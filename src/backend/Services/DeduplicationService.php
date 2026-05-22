<?php
declare(strict_types=1);

namespace App\Services;

class DeduplicationService
{
    private float $threshold;

    public function __construct(array $config = [])
    {
        $default = file_exists(dirname(__DIR__, 3) . '/config/intelligence.php')
            ? require dirname(__DIR__, 3) . '/config/intelligence.php'
            : [];
        $merged = array_merge($default, $config);
        $this->threshold = (float) ($merged['dedup_title_threshold'] ?? 0.80);
    }

    public function findDuplicateId(string $title, array $candidates): ?int
    {
        $titleNorm = $this->normalizeTitle($title);
        $bestId = null;
        $bestScore = 0.0;
        foreach ($candidates as $row) {
            $candidateTitle = $this->normalizeTitle((string) ($row['title'] ?? ''));
            if ($candidateTitle === '' || $titleNorm === '') {
                continue;
            }
            similar_text($titleNorm, $candidateTitle, $pct);
            $score = $pct / 100;
            if ($score >= $this->threshold && $score > $bestScore) {
                $bestScore = $score;
                $bestId = (int) $row['id'];
            }
        }
        return $bestId;
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? $title;
        return trim(preg_replace('/\s+/', ' ', $title) ?? $title);
    }
}
