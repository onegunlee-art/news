<?php
/**
 * myth_bust 코치 반복 제거 회귀 (LLM 없음)
 *
 * Usage: php tools/edu_myth_bust_coach_regression_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';

use Services\Edu\Agents\SocraticCoach;

eduLoadAgents();

class FakeCoachLlm
{
    public function chat(string $system, array $messages, int $maxTokens = 256, float $temp = 0.7): array
    {
        return ['content' => '왜 그렇게 생각해?'];
    }

    public function haiku(string $system, array $messages, int $maxTokens = 256): array
    {
        return ['content' => '{}'];
    }
}

$coach = new SocraticCoach(new FakeCoachLlm());
$quest = eduNuke630QuestFixture();
$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

echo "=== myth_bust coach anti-repeat regression ===\n\n";

$substantive = '핵이 있어도 드론이나 미사일 공격은 막기 어렵다고 봐. 러시아·이스라엘 사례가 그렇잖아.';
$vague = '그냥 복잡해서 잘 모르겠어요.';

assertTrue('vague detected', $coach->isVagueStudentText($vague));
assertTrue('substantive not vague', !$coach->isVagueStudentText($substantive));

$evalDeep = ['depth_score' => 4, 'needs_followup' => false];
assertTrue(
    'substantive advances (depth 4)',
    $coach->shouldAdvanceReasoningMythBust($evalDeep, $substantive, [$substantive], 0)
);
assertTrue(
    'second vague turn advances',
    $coach->shouldAdvanceReasoningMythBust(['depth_score' => 2], $vague, [$substantive, $vague], 1)
);
assertTrue(
    'followup answered advances',
    $coach->shouldAdvanceReasoningMythBust(['depth_score' => 3], '방공을 더 키워야 한다고 봐', [$substantive, '방공'], 1)
);

assertTrue(
    'generic why overlaps opening',
    $coach->questionOverlapsStudentText('왜 그렇게 생각해?', [$substantive])
);
assertTrue(
    'repeat prompt overlaps',
    $coach->questionOverlapsStudentText('방금 말한 생각을 조금 더 구체적으로 말해줄래?', [$substantive])
);
assertTrue(
    'new angle does not overlap',
    !$coach->questionOverlapsStudentText('우리나라는 어떻게 대비해야 할까?', [$substantive])
);

$dialogue = [
    ['role' => 'assistant', 'content' => 'hook', 'agent' => 'hook'],
    ['role' => 'student', 'content' => $substantive],
    ['role' => 'assistant', 'content' => '왜 그렇게 생각해?', 'agent' => 'socratic'],
];
$studentTexts = $coach->collectStudentTexts($dialogue);
assertTrue('collect student turns', $studentTexts === [$substantive]);
$coachQs = $coach->collectCoachQuestions($dialogue);
assertTrue('collect coach questions', count($coachQs) === 2);

$followup = $coach->askReasonFollowupMythBust($quest, '방공이 더 중요해', $studentTexts, $coachQs);
assertTrue(
    'overlap followup triggers advance path in chat',
    $coach->questionOverlapsStudentText($followup['question'] ?? '', $studentTexts)
);

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
