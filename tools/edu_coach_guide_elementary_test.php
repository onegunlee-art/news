<?php
/**
 * EDU coach level 1 — 초등 인도 회귀 (LLM 없음)
 *
 * Usage: php tools/edu_coach_guide_elementary_test.php
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

function assertContains(string $label, string $haystack, string $needle): void
{
    assertTrue($label, str_contains($haystack, $needle));
}

function assertNotContains(string $label, string $haystack, string $needle): void
{
    assertTrue($label, !str_contains($haystack, $needle));
}

$quest630 = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE,
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([]), [
        'hook_short' => '핵무기가 있으면 재래식 공격과 전쟁 확대를 정말 막을 수 있을까?',
    ]),
];

assertTrue('elementary ready', eduCoachGuideElementaryReady() === true);
assertTrue('default resolve is level 1', eduResolveCoachLevel([], []) === EDU_COACH_LEVEL_DEFAULT);
assertTrue('elementary 630 has 2 axes', count(eduCoachGuideElementaryAxes($quest630)) === 2);

$bp = eduBlueprintDefaults();
$open = eduCoachGuideElementaryHandleOpening($bp, $quest630, '핵이 있으면 공격을 막을 것 같아');
assertContains('630 intro friendly tone', $open['message'], '어떤 생각');
assertNotContains('630 intro no 고농축', $open['message'], '고농축');
assertTrue('630 intro sets coach_level 1', (int) ($open['blueprint']['coach_level'] ?? 0) === 1);

$unknown = eduCoachGuideElementaryHandleTurn($open['blueprint'], $quest630, '모르겠어');
assertContains('unknown gentle scaffold', $unknown['message'], '괜찮아');
assertNotContains('unknown no conclusion spoonfeed', $unknown['message'], '그러니까');

$passTurn = eduCoachGuideElementaryHandleTurn(
    $open['blueprint'],
    $quest630,
    '우크라이나 보면 핵 대신 일반 무기만 써서 약해진 것 같아'
);
assertTrue('axis pass advances index', (int) ($passTurn['blueprint']['guide_axis_index'] ?? 0) === 1);

$pass2 = eduCoachGuideElementaryHandleTurn(
    $passTurn['blueprint'],
    $quest630,
    '약속이 있어도 싸움은 났으니까 안 됐어'
);
assertTrue('2 axes -> guide_conclusion', ($pass2['blueprint']['phase'] ?? '') === 'guide_conclusion');
assertContains('conclusion asks student voice', $pass2['message'], '나는');

$quest196 = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE_IRAN_196,
    'hammer_hints' => eduCoachGuideAttachHints([], 196),
];
$open196 = eduCoachGuideElementaryHandleOpening($bp, $quest196, '이란이 위험해진 것 같아');
assertNotContains('196 no 고농축 우라늄', $open196['message'], '고농축');
assertContains('196 simplified intro', $open196['message'], '지도자');

$level7Open = eduCoachGuideHandleOpening($bp, $quest630, '핵이 있으면 재래식 공격은 막힐 것 같아요', EDU_COACH_LEVEL_ADVANCED);
$level1Open = eduCoachGuideHandleOpening($bp, $quest630, '핵이 있으면 재래식 공격은 막힐 것 같아요', EDU_COACH_LEVEL_ELEMENTARY);
assertTrue('level 1 differs from level 7 intro', $level1Open['message'] !== $level7Open['message']);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
