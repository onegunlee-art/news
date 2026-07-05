<?php
/**
 * narrative v2 입력 성의 검증 — static test (LLM 호출 없음)
 * Usage: php tools/edu_input_quality_static_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeV2.php';

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

echo "=== edu input quality static test ===\n\n";

$nukeQuest = [
    'quest_code' => 'Q-AUTO-NUKE-630',
    'quest_title' => '핵 억지와 드론 시대',
    'hammer_hints' => [
        'coach_mode' => 'narrative_bridge_v2',
        '_hinge' => [
            'hook_student' => '핵 억지',
            'side_a' => '핵 억지력',
            'side_b' => '드론 시대',
        ],
    ],
];
$laborQuest = [
    'quest_code' => 'Q-AUTO-YOUTH-288',
    'quest_title' => '청년 노동시장 위기',
    'hammer_hints' => [
        '_hinge' => ['hook_student' => '청년 실업', 'side_a' => '일자리 부족', 'side_b' => '구조적 문제'],
    ],
];
$textNode = ['input_mode' => 'text', 'layer' => 'refine'];
$bp = ['narrative_v2_input_quality' => ['strikes' => 0, 'rot' => []]];

ok('aaaa repeat spam', !eduNarrativeV2InputQualityEvaluate('aaaa', $nukeQuest, $textNode, $bp, '질문')['pass']);
ok('jamo only', !eduNarrativeV2InputQualityEvaluate('ㅁㄴㅇㄹ', $nukeQuest, $textNode, $bp, '질문')['pass']);
ok('too short ack 응', !eduNarrativeV2InputQualityEvaluate('응', $nukeQuest, $textNode, $bp, '질문')['pass']);
ok('profanity redirect category', eduNarrativeV2InputQualityEvaluate('ㅅㅂ', $nukeQuest, $textNode, $bp, '질문')['category'] === 'profanity');

ok(' sincere unsure passes', eduNarrativeV2InputQualityEvaluate('잘 모르겠지만 핵은 무서운 것 같아', $nukeQuest, $textNode, $bp, '질문')['pass']);
ok('short valid 반대 passes', eduNarrativeV2InputQualityEvaluate('반대야, 위험하니까', $nukeQuest, $textNode, $bp, '질문')['pass']);
ok('creative metaphor passes', eduNarrativeV2InputQualityEvaluate('핵은 게임 아이템 같아', $nukeQuest, $textNode, $bp, '질문')['pass']);

$offTopic = eduNarrativeV2InputQualityEvaluate('점심 뭐 먹지', $nukeQuest, $textNode, $bp, '핵은 안전할까?');
ok('off-topic heuristic flags lunch', eduNarrativeV2InputLooksOffTopic('점심 뭐 먹지', $nukeQuest));

$nukeTopic = eduNarrativeV2InputQualityTopicLabel($nukeQuest);
$laborTopic = eduNarrativeV2InputQualityTopicLabel($laborQuest);
ok('topic label from nuke quest', str_contains($nukeTopic, '핵') || str_contains($nukeTopic, '억지'));
ok('topic label from labor quest', str_contains($laborTopic, '청년') || str_contains($laborTopic, '노동') || str_contains($laborTopic, '실업'));
ok('topic labels differ by quest', $nukeTopic !== $laborTopic);

$line1 = eduNarrativeV2InputQualityCoachLine('meaningless', $nukeQuest, 0);
$line2 = eduNarrativeV2InputQualityCoachLine('meaningless', $nukeQuest, 1);
ok('coach line rotation', $line1 !== $line2);

$offLine = eduNarrativeV2InputQualityCoachLine('off_topic', $laborQuest, 0);
ok('off_topic uses labor topic not hardcoded nuke', str_contains($offLine, $laborTopic) && !str_contains($offLine, '핵 억지'));

$script = eduNarrativeV2LoadScript($nukeQuest);
$fallback = eduNarrativeV2InputQualityLayerButtonFallback($script, 'n_refine_input', 'refine');
ok('refine layer button fallback', count($fallback) >= 2);

$bpStrikes = ['narrative_v2_input_quality' => ['strikes' => 2, 'rot' => []]];
$reject = eduNarrativeV2InputQualityRejectResponse(
    $bpStrikes,
    $nukeQuest,
    $script,
    'n_refine_input',
    ['input_mode' => 'text', 'layer' => 'refine'],
    'aaaa',
    ['pass' => false, 'category' => 'meaningless', 'strikes' => 3]
);
ok('3rd strike offers button fallback', ($reject['choices'] ?? []) !== [] && ($reject['input_mode'] ?? 'x') === '');

$utilPath = $root . '/public/api/edu/lib/eduNarrativeV2InputQuality.php';
$util = (string) file_get_contents($utilPath);
ok('no hardcoded labor topic in util', !str_contains($util, '노동시장 위기') && !str_contains($util, 'Q-AUTO-YOUTH'));
ok('no hardcoded nuke coach line template', !preg_match("/'핵 이야기'/u", $util));

echo "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
