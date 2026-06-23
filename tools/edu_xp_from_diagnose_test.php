<?php
/**
 * P2-B 진단 → XP 산식 회귀 (LLM 없음)
 *
 * Usage: php tools/edu_xp_from_diagnose_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduStructureDiagnose.php';
require_once $root . '/public/api/edu/lib/eduGamification.php';

$pass = 0;
$fail = 0;

function assertEq(string $label, $expected, $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label} expected=" . json_encode($expected) . ' actual=' . json_encode($actual) . "\n";
        $fail++;
    }
}

function assertTrue(string $label, bool $ok): void
{
    assertEq($label, true, $ok);
}

$fixturePath = $root . '/docs/structure_diagnoses/fixture-630-sample.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true);
$diag = eduStructureDiagnoseSession(
    (string) ($fixture['session_id'] ?? 'fixture'),
    $fixture['quest'] ?? [],
    $fixture['blueprint'] ?? [],
    $fixture['dialogue'] ?? [],
    null,
    (string) ($fixture['essay_text'] ?? '')
);
$fullXp = eduXpFromStructureDiagnose($diag);

$skippedDiag = $diag;
$skippedDiag['axes_covered'] = array_map(static function (array $a): array {
    $a['covered'] = false;
    $a['status'] = 'skipped';

    return $a;
}, $diag['axes_covered'] ?? []);
$skippedDiag['tension_engaged'] = '없음';
$skippedDiag['conclusion_clarity'] = '모호';
$skippedDiag['evidence_linked'] = 'no';
assertEq('all skipped evasion complete', 5, eduXpFromStructureDiagnose($skippedDiag));

$partialDiag = [
    'axes_covered' => [
        ['covered' => true, 'status' => 'engaged'],
        ['covered' => true, 'status' => 'engaged'],
        ['covered' => false, 'status' => 'missing'],
    ],
    'tension_engaged' => '한쪽',
    'conclusion_clarity' => '모호',
    'evidence_linked' => 'no',
];
assertEq('2 axis partial 20', 20, eduXpFromStructureDiagnose($partialDiag));

$maxDiag = [
    'axes_covered' => [
        ['covered' => true, 'status' => 'engaged'],
        ['covered' => true, 'status' => 'engaged'],
        ['covered' => true, 'status' => 'engaged'],
    ],
    'tension_engaged' => '양면',
    'conclusion_clarity' => '명확',
    'evidence_linked' => 'yes',
];
assertEq('max depth 65', 65, eduXpFromStructureDiagnose($maxDiag));

assertTrue('630 rule path xp', $fullXp >= 30 && $fullXp <= 65);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
