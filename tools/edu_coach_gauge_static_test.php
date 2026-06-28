<?php
/**
 * B-2 — 코치 레벨 게이지 XP 정적 회귀 (DB/LLM 없음)
 *
 * Usage: php tools/edu_coach_gauge_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduGamification.php';
require_once $root . '/public/api/edu/lib/eduTier.php';
require_once $root . '/public/api/edu/lib/eduCoachLevel.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

echo "=== B-2 coach gauge static test ===\n\n";

$qualityDiag = [
    'axes_covered' => [
        ['covered' => true, 'status' => 'engaged'],
        ['covered' => true, 'status' => 'engaged'],
    ],
    'tension_engaged' => '양면',
    'evidence_linked' => 'yes',
    'exploration_depth_level' => 4,
];

$weakDiag = [
    'axes_covered' => [['covered' => false, 'status' => 'skipped']],
    'tension_engaged' => '없음',
    'evidence_linked' => 'no',
    'exploration_depth_level' => 1,
];

$l1Hit = eduXpAwardFromDiagnose($qualityDiag, 1, []);
$l1Miss = eduXpAwardFromDiagnose($weakDiag, 1, []);
ok('L1 gate hit > miss', $l1Hit['xp'] > $l1Miss['xp']);
ok('L1 gate hit', $l1Hit['gate_hit'] === true);
ok('L1 gate miss', $l1Miss['gate_hit'] === false);

$l2Hit = eduXpAwardFromDiagnose($qualityDiag, 2, ['counter_handled' => true]);
$l2Miss = eduXpAwardFromDiagnose($weakDiag, 2, []);
ok('L2 counter gate hit > miss', $l2Hit['xp'] > $l2Miss['xp']);

$l3Hit = eduXpAwardFromDiagnose($qualityDiag, 3, ['counter_handled' => true]);
$l3Miss = eduXpAwardFromDiagnose($weakDiag, 3, []);
ok('L3 evidence gate hit > miss', $l3Hit['xp'] > $l3Miss['xp']);

$l4Hit = eduXpAwardFromDiagnose($qualityDiag, 4, ['counter_handled' => true]);
$l4Miss = eduXpAwardFromDiagnose($weakDiag, 4, []);
ok('L4 meta gate hit > miss', $l4Hit['xp'] > $l4Miss['xp']);

ok('floor XP on miss (5-8)', $l1Miss['xp'] >= 5 && $l1Miss['xp'] <= 8);
ok('quality XP cap', $l1Hit['xp'] <= 28);

$gaugeRow = ['coach_gauge_xp' => 80, 'streak_days' => 3, 'tier_id' => 'observer', 'status' => 'active'];
$payload = eduCoachGaugeProgressPayload(2, $gaugeRow);
ok('gauge progress 80%', ($payload['coach_gauge_progress_pct'] ?? 0) === 80);
ok('gauge not full at 80', ($payload['coach_gauge_full'] ?? true) === false);

$fullRow = ['coach_gauge_xp' => 100, 'streak_days' => 3, 'tier_id' => 'observer', 'status' => 'active'];
$fullPayload = eduCoachGaugeProgressPayload(3, $fullRow);
ok('gauge full at 100', ($fullPayload['coach_gauge_full'] ?? false) === true);

$tierPayload = eduTierProgressPayload($fullRow, 3);
ok('tier progress uses gauge', ($tierPayload['progress_pct'] ?? 0) === 100);
ok('next coach label', ($tierPayload['next_coach_label_ko'] ?? '') === '분석가');
ok('streak preserved in payload', ($tierPayload['streak_days'] ?? 0) === 3);

$hero = (string) file_get_contents($root . '/src/frontend/src/components/edu/EduStudentProfileHero.tsx');
ok('profile gauge UI', str_contains($hero, 'coach_gauge') || str_contains($hero, 'gaugePct'));
ok('profile soon levelup', str_contains($hero, '곧 레벨업'));

$homeCard = (string) file_get_contents($root . '/src/frontend/src/components/edu/TierProgressCard.tsx');
ok('home gauge UI', str_contains($homeCard, 'coach_gauge') || str_contains($homeCard, 'gaugePct'));
ok('home no legacy iron tier', !str_contains($homeCard, 'tier_label_en'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
