<?php
declare(strict_types=1);

namespace App\Services;

class QualityFilterService
{
    private int $minWordCount;

    public function __construct(array $config = [])
    {
        $default = file_exists(dirname(__DIR__, 3) . '/config/intelligence.php')
            ? require dirname(__DIR__, 3) . '/config/intelligence.php'
            : [];
        $merged = array_merge($default, $config);
        $this->minWordCount = (int) ($merged['min_word_count'] ?? 150);
    }

    public function evaluate(int $wordCount, string $trustTier = 'B'): array
    {
        $passed = $wordCount >= $this->minWordCount;
        $score = min(100, (int) round(($wordCount / max($this->minWordCount, 1)) * 70));
        if ($trustTier === 'A') {
            $score = min(100, $score + 15);
        } elseif ($trustTier === 'C') {
            $score = max(0, $score - 15);
        }
        return [
            'passed' => $passed,
            'quality_score' => $score,
            'reason' => $passed ? null : 'word_count_below_minimum',
        ];
    }
}
