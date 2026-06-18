<?php
/**
 * P1-2i — eduHammerPayload / eduStudentStanceLabel decision branch via coach_profile (call site 0)
 *
 * Usage: php tools/edu_hammer_stance_coach_profile_parity_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

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

/** @param array<string, mixed> $quest */
function hammerDecisionBranchLegacy(array $quest): bool
{
    $hints = eduQuestHammerHints($quest);

    return ($hints['quest_frame'] ?? '') === 'decision_inquiry';
}

/** @param array<string, mixed> $quest */
function stanceLabelLegacy(string $stance, array $quest): string
{
    if (eduIsDecisionInquiryQuest($quest)) {
        return eduDecisionStanceLabel($stance, $quest);
    }

    return $stance === 'pro' ? '찬성' : '반대';
}

/** @param array<string, mixed> $quest */
function hammerPayloadLegacy(array $quest, string $stance): array
{
    $hints = eduQuestHammerHints($quest);
    $mode = $hints['mode'] ?? 'adversarial';
    if ($mode !== 'convergent') {
        return eduHammerPayload($quest, $stance);
    }

    $shared = (string) ($hints['shared_conclusion'] ?? '');
    $isDecisionInquiry = hammerDecisionBranchLegacy($quest);
    if ($isDecisionInquiry) {
        $reflectionQuestion = '네가 본 관점을 한 줄로 정리해볼래? 그 선택, 너는 어떻게 봐?';
    } elseif ($shared !== '') {
        $reflectionQuestion = "네가 고른 근거 층위를 한 줄로 정리해볼래? 그래도 \"{$shared}\"에 동의해?";
    } else {
        $reflectionQuestion = '네 근거 층위를 한 줄로 정리해볼래?';
    }

    return [
        'mode' => 'convergent',
        'stance' => $stance,
        'shared_conclusion' => $shared,
        'axes' => $hints['axes'] ?? [],
        'reflection_question' => $reflectionQuestion,
    ];
}

echo "=== eduHammerPayload / eduStudentStanceLabel coach_profile (P1-2i) ===\n\n";

$japan = eduG09DecQuestFixture();
$iran = eduIranDecQuestFixture();
$nuke = eduNuke630QuestFixture();
$adv = [
    'pro_line' => '찬성 라인',
    'con_line' => '반대 라인',
    'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => 'adversarial'],
];

$fixtures = [
    'japan' => $japan,
    'iran_dec' => $iran,
    'nuke' => $nuke,
    'empty_frame' => ['quest_code' => 'Q-EMPTY', 'hammer_hints' => ['mode' => 'convergent']],
    'adversarial' => $adv,
];

foreach ($fixtures as $name => $quest) {
    $legacyBranch = hammerDecisionBranchLegacy($quest);
    $derivedBranch = eduQuestCoachProfile($quest) === 'decision';
    ok("{$name} hammer decision branch legacy ↔ coach_profile", $legacyBranch === $derivedBranch);
}

echo "\n--- eduStudentStanceLabel golden ---\n";

foreach (['con', 'pro'] as $stance) {
    ok("japan {$stance} stance label legacy", eduStudentStanceLabel($stance, $japan) === stanceLabelLegacy($stance, $japan));
    ok("iran {$stance} stance label legacy", eduStudentStanceLabel($stance, $iran) === stanceLabelLegacy($stance, $iran));
}
ok('nuke pro stance 찬성', eduStudentStanceLabel('pro', $nuke) === '찬성');
ok('adv pro stance 찬성', eduStudentStanceLabel('pro', $adv) === '찬성');
ok(
    'japan con label exact',
    eduStudentStanceLabel('con', $japan) === '그 결정이 너무 과하거나 위험하다고 본 입장'
);
ok(
    'iran con label exact',
    eduStudentStanceLabel('con', $iran) === '군대를 보내거나, 다른 방법이 나았다고 본 입장'
);

echo "\n--- eduHammerPayload convergent reflection_question golden ---\n";

$decisionQuestion = '네가 본 관점을 한 줄로 정리해볼래? 그 선택, 너는 어떻게 봐?';

$japanHammer = eduHammerPayload($japan, 'con');
ok('japan hammer legacy parity', ($japanHammer['reflection_question'] ?? '') === (hammerPayloadLegacy($japan, 'con')['reflection_question'] ?? ''));
ok('japan hammer decision question exact', ($japanHammer['reflection_question'] ?? '') === $decisionQuestion);

$iranHammer = eduHammerPayload($iran, 'con');
ok('iran hammer legacy parity', ($iranHammer['reflection_question'] ?? '') === (hammerPayloadLegacy($iran, 'con')['reflection_question'] ?? ''));
ok('iran hammer decision question exact', ($iranHammer['reflection_question'] ?? '') === $decisionQuestion);

$nukeHammer = eduHammerPayload($nuke, 'pro');
$nukeLegacy = hammerPayloadLegacy($nuke, 'pro');
ok('nuke hammer legacy parity', ($nukeHammer['reflection_question'] ?? '') === ($nukeLegacy['reflection_question'] ?? ''));
ok('nuke hammer uses shared_conclusion path', str_contains($nukeHammer['reflection_question'] ?? '', '동의해'));
ok('nuke hammer not decision question', ($nukeHammer['reflection_question'] ?? '') !== $decisionQuestion);

$advHammer = eduHammerPayload($adv, 'pro');
ok('adversarial hammer has counter_line', ($advHammer['counter_line'] ?? '') === '반대 라인');
ok('adversarial hammer reflection 찬성', str_contains($advHammer['reflection_question'] ?? '', '찬성'));

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
