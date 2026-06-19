<?php
/**
 * P1-2j — chat.php reasoning phase: quest_frame myth_bust ↔ eduQuestEntryMode parity
 *
 * 4 decision points in reasoning block must remain byte-identical to legacy myth_bust check.
 *
 * Usage: php tools/edu_chat_reasoning_entry_mode_parity_test.php
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
function reasoningBranchLegacy(array $quest): bool
{
    return (eduQuestHammerHints($quest)['quest_frame'] ?? '') === 'myth_bust';
}

/** @param array<string, mixed> $quest */
function reasoningBranchDerived(array $quest): bool
{
    return eduQuestEntryMode($quest) === 'open_response';
}

/**
 * Mirrors chat.php reasoning block decisions (L315–346) without HTTP.
 *
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $blueprint
 * @return array{
 *   stance_for_eval: string,
 *   skip_hypothesis_update: bool,
 *   myth_bust_advance_gate: bool,
 *   myth_bust_followup_path: bool
 * }
 */
function simulateReasoningBranchDecisions(array $quest, array $blueprint, bool $useDerived): array
{
    $isOpen = $useDerived ? reasoningBranchDerived($quest) : reasoningBranchLegacy($quest);

    return [
        'stance_for_eval' => $isOpen ? 'myth_bust' : (string) ($blueprint['stance'] ?? 'pro'),
        'skip_hypothesis_update' => $isOpen,
        'myth_bust_advance_gate' => $isOpen,
        'myth_bust_followup_path' => $isOpen,
    ];
}

echo "=== chat.php reasoning entry_mode parity (P1-2j) ===\n\n";

$fixtures = [
    'japan_dec' => eduG09DecQuestFixture(),
    'iran_dec' => eduIranDecQuestFixture(),
    'nuke_myth_bust' => eduNuke630QuestFixture(),
    'iran_convergent' => [
        'quest_code' => 'Q-IRAN-FOREVER-001',
        'pro_line' => 'pro',
        'con_line' => 'con',
        'hammer_hints' => ['mode' => 'convergent', 'quest_frame' => 'decision_inquiry'],
    ],
    'empty_frame' => ['quest_code' => 'Q-EMPTY', 'hammer_hints' => []],
];

foreach ($fixtures as $name => $quest) {
    ok("{$name} legacy ↔ entry_mode open_response", reasoningBranchLegacy($quest) === reasoningBranchDerived($quest));
}

echo "\n--- 4 reasoning decision points (legacy vs derived) ---\n";

$blueprints = [
    'stance_pro' => ['stance' => 'pro', 'phase' => 'reasoning'],
    'stance_con' => ['stance' => 'con', 'phase' => 'reasoning'],
    'no_stance' => ['phase' => 'reasoning'],
];

foreach ($fixtures as $name => $quest) {
    foreach ($blueprints as $bpName => $blueprint) {
        $legacy = simulateReasoningBranchDecisions($quest, $blueprint, false);
        $derived = simulateReasoningBranchDecisions($quest, $blueprint, true);
        ok("{$name}/{$bpName} all 4 points match", $legacy === $derived);
    }
}

echo "\n--- golden branch expectations ---\n";

$nukeBp = ['phase' => 'reasoning'];
$japanBp = ['stance' => 'pro', 'phase' => 'reasoning'];
ok('nuke stance_for_eval myth_bust', simulateReasoningBranchDecisions($fixtures['nuke_myth_bust'], $nukeBp, true)['stance_for_eval'] === 'myth_bust');
ok('nuke skip hypothesis update', simulateReasoningBranchDecisions($fixtures['nuke_myth_bust'], $nukeBp, true)['skip_hypothesis_update'] === true);
ok('japan stance_for_eval pro', simulateReasoningBranchDecisions($fixtures['japan_dec'], $japanBp, true)['stance_for_eval'] === 'pro');
ok('japan not myth_bust followup path', simulateReasoningBranchDecisions($fixtures['japan_dec'], $japanBp, true)['myth_bust_followup_path'] === false);

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
