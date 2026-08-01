<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryPipeline
{
    private SourceWhitelist $whitelist;
    private DiscoveryQualityGate $qualityGate;
    private DiscoveryUrlGuard $urlGuard;

    public function __construct(
        private readonly DiscoveryRepository $repo,
        private readonly DiscoveryAgent $agent,
        private readonly SourceVerifier $verifier,
        private readonly array $config,
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
            $llmResult = $this->agent->generateDailyChanges($date);
            $candidates = $llmResult['changes'] ?? [];
            $generationMode = (string) ($llmResult['generation_mode'] ?? 'unknown');
            $catalogCount = (int) ($llmResult['catalog_count'] ?? 0);

            $discarded = [];
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
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'url_hallucination_suspected',
                        'sources' => $rawSources,
                    ];
                    continue;
                }

                if ($this->whitelist->hasBlockedSource($rawSources)) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'korean_media_source',
                        'sources' => array_map(fn(array $s) => [
                            'name' => $s['name'] ?? '',
                            'url' => $s['url'] ?? '',
                            'domain' => $this->whitelist->hostLabel((string) ($s['url'] ?? '')),
                        ], $rawSources),
                    ];
                    continue;
                }

                if (!$this->whitelist->hasWhitelistedSource($rawSources)) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'whitelist_failed',
                        'sources' => array_map(fn(array $s) => [
                            'name' => $s['name'] ?? '',
                            'url' => $s['url'] ?? '',
                            'domain' => $this->whitelist->hostLabel((string) ($s['url'] ?? '')),
                        ], $rawSources),
                    ];
                    continue;
                }

                $sources = $this->verifier->verify($rawSources, $title);
                $validSources = $this->whitelist->filterWhitelistedSources(
                    array_values(array_filter($sources, static fn($s) => !empty($s['verified'])))
                );

                if (count($validSources) < 1) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'source_verification_failed',
                        'sources' => $sources,
                    ];
                    continue;
                }

                if (!$this->pollOptionsDistinct($candidate['poll']['options'] ?? [])) {
                    $discarded[] = [
                        'title' => $title,
                        'reason' => 'poll_options_overlap',
                    ];
                    continue;
                }

                $candidate['sources'] = $validSources;
                $sourcePassed[] = $candidate;
            }

            $afterImportance = $this->qualityGate->applyImportanceCategory($sourcePassed, $discarded);
            $afterCompleteness = $this->qualityGate->applyCompleteness($afterImportance, $discarded);
            $deduped = $this->qualityGate->applyDeduplication($afterCompleteness, $discarded);

            $maxTarget = (int) ($this->config['target_changes'] ?? 7);
            $verified = array_slice($deduped, 0, $maxTarget);

            $minTarget = (int) ($this->config['min_changes'] ?? 5);
            $warning = null;
            if (count($verified) < $minTarget) {
                $warning = sprintf(
                    '%d개만 생성됨 (목표 %d~%d개). 억지로 채우지 않았습니다.',
                    count($verified),
                    $minTarget,
                    $maxTarget
                );
            } elseif (count($verified) < $maxTarget) {
                $warning = sprintf(
                    '%d개 생성됨 (목표 %d~%d개). 품질 게이트 통과분만 반영했습니다.',
                    count($verified),
                    $minTarget,
                    $maxTarget
                );
            }

            $this->repo->saveChanges((int) $edition['id'], $verified);
            $this->repo->setEditionChangeCount((int) $edition['id'], count($verified), $warning);
            $this->repo->updateEditionStatus((int) $edition['id'], 'draft', $warning);

            $duration = time() - $started;
            $this->repo->logRun($date, count($verified), count($discarded), $discarded, $llmResult['cost_usd'] ?? null, $duration);

            $freshEdition = $this->repo->findEditionById((int) $edition['id']) ?? $edition;

            return new DiscoveryRunResult($freshEdition, $verified, $discarded, $duration, [
                'generation_mode' => $generationMode,
                'catalog_count' => $catalogCount,
            ]);
        } catch (\Throwable $e) {
            $this->repo->updateEditionStatus((int) $edition['id'], 'draft', '생성 실패: ' . $e->getMessage());
            throw $e;
        }
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
}
