<?php
declare(strict_types=1);

namespace Discovery\Agents;

use Discovery\DiscoveryArticleCatalog;
use Discovery\DiscoveryQualityGate;
use Discovery\DiscoveryRepository;
use Discovery\DiscoveryRunResult;
use Discovery\DiscoveryUrlGuard;
use Discovery\SourceVerifier;
use Discovery\SourceWhitelist;

final class Pipeline
{
    private SourceWhitelist $whitelist;
    private DiscoveryQualityGate $qualityGate;
    private DiscoveryUrlGuard $urlGuard;

    /** @var list<array<string, mixed>> */
    private array $stageLogs = [];

    public function __construct(
        private readonly DiscoveryRepository $repo,
        private readonly ExtractorAgent $extractor,
        private readonly BrieferAgent $briefer,
        private readonly SourceVerifier $verifier,
        private readonly array $config,
        private readonly array $agentConfig,
    ) {
        $this->whitelist = new SourceWhitelist(
            $config['source_whitelist'] ?? [],
            $config['source_blocklist'] ?? [],
        );
        $this->qualityGate = new DiscoveryQualityGate();
        $this->urlGuard = new DiscoveryUrlGuard();
    }

    public function run(string $date, bool $forceRegenerate = false): DiscoveryRunResult
    {
        if (!$forceRegenerate && $this->repo->hasPublishedRealEditionForDate($date)) {
            throw new \RuntimeException(
                sprintf('Published real edition already exists for %s. Use --force to regenerate.', $date)
            );
        }

        $started = time();
        $edition = $this->repo->createEdition($date, 'generating');

        try {
            // A. Collector
            $catalog = new DiscoveryArticleCatalog($this->config, $this->whitelist);
            $articles = $catalog->fetch($date);
            $this->logStage('collector', count($articles), count($articles));

            // B. Curator (Phase 1: top N from catalog)
            $curatorLimit = (int) ($this->agentConfig['pipeline']['curator_limit'] ?? 15);
            $selected = array_slice($articles, 0, $curatorLimit);
            $this->logStage('curator', count($articles), count($selected));

            // C. Extractor
            $extracted = $this->extractor->process(['articles' => $selected], $this->agentConfig);
            $extractionStats = $extracted->output['extraction_stats'] ?? [];
            $this->logStage('extractor', $extracted->inputCount, $extracted->outputCount, $extracted->discarded, [
                'full' => $extractionStats['full'] ?? 0,
                'summary_only' => $extractionStats['summary_only'] ?? 0,
                'failed' => $extractionStats['failed'] ?? 0,
                'by_domain' => $extractionStats['by_domain'] ?? [],
            ]);

            /** @var list<array<string, mixed>> $extractedArticles */
            $extractedArticles = $extracted->output['articles'] ?? [];

            // D. Briefer
            $briefed = $this->briefer->process(['articles' => $extractedArticles], $this->agentConfig);
            $this->logStage('briefer', $briefed->inputCount, $briefed->outputCount, $briefed->discarded);

            /** @var list<array<string, mixed>> $candidates */
            $candidates = $briefed->output['changes'] ?? [];

            // E-G. Verification + quality gates + finalize
            $discarded = array_merge($extracted->discarded, $briefed->discarded);
            $verified = $this->applyVerificationAndGates($candidates, $discarded);

            $maxTarget = (int) ($this->config['target_changes'] ?? 7);
            $verified = array_slice($verified, 0, $maxTarget);

            $minTarget = (int) ($this->config['min_changes'] ?? 5);
            $warning = $this->buildWarning(count($verified), $minTarget, $maxTarget);

            // Strip internal fields before save
            $toSave = array_map(static function (array $change): array {
                unset($change['body_text'], $change['extraction_status']);
                return $change;
            }, $verified);

            $this->repo->saveChanges((int) $edition['id'], $toSave);
            $this->repo->setEditionChangeCount((int) $edition['id'], count($toSave), $warning);
            $this->repo->updateEditionStatus((int) $edition['id'], 'draft', $warning);

            $duration = time() - $started;
            $this->repo->logRun($date, count($verified), count($discarded), $discarded, null, $duration);

            $freshEdition = $this->repo->findEditionById((int) $edition['id']) ?? $edition;

            $fullCount = 0;
            $summaryOnlyCount = 0;
            foreach ($extractedArticles as $a) {
                if (($a['extraction_status'] ?? '') === 'full') {
                    $fullCount++;
                } else {
                    $summaryOnlyCount++;
                }
            }

            return new DiscoveryRunResult($freshEdition, $toSave, $discarded, $duration, [
                'generation_mode' => 'multi_agent_v1',
                'catalog_count' => count($articles),
                'stage_logs' => $this->stageLogs,
                'extraction_full' => $fullCount,
                'extraction_summary_only' => $summaryOnlyCount,
            ]);
        } catch (\Throwable $e) {
            $this->repo->updateEditionStatus((int) $edition['id'], 'draft', '생성 실패: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @param list<array<string, mixed>> $discarded
     * @return list<array<string, mixed>>
     */
    private function applyVerificationAndGates(array $candidates, array &$discarded): array
    {
        $sourcePassed = [];

        foreach ($candidates as $candidate) {
            $title = (string) ($candidate['title'] ?? '');
            $rawSources = is_array($candidate['sources'] ?? null) ? $candidate['sources'] : [];

            $hallucinated = false;
            foreach ($rawSources as $source) {
                if (!is_array($source)) {
                    continue;
                }
                $url = trim((string) ($source['url'] ?? ''));
                if ($url !== '' && $this->urlGuard->looksHallucinated($url)) {
                    $hallucinated = true;
                    break;
                }
            }
            if ($hallucinated) {
                $discarded[] = ['title' => $title, 'reason' => 'url_hallucination_suspected', 'sources' => $rawSources];
                continue;
            }

            if ($this->whitelist->hasBlockedSource($rawSources)) {
                $discarded[] = ['title' => $title, 'reason' => 'korean_media_source', 'sources' => $rawSources];
                continue;
            }

            if (!$this->whitelist->hasWhitelistedSource($rawSources)) {
                $discarded[] = ['title' => $title, 'reason' => 'whitelist_failed', 'sources' => $rawSources];
                continue;
            }

            $sources = $this->verifier->verify($rawSources, $title);
            $validSources = $this->whitelist->filterWhitelistedSources(
                array_values(array_filter($sources, static fn($s) => !empty($s['verified'])))
            );

            if (count($validSources) < 1) {
                $discarded[] = ['title' => $title, 'reason' => 'source_verification_failed', 'sources' => $sources];
                continue;
            }

            if (!$this->pollOptionsDistinct($candidate['poll']['options'] ?? [])) {
                $discarded[] = ['title' => $title, 'reason' => 'poll_options_overlap'];
                continue;
            }

            $candidate['sources'] = $validSources;
            $sourcePassed[] = $candidate;
        }

        $this->logStage('verifier', count($candidates), count($sourcePassed));

        $afterImportance = $this->qualityGate->applyImportanceCategory($sourcePassed, $discarded);
        $this->logStage('importance_gate', count($sourcePassed), count($afterImportance));

        $afterCompleteness = $this->qualityGate->applyCompleteness($afterImportance, $discarded);
        $this->logStage('completeness_gate', count($afterImportance), count($afterCompleteness));

        $deduped = $this->qualityGate->applyDeduplication($afterCompleteness, $discarded);
        $this->logStage('dedup_gate', count($afterCompleteness), count($deduped));

        return $deduped;
    }

    /** @param list<string> $options */
    private function pollOptionsDistinct(array $options): bool
    {
        if (count($options) !== 4) {
            return false;
        }
        $normalized = array_map(static function ($o) {
            $t = mb_strtolower(trim((string) $o));
            return preg_replace('/\s+/u', '', $t) ?? $t;
        }, $options);

        return count(array_unique($normalized)) === 4;
    }

    private function buildWarning(int $count, int $minTarget, int $maxTarget): ?string
    {
        if ($count < $minTarget) {
            return sprintf(
                '%d개만 생성됨 (목표 %d~%d개). 억지로 채우지 않았습니다.',
                $count,
                $minTarget,
                $maxTarget
            );
        }
        if ($count < $maxTarget) {
            return sprintf(
                '%d개 생성됨 (목표 %d~%d개). 품질 게이트 통과분만 반영했습니다.',
                $count,
                $minTarget,
                $maxTarget
            );
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $discarded
     * @param array<string, mixed> $extra
     */
    private function logStage(string $stage, int $input, int $output, array $discarded = [], array $extra = []): void
    {
        $log = [
            'stage' => $stage,
            'input' => $input,
            'output' => $output,
            'discarded' => count($discarded),
        ];
        if (!empty($extra)) {
            $log = array_merge($log, $extra);
        }
        $this->stageLogs[] = $log;
    }

    /** @return list<array<string, mixed>> */
    public function getStageLogs(): array
    {
        return $this->stageLogs;
    }
}
