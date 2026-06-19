<?php
/**
 * P1-2m — chat.php FSM entry: submit_opening / select_stance guards
 *
 * Legacy eduIsMythBustQuest ↔ eduQuestEntryMode must agree on allow/block matrix.
 *
 * Usage: php tools/edu_chat_fsm_entry_mode_parity_test.php
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
function submitOpeningAllowedLegacy(array $quest): bool
{
    return eduIsMythBustQuest($quest);
}

/** @param array<string, mixed> $quest */
function submitOpeningAllowedDerived(array $quest): bool
{
    return eduQuestEntryMode($quest) === 'open_response';
}

/** @param array<string, mixed> $quest */
function selectStanceAllowedLegacy(array $quest): bool
{
    return !eduIsMythBustQuest($quest);
}

/** @param array<string, mixed> $quest */
function selectStanceAllowedDerived(array $quest): bool
{
    return eduQuestEntryMode($quest) === 'stance_pick';
}

echo "=== chat.php FSM entry entry_mode parity (P1-2m) ===\n\n";

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
    ok("{$name} submit_opening legacy ↔ derive", submitOpeningAllowedLegacy($quest) === submitOpeningAllowedDerived($quest));
    ok("{$name} select_stance legacy ↔ derive", selectStanceAllowedLegacy($quest) === selectStanceAllowedDerived($quest));
}

echo "\n--- golden allow/block matrix ---\n";

ok('nuke submit_opening allowed', submitOpeningAllowedDerived($fixtures['nuke_myth_bust']) === true);
ok('nuke select_stance blocked', selectStanceAllowedDerived($fixtures['nuke_myth_bust']) === false);
ok('japan submit_opening blocked', submitOpeningAllowedDerived($fixtures['japan_dec']) === false);
ok('japan select_stance allowed', selectStanceAllowedDerived($fixtures['japan_dec']) === true);
ok('iran submit_opening blocked', submitOpeningAllowedDerived($fixtures['iran_convergent']) === false);
ok('iran select_stance allowed', selectStanceAllowedDerived($fixtures['iran_convergent']) === true);
ok('empty frame defaults stance_pick', eduQuestEntryMode($fixtures['empty_frame']) === 'stance_pick');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
