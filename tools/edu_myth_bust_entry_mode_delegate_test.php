<?php
/**
 * P1-2h — eduIsMythBustQuest() delegates to eduQuestEntryMode (call site 0)
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
function eduIsMythBustQuestLegacy(array $quest): bool
{
    return (eduQuestHammerHints($quest)['quest_frame'] ?? '') === 'myth_bust';
}

echo "=== eduIsMythBustQuest entry_mode delegate (P1-2h) ===\n\n";

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
        'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => ''],
    ],
    'unknown_frame' => [
        'quest_code' => 'Q-UNK',
        'hammer_hints' => ['quest_frame' => 'future_frame', 'mode' => 'convergent'],
    ],
];

foreach ($fixtures as $name => $quest) {
    $legacy = eduIsMythBustQuestLegacy($quest);
    $current = eduIsMythBustQuest($quest);
    ok("{$name} legacy ↔ delegate", $legacy === $current);
    ok("{$name} delegate ↔ open_response", $current === (eduQuestEntryMode($quest) === 'open_response'));
}

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
