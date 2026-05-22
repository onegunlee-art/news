<?php
declare(strict_types=1);

namespace App\Services;

class QualityFilterService
{
    private int $minWordCount;
    private int $minWordCountTierA;

    public function __construct(array $config = [])
    {
        $default = file_exists(dirname(__DIR__, 3) . '/config/intelligence.php')
            ? require dirname(__DIR__, 3) . '/config/intelligence.php'
            : [];
        $merged = array_merge($default, $config);
        $this->minWordCount = (int) ($merged['min_word_count'] ?? 150);
        $this->minWordCountTierA = (int) ($merged['min_word_count_tier_a'] ?? 80);
    }

    public function evaluate(int $wordCount, string $trustTier = 'B'): array
    {
        $minRequired = $trustTier === 'A' ? $this->minWordCountTierA : $this->minWordCount;
        $passed = $wordCount >= $minRequired;
        $score = min(100, (int) round(($wordCount / max($minRequired, 1)) * 70));
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
