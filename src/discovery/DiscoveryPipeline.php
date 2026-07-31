<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryPipeline
{
    public function __construct(
        private readonly DiscoveryRepository $repo,
        private readonly DiscoveryAgent $agent,
        private readonly SourceVerifier $verifier,
        private readonly array $config,
    ) {
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

            $verified = [];
            $discarded = [];

            foreach ($candidates as $candidate) {
                $sources = $this->verifier->verify($candidate['sources'] ?? [], $candidate['title']);
                $validSources = array_values(array_filter($sources, static fn($s) => !empty($s['verified'])));

                if (count($validSources) < 1) {
                    $discarded[] = [
                        'title' => $candidate['title'] ?? '',
                        'reason' => 'source_verification_failed',
                        'sources' => $sources,
                    ];
                    continue;
                }

                if (!$this->pollOptionsDistinct($candidate['poll']['options'] ?? [])) {
                    $discarded[] = [
                        'title' => $candidate['title'] ?? '',
                        'reason' => 'poll_options_overlap',
                    ];
                    continue;
                }

                $candidate['sources'] = $validSources;
                $verified[] = $candidate;

                if (count($verified) >= (int) ($this->config['target_changes'] ?? 9)) {
                    break;
                }
            }

            $target = (int) ($this->config['target_changes'] ?? 9);
            $warning = null;
            if (count($verified) < $target) {
                $warning = sprintf('%d개만 생성됨 (목표 %d개). 억지로 채우지 않았습니다.', count($verified), $target);
            }

            $this->repo->saveChanges((int) $edition['id'], $verified);
            $this->repo->setEditionChangeCount((int) $edition['id'], count($verified), $warning);
            $this->repo->updateEditionStatus((int) $edition['id'], 'draft', $warning);

            $duration = time() - $started;
            $this->repo->logRun($date, count($verified), count($discarded), $discarded, $llmResult['cost_usd'] ?? null, $duration);

            $freshEdition = $this->repo->findEditionById((int) $edition['id']) ?? $edition;

            return new DiscoveryRunResult($freshEdition, $verified, $discarded, $duration);
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
