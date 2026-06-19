<?php
/**
 * P1-3 — quest_frame myth_bust ↔ eduQuestEntryMode (replaces P1-2h delegate test)
 *
 * Usage: php tools/edu_myth_bust_entry_mode_delegate_test.php
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
function legacyMythBustFrame(array $quest): bool
{
    return (eduQuestHammerHints($quest)['quest_frame'] ?? '') === 'myth_bust';
}

echo "=== quest_frame myth_bust ↔ entry_mode open_response (P1-3) ===\n\n";

$fixtures = [
    'japan' => eduG09DecQuestFixture(),
    'nuke' => eduNuke630QuestFixture(),
    'iran_dec' => eduIranDecQuestFixture(),
    'iran_minimal' => [
        'quest_code' => 'Q-IRAN-FOREVER-001',
        'hammer_hints' => ['quest_frame' => 'decision_inquiry'],
    ],
    'empty_hints' => ['quest_code' => 'Q-EMPTY', 'hammer_hints' => []],
    'adversarial' => [
        'quest_code' => 'Q-ADV',
        'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => 'adversarial'],
    ],
    'unknown_frame' => [
        'quest_code' => 'Q-UNK',
        'hammer_hints' => ['quest_frame' => 'unknown_xyz'],
    ],
];

foreach ($fixtures as $name => $quest) {
    $frameOpen = legacyMythBustFrame($quest);
    $entryOpen = eduQuestEntryMode($quest) === 'open_response';
    ok("{$name} frame ↔ entry_mode open_response", $frameOpen === $entryOpen);
}

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
