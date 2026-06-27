<?php
/**
 * L5 parity — explicit coach_level=5 (legacy 7) must match v1 default (no level arg).
 *
 * Usage: php tools/edu_coach_guide_level5_parity_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduCoachGuide.php';

$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

function assertSame(string $label, mixed $a, mixed $b): void
{
    assertTrue($label, $a === $b);
}

$quest = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE,
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([]), [
        'hook_short' => '핵무기가 있으면 재래식 공격과 전쟁 확대를 정말 막을 수 있을까?',
    ]),
];

$bp = eduBlueprintDefaults();
$opening = '핵이 있으면 재래식 공격은 막힐 것 같아요';

$legacyOpen = eduCoachGuideHandleOpening($bp, $quest, $opening);
$level5Open = eduCoachGuideHandleOpening($bp, $quest, $opening, EDU_COACH_LEVEL_L5);
$legacy7Open = eduCoachGuideHandleOpening($bp, $quest, $opening, 7);

assertSame('opening message legacy vs L5', $legacyOpen['message'], $level5Open['message']);
assertSame('opening message legacy vs legacy-7 input', $legacyOpen['message'], $legacy7Open['message']);
assertSame('opening phase legacy vs L5', $legacyOpen['blueprint']['phase'] ?? '', $level5Open['blueprint']['phase'] ?? '');
assertSame('L5 stores coach_level 5', 5, (int) ($level5Open['blueprint']['coach_level'] ?? 0));

$bothTurnLegacy = eduCoachGuideHandleTurn($level5Open['blueprint'], $quest, '둘 다 필요해');
$bothTurnL5 = eduCoachGuideHandleTurn($level5Open['blueprint'], $quest, '둘 다 필요해', EDU_COACH_LEVEL_L5);
assertSame('evasion turn message legacy vs L5', $bothTurnLegacy['message'], $bothTurnL5['message']);

$passTurnLegacy = eduCoachGuideHandleTurn($bothTurnLegacy['blueprint'], $quest, '우크라이나 사례 보면 핵 대신 재래식만 써서 약하게 해줘');
$passTurnL5 = eduCoachGuideHandleTurn($bothTurnLegacy['blueprint'], $quest, '우크라이나 사례 보면 핵 대신 재래식만 써서 약하게 해줘', EDU_COACH_LEVEL_L5);
assertSame('pass turn message legacy vs L5', $passTurnLegacy['message'], $passTurnL5['message']);

assertTrue('resolve defaults to L1 when elementary ready', eduResolveCoachLevel([], []) === EDU_COACH_LEVEL_DEFAULT);
assertTrue('legacy blueprint 7 → L5', eduResolveCoachLevel(['coach_level' => 7], []) === EDU_COACH_LEVEL_L5);
assertTrue('L3 path is middle coach', eduCoachLevelCoachPath(EDU_COACH_LEVEL_L3) === 'l3');
assertTrue('freeze stores normalized L5', (eduBlueprintFreezeCoachLevel([], 7)['coach_level'] ?? 0) === EDU_COACH_LEVEL_L5);
assertTrue('elementary ready flag', eduCoachGuideElementaryReady() === true);

$level1Open = eduCoachGuideHandleOpening($bp, $quest, $opening, EDU_COACH_LEVEL_L1);
assertTrue('L1 differs from L5 intro', $level1Open['message'] !== $level5Open['message']);

$level2Open = eduCoachGuideHandleOpening($bp, $quest, $opening, EDU_COACH_LEVEL_L2);
assertTrue('L2 differs from L1 intro', $level2Open['message'] !== $level1Open['message']);
assertTrue('L2 differs from L5 intro', $level2Open['message'] !== $level5Open['message']);
assertTrue('L2 stores coach_level 2', (int) ($level2Open['blueprint']['coach_level'] ?? 0) === EDU_COACH_LEVEL_L2);

$level3Open = eduCoachGuideHandleOpening($bp, $quest, $opening, EDU_COACH_LEVEL_L3);
assertTrue('L3 intro differs from L5 (middle vs v1)', $level3Open['message'] !== $level5Open['message']);
assertTrue('L3 intro uses dual-sided framing', str_contains($level3Open['message'], '한쪽') || str_contains($level3Open['message'], '양쪽'));
assertTrue('L3 stores coach_level 3', (int) ($level3Open['blueprint']['coach_level'] ?? 0) === EDU_COACH_LEVEL_L3);
assertTrue('L3 differs from L2 intro', $level3Open['message'] !== $level2Open['message']);

$level4Open = eduCoachGuideHandleOpening($bp, $quest, $opening, EDU_COACH_LEVEL_L4);
assertTrue('L4 differs from L3 intro (evidence lead)', $level4Open['message'] !== $level3Open['message']);
assertTrue('L4 stores coach_level 4', (int) ($level4Open['blueprint']['coach_level'] ?? 0) === EDU_COACH_LEVEL_L4);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
