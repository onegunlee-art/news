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
assertTrue('unknown 몰라', eduCoachDetectEvasion('몰라') === 'unknown');
assertTrue('ask conclusion', eduCoachDetectEvasion('결론은 뭐야?') === 'ask_conclusion');
assertTrue('short substantive pass', eduCoachAxisStudentPass('새로 만들어야 함', null));
assertTrue('short defense pass', eduCoachAxisStudentPass('기지 방공', null));
assertTrue('unknown blocks pass', !eduCoachAxisStudentPass('모르겠어', eduCoachDetectEvasion('모르겠어')));
assertTrue('substantive not evasion', eduCoachDetectEvasion('새로 만들어야 함') === null);

$shortPass = eduCoachGuideHandleTurn(
    ['phase' => 'guide_axis', 'guide_axis_index' => 0, 'guide_axis_stall' => 0, 'guide_axis_answers' => []],
    $quest,
    '새로 만들어야 함'
);
assertTrue('short clear answer advances axis', (int) ($shortPass['blueprint']['guide_axis_index'] ?? 0) === 1);
assertNotContains('short answer not misread as unknown', $shortPass['message'], '모르겠다는');

$weakTurn = eduCoachGuideHandleTurn(
    ['phase' => 'guide_axis', 'guide_axis_index' => 0, 'guide_axis_stall' => 0, 'guide_axis_answers' => []],
    $quest,
    '음'
);
assertNotContains('filler not unknown evasion reply', $weakTurn['message'], '모르겠다는');

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

$quest150 = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE_DC_150,
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([], 150), [
        'hook_short' => 'AI 데이터센터가 전기세 폭등의 진짜 범인일까?',
    ]),
];
assertTrue('150 uses axis guide', eduQuestUsesAxisGuide($quest150));
assertTrue('150 has 3 axes', count(eduCoachGuideAxes($quest150)) === 3);
$axes150 = eduCoachGuide150Axes();
assertTrue('150 merge grid+investment', str_contains($axes150[2]['article_fact'] ?? '', '송전망'));
assertNotContains('150 axis3 no meta fact', $axes150[2]['article_fact'] ?? '', '기사는');
$open150 = eduCoachGuideHandleOpening(
    ['phase' => 'stance', 'exchange_count' => 0],
    $quest150,
    'AI 데이터센터가 전기세를 올리는 것 같아요'
);
assertContains('150 intro scale fact', $open150['message'], '애슈번');
assertNotContains('150 hook not repeated as axis q', $open150['message'], '진짜 범인일까');

$quest196 = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE_IRAN_196,
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([], 196), [
        'hook_short' => '이란 핵, 군사·외교 수단 중 하나면 해결될까?',
    ]),
];
assertTrue('196 uses axis guide', eduQuestUsesAxisGuide($quest196));
assertTrue('196 has 3 axes', count(eduCoachGuideAxes($quest196)) === 3);
$axes196 = eduCoachGuide196Axes();
assertContains('196 uranium fact', $axes196[1]['article_fact'] ?? '', '400kg');
$open196 = eduCoachGuideHandleOpening(
    ['phase' => 'stance', 'exchange_count' => 0],
    $quest196,
    '특수부대로 우라늄을 빼낼 수 있을 것 같아요'
);
assertContains('196 intro regime', $open196['message'], '모즈타바');
assertNotContains('196 hook not repeated', $open196['message'], '군사·외교 수단 중');

$quest288 = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE_YOUTH_288,
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([], 288), [
        'hook_short' => '청소년 AI 위험, 사용 시간만 보면 될까?',
    ]),
];
assertTrue('288 uses axis guide', eduQuestUsesAxisGuide($quest288));
assertTrue('288 has 3 axes', count(eduCoachGuideAxes($quest288)) === 3);
$open288 = eduCoachGuideHandleOpening(
    ['phase' => 'stance', 'exchange_count' => 0],
    $quest288,
    '청소년은 AI 사용 시간을 줄여야 해요'
);
assertContains('288 intro framing survey', $open288['message'], '2025');
assertNotContains('288 hook not repeated', $open288['message'], '사용 시간만');

$choiceOpen = eduCoachGuideChoiceMeta($open['blueprint'], $quest, $open['message']);
assertTrue('630 opening overlap is choice', ($choiceOpen['choice_question'] ?? false) === true);
assertTrue('630 opening strengthen/weaken options', ($choiceOpen['options'] ?? []) === ['강하게', '약하게']);

$questNoOverlap = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE,
    'hammer_hints' => eduCoachGuideAttachHints([]),
];
$openNoOverlap = eduCoachGuideHandleOpening(
    ['phase' => 'stance', 'exchange_count' => 0],
    $questNoOverlap,
    '핵 억지가 재래식 공격을 막는다고 봐요'
);
$choiceNoOverlap = eduCoachGuideChoiceMeta($openNoOverlap['blueprint'], $questNoOverlap, $openNoOverlap['message']);
assertTrue('630 military open core is narrative', $choiceNoOverlap === null);

$defenseIntro = eduCoachGuideIntroAxis($axes[2], 2, 3);
$choiceDefense = eduCoachGuideChoiceMeta(
    ['phase' => 'guide_axis', 'guide_axis_index' => 2],
    $quest,
    $defenseIntro
);
assertTrue('630 defense axis is choice', ($choiceDefense['choice_question'] ?? false) === true);
assertTrue('630 defense has 3 gist options', count($choiceDefense['options'] ?? []) === 3);
assertContains('630 defense option nuclear', implode('|', $choiceDefense['options'] ?? []), '핵 현대화');

$conclusionTurn = eduCoachGuideHandleTurn(
    ['phase' => 'guide_axis', 'guide_axis_index' => 2, 'guide_axis_stall' => 0, 'guide_axis_answers' => ['military' => 'a', 'norms' => 'b']],
    $quest,
    '방공에 먼저 쓸 것 같아요'
);
$choiceConclusion = eduCoachGuideChoiceMeta(
    $conclusionTurn['blueprint'],
    $quest,
    $conclusionTurn['message']
);
assertTrue('guide_conclusion is narrative not choice', $choiceConclusion === null);

$unknownTurn = eduCoachGuideHandleTurn(
    ['phase' => 'guide_axis', 'guide_axis_index' => 0, 'guide_axis_stall' => 0, 'guide_axis_answers' => []],
    $quest,
    '모르겠어'
);
$choiceUnknown = eduCoachGuideChoiceMeta(
    $unknownTurn['blueprint'],
    $quest,
    $unknownTurn['message']
);
assertTrue('unknown evasion ternary stays narrative', $choiceUnknown === null);

$staleAxes = eduCoachGuide630Axes();
unset($staleAxes[2]['choice_options'], $staleAxes[2]['weak_choice_options']);
$staleQuest = [
    'quest_code' => EDU_COACH_GUIDE_QUEST_CODE,
    'articles' => [['news_id' => 630]],
    'hammer_hints' => array_merge(eduCoachGuideAttachHints([]), ['_guide_axes' => $staleAxes]),
];
$staleDefenseIntro = eduCoachGuideIntroAxis($staleAxes[2], 2, 3);
$choiceStale = eduCoachGuideChoiceMeta(
    ['phase' => 'guide_axis', 'guide_axis_index' => 2],
    $staleQuest,
    $staleDefenseIntro
);
assertTrue('stale DB axes merge choice_options', ($choiceStale['choice_question'] ?? false) === true);
assertTrue('stale DB axes has 3 options', count($choiceStale['options'] ?? []) === 3);
assertNotContains(
    'choice question strips inline options',
    $choiceStale['choice_question_text'] ?? '',
    '핵 현대화'
);
assertContains(
    'choice question keeps core prompt',
    $choiceStale['choice_question_text'] ?? '',
    '무엇에 먼저'
);

echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
