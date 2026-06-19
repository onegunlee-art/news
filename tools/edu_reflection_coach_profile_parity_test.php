<?php
/**
 * P1-2f — Reflection coach_profile derive: legacy boolean parity + fallback golden output
 *
 * Usage: php tools/edu_reflection_coach_profile_parity_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';
require_once $root . '/tools/edu_g09_decision_quest_fixture.php';
require_once $root . '/tools/edu_nuclear_axis_quest_fixture.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use Services\Edu\Agents\Reflection;

final class ReflectionFailLlm
{
    public function chat(string $system, array $messages, int $maxTokens = 500, float $temperature = 0.7): array
    {
        return ['error' => 'mock_fail'];
    }
}

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

function reflectionFallbackLines(array $quest, array $blueprint, string $stance = 'con'): array
{
    $reflection = new Reflection(new ReflectionFailLlm());
    $result = $reflection->summarize(
        $stance,
        '테스트 이유입니다.',
        '반론 텍스트',
        '재답변 텍스트',
        $stance,
        $quest,
        $blueprint
    );

    return $result['summary_lines'] ?? [];
}

echo "=== Reflection coach_profile parity (P1-2f) ===\n\n";

$fixtures = [
    'japan' => eduG09DecQuestFixture(),
    'iran_dec' => eduIranDecQuestFixture(),
    'nuke' => eduNuke630QuestFixture(),
    'iran_minimal' => [
        'quest_code' => 'Q-IRAN-FOREVER-001',
        'hammer_hints' => ['quest_frame' => 'decision_inquiry', 'mode' => 'convergent'],
    ],
    'empty_frame' => ['quest_code' => 'Q-EMPTY', 'hammer_hints' => []],
    'adversarial' => [
        'pro_line' => '찬성',
        'con_line' => '반대',
        'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => 'adversarial'],
    ],
];

foreach ($fixtures as $name => $quest) {
    $legacy = (eduQuestHammerHints($quest)['quest_frame'] ?? '') === 'decision_inquiry';
    $derived = eduQuestCoachProfile($quest) === 'decision';
    ok("{$name} legacy ↔ coach_profile decision", $legacy === $derived);
}

echo "\n--- fallback golden (LLM off, deterministic) ---\n";

$iranLines = reflectionFallbackLines(eduIranDecQuestFixture(), ['stance' => 'con', 'final_stance' => 'con']);
ok('iran fallback L1 exact', ($iranLines[0] ?? '') === '너는 군대를 보내거나, 다른 방법이 나았다고 본 입장 쪽이었어');
ok('iran fallback L2 exact', ($iranLines[1] ?? '') === '다른 측면의 반론을 들어봤어');
ok('iran fallback L3 exact', ($iranLines[2] ?? '') === '너는 군대를 보내거나, 다른 방법이 나았다고 본 입장을 지켰어');

$g09Lines = reflectionFallbackLines(
    eduG09DecQuestFixture(),
    ['stance' => 'con', 'final_stance' => 'con', 'student_axis' => 'tech']
);
ok('g09 fallback L1 exact', ($g09Lines[0] ?? '') === '너는 주변 나라·대만 위험 관점으로, 그 결정이 너무 과하거나 위험하다고 본 입장 쪽이었어');
ok('g09 fallback L3 exact', ($g09Lines[2] ?? '') === '너는 그 결정이 너무 과하거나 위험하다고 본 입장을 지켰어');

$advQuest = $fixtures['adversarial'];
$advLines = reflectionFallbackLines($advQuest, ['stance' => 'pro', 'final_stance' => 'pro'], 'pro');
ok('adversarial fallback L1 exact', ($advLines[0] ?? '') === '처음엔 찬성이었어');
ok('adversarial fallback L3 exact', ($advLines[2] ?? '') === '신념이 더 단단해졌어');

$nukeLines = reflectionFallbackLines($fixtures['nuke'], ['stance' => 'pro', 'final_stance' => 'pro'], 'pro');
ok('nuke not decision path L1', ($nukeLines[0] ?? '') === '처음엔 찬성이었어');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
