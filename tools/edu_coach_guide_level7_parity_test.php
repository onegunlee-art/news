<?php
/**
 * Level 7 parity — explicit coach_level=7 must match v1 default (no level arg).
 *
 * Usage: php tools/edu_coach_guide_level7_parity_test.php
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
$level7Open = eduCoachGuideHandleOpening($bp, $quest, $opening, EDU_COACH_LEVEL_ADVANCED);

assertSame('opening message legacy vs level 7', $legacyOpen['message'], $level7Open['message']);
assertSame('opening phase legacy vs level 7', $legacyOpen['blueprint']['phase'] ?? '', $level7Open['blueprint']['phase'] ?? '');

$bothTurnLegacy = eduCoachGuideHandleTurn($level7Open['blueprint'], $quest, '둘 다 필요해');
$bothTurnL7 = eduCoachGuideHandleTurn($level7Open['blueprint'], $quest, '둘 다 필요해', EDU_COACH_LEVEL_ADVANCED);
assertSame('evasion turn message legacy vs level 7', $bothTurnLegacy['message'], $bothTurnL7['message']);

$passTurnLegacy = eduCoachGuideHandleTurn($bothTurnLegacy['blueprint'], $quest, '우크라이나 사례 보면 핵 대신 재래식만 써서 약하게 해줘');
$passTurnL7 = eduCoachGuideHandleTurn($bothTurnLegacy['blueprint'], $quest, '우크라이나 사례 보면 핵 대신 재래식만 써서 약하게 해줘', EDU_COACH_LEVEL_ADVANCED);
assertSame('pass turn message legacy vs level 7', $passTurnLegacy['message'], $passTurnL7['message']);

assertTrue('resolve defaults to level 7 before elementary ready', eduResolveCoachLevel([], []) === EDU_COACH_LEVEL_ADVANCED);
assertTrue('blueprint coach_level frozen', (eduBlueprintFreezeCoachLevel([], 7)['coach_level'] ?? 0) === 7);
assertTrue('elementary not ready yet', eduCoachGuideElementaryReady() === false);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
