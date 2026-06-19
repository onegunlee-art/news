<?php
/**
 * P2-A1 — needs_review / normalize (LLM 없음)
 *
 * Usage: php tools/edu_hinge_normalize_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';

function assertTrue(string $label, bool $cond): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

$high = eduHingeNormalize([
    'hinge' => 'A이지만 B',
    'side_a' => 'a',
    'side_b' => 'b',
    'confidence' => 'high',
], 630, 'test');
assertTrue('high → needs_review false', $high['needs_review'] === false);

$medium = eduHingeNormalize([
    'hinge' => 'A이지만 B',
    'confidence' => 'medium',
], 631, 'test');
assertTrue('medium → needs_review false', $medium['needs_review'] === false);

$low = eduHingeNormalize([
    'hinge' => 'A이지만 B',
    'confidence' => 'low',
], 632, 'test');
assertTrue('low → needs_review true', $low['needs_review'] === true);

$nullHinge = eduHingeNormalize([
    'hinge' => null,
    'confidence' => 'high',
], 633, 'test');
assertTrue('null hinge → needs_review true', $nullHinge['needs_review'] === true);

$emptyConf = eduHingeNormalize([
    'hinge' => 'A이지만 B',
    'confidence' => '',
], 634, 'test');
assertTrue('empty confidence → needs_review true', $emptyConf['needs_review'] === true);

$diff = eduHingeDiffFields(
    ['hinge' => 'old', 'side_a' => 'same'],
    ['hinge' => 'new', 'side_a' => 'same']
);
assertTrue('diff detects hinge only', $diff === ['hinge']);

echo "\nAll normalize tests passed.\n";
