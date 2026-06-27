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

$axisPassMsg = '우크라이나 사례 보면 핵 대신 재래식만 써서 약하게 해줘';
$passTurnLegacy = eduCoachGuideHandleTurn($bothTurnLegacy['blueprint'], $quest, $axisPassMsg);
$passTurnL5 = eduCoachGuideHandleTurn($bothTurnLegacy['blueprint'], $quest, $axisPassMsg, EDU_COACH_LEVEL_L5);
assertSame('pass turn message legacy vs L5', $passTurnLegacy['message'], $passTurnL5['message']);
assertTrue(
    'L5 axis pass triggers meta counter-ask (not next axis)',
    str_contains($passTurnL5['message'], '반대')
        || str_contains($passTurnL5['message'], '받아칠')
);
assertTrue(
    'L5 meta ask does not spoonfeed counter-argument',
    !str_contains($passTurnL5['message'], '거미줄')
        && !str_contains($passTurnL5['message'], '재래식 보복만')
);
assertTrue(
    'L5 sets guide_axis_pending_meta after axis pass',
    is_array($passTurnL5['blueprint']['guide_axis_pending_meta'] ?? null)
);

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
assertTrue('L4 intro asks for evidence not spoonfeed', str_contains($level4Open['message'], '근거'));
assertTrue('L4 stores coach_level 4', (int) ($level4Open['blueprint']['coach_level'] ?? 0) === EDU_COACH_LEVEL_L4);

$l4Bp = $level4Open['blueprint'];
$vagueTurn = eduCoachGuideHandleTurn($l4Bp, $quest, '그냥 도움이 될 것 같아요', EDU_COACH_LEVEL_L4);
assertTrue('L4 vague answer triggers evidence ask', str_contains($vagueTurn['message'], '기사') || str_contains($vagueTurn['message'], '사건'));
assertTrue('L4 evidence ask does not spoonfeed 거미줄', !str_contains($vagueTurn['message'], '거미줄'));

$l3Vague = eduCoachGuideHandleTurn($level3Open['blueprint'], $quest, '그냥 도움이 될 것 같아요', EDU_COACH_LEVEL_L3);
assertTrue('L4 evidence flow differs from L3 on vague answer', $vagueTurn['message'] !== $l3Vague['message']);

$l4EvidencePass = eduCoachGuideHandleTurn(
    $l4Bp,
    $quest,
    '우크라이나 거미줄 작전 후 러시아가 재래식만 썼어요',
    EDU_COACH_LEVEL_L4
);
assertTrue(
    'L4 evidence pass asks layer_half not meta counter',
    str_contains($l4EvidencePass['message'], '반대') || str_contains($l4EvidencePass['message'], '약해')
);
assertTrue(
    'L4 differs from L5 meta ask on same axis answer',
    $l4EvidencePass['message'] !== $passTurnL5['message']
);
assertTrue(
    'L4 does not set guide_axis_pending_meta',
    !is_array($l4EvidencePass['blueprint']['guide_axis_pending_meta'] ?? null)
);

$metaReply = eduCoachGuideHandleTurn(
    $passTurnL5['blueprint'],
    $quest,
    '반대편은 핵이 없으면 재래식으로 밀릴 거라고 할 것 같아요',
    EDU_COACH_LEVEL_L5
);
assertTrue(
    'L5 meta reply advances to next axis intro',
    str_contains($metaReply['message'], '다음') || (int) ($metaReply['blueprint']['guide_axis_index'] ?? 0) === 1
);

$l5ConclusionDraft = eduCoachGuideHandleTurn(
    array_merge($level5Open['blueprint'], ['phase' => 'guide_conclusion', 'guide_conclusion_meta_done' => false]),
    $quest,
    '나는 핵 억지가 재래식까지는 못 막는다고 본다',
    EDU_COACH_LEVEL_L5
);
assertTrue(
    'L5 conclusion draft triggers meta counter-ask',
    str_contains($l5ConclusionDraft['message'], '반대') || str_contains($l5ConclusionDraft['message'], '받아칠')
);
assertTrue('L5 path is l5', eduCoachLevelCoachPath(EDU_COACH_LEVEL_L5) === 'l5');

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
