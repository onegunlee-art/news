<?php
/**
 * P2 코치 axis_guide_v1 회귀 (LLM 없음)
 *
 * Usage: php tools/edu_coach_guide_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
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

$quest = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE,
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([]), [
        'hook_short' => '핵무기가 있으면 재래식 공격과 전쟁 확대를 정말 막을 수 있을까?',
    ]),
];

assertTrue('630 uses axis guide', eduQuestUsesAxisGuide($quest));
assertTrue('630 has 3 axes', count(eduCoachGuideAxes($quest)) === 3);

$axes = eduCoachGuide630Axes();
assertTrue('norms absorbs regional fact', str_contains($axes[1]['article_fact'] ?? '', '인도'));
assertNotContains('defense core_question no 유도어', $axes[2]['core_question'] ?? '', '중요하지');

assertTrue('both evasion', eduCoachDetectEvasion('둘 다 필요해') === 'both');
assertTrue('unknown evasion', eduCoachDetectEvasion('모르겠어') === 'unknown');
assertTrue('ask conclusion', eduCoachDetectEvasion('결론은 뭐야?') === 'ask_conclusion');

$bp = ['phase' => 'stance', 'exchange_count' => 0];
$open = eduCoachGuideHandleOpening($bp, $quest, '핵이 있으면 재래식 공격은 막힐 것 같아요');
assertTrue('opening -> guide_axis', ($open['blueprint']['phase'] ?? '') === 'guide_axis');
assertContains('opening intro military', $open['message'], '거미줄');
assertContains('opening overlap deepens', $open['message'], '더 따져보자');
assertNotContains('opening no repeat core q', $open['message'], '정말 막을 수 있을까');

$guardedSnippet = eduCoachSpoonfeedGuard(
    "lead\n\n{{snippet|summary}}\n2025년 6월 우크라이나\n{{/snippet}}\n\nquestion?"
);
assertContains('guard keeps snippet markers', $guardedSnippet, '{{snippet|summary}}');
assertContains('guard keeps snippet body', $guardedSnippet, '우크라이나');

$bp = $open['blueprint'];
$both = eduCoachGuideHandleTurn($bp, $quest, '둘 다 필요해');
assertNotContains('both not accepted', $both['message'], '맞아');
assertContains('both pushback', $both['message'], '1순위');

$bp = $both['blueprint'];
$passTurn = eduCoachGuideHandleTurn($bp, $quest, '우크라이나 사례 보면 핵 대신 재래식만 써서 약하게 해줘');
assertTrue('axis pass advances index', (int) ($passTurn['blueprint']['guide_axis_index'] ?? 0) === 1);

$bp = ['phase' => 'guide_axis', 'guide_axis_index' => 0, 'guide_axis_stall' => 0, 'guide_axis_answers' => []];
for ($i = 0; $i < EDU_COACH_GUIDE_STALL_ESCAPE; $i++) {
    $r = eduCoachGuideHandleTurn($bp, $quest, '모르겠어');
    $bp = $r['blueprint'];
}
assertTrue('stall escape advances axis', (int) ($bp['guide_axis_index'] ?? 0) === 1);

$guarded = eduCoachSpoonfeedGuard('정리하면 방어 투자가 핵심이네. ~중요하지?');
assertNotContains('guard strips 정리하면', $guarded, '정리하면');
assertNotContains('guard strips 유도어', $guarded, '중요하지');

assertNotContains('intro hides axis index', $open['message'], '(1/3)');
assertContains('intro uses article snippet', $open['message'], '{{snippet|summary}}');
assertNotContains('intro no raw fact label', $open['message'], '기사 fact:');

$passDefense = eduCoachGuideHandleTurn(
    ['phase' => 'guide_axis', 'guide_axis_index' => 2, 'guide_axis_stall' => 0, 'guide_axis_answers' => ['military' => 'a', 'norms' => 'b']],
    $quest,
    '방공에 먼저 쓸 것 같아요 예산이 한정돼서'
);
assertTrue('conclusion phase', ($passDefense['blueprint']['phase'] ?? '') === 'guide_conclusion');
assertNotContains('conclusion no gist', $passDefense['message'], 'the gist');
assertNotContains('conclusion no gist ko', $passDefense['message'], 'gist');
assertNotContains('conclusion no article direction', $passDefense['message'], '방어·규범');

$hook630 = '핵무기가 있으면 재래식 공격과 전쟁 확대를 정말 막을 수 있을까?';
$sideA630 = '핵무기가 있으면 재래식 공격과 전쟁 확대를 막을 수 있는가';
require_once $root . '/public/api/edu/lib/eduHingeQuestMap.php';
assertTrue('630 hook_full dedup', eduHingeBuildHookFull($hook630, $sideA630, '') === $hook630);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
