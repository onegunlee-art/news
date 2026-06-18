<?php
/**
 * GIST EDU — dialogue turn_id (P1-0): normalize read-only + legacy write-back guard (R7)
 *
 * Usage: php tools/edu_dialogue_turn_id_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';

$pass = 0;
$fail = 0;

function assertTrue(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        if ($detail !== '') {
            echo "  → {$detail}\n";
        }
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    if ($detail !== '') {
        echo "  → {$detail}\n";
    }
    $fail++;
}

$legacy = [
    ['role' => 'assistant', 'content' => 'hook', 'agent' => 'hook', 'at' => '2026-01-01T00:00:00+00:00'],
    ['role' => 'student', 'content' => 'stance pick', 'at' => '2026-01-01T00:01:00+00:00'],
    ['role' => 'assistant', 'content' => 'why?', 'agent' => 'socratic', 'at' => '2026-01-01T00:02:00+00:00'],
];

$session = ['dialogue_json' => $legacy];

// --- Raw load (chat/compose path) ---
$raw = eduLoadDialogue($session, false);
assertTrue('R7 raw load count', count($raw) === 3);
assertTrue(
    'R7 raw load no turn_id on legacy',
    !isset($raw[0]['turn_id']) && !isset($raw[1]['turn_id']) && !isset($raw[2]['turn_id'])
);

// --- Normalize is read-only on input ---
$legacySnapshot = json_encode($legacy);
$normalized = eduNormalizeDialogueTurns($legacy);
assertTrue('R7 normalize count', count($normalized) === 3);
assertTrue(
    'R7 normalize assigns sequential ids',
    ($normalized[0]['turn_id'] ?? '') === 't-1'
        && ($normalized[1]['turn_id'] ?? '') === 't-2'
        && ($normalized[2]['turn_id'] ?? '') === 't-3'
);
assertTrue('R7 normalize does not mutate source array', json_encode($legacy) === $legacySnapshot);
assertTrue(
    'R7 normalize does not invent phase',
    !isset($normalized[0]['phase']) && !isset($normalized[2]['phase'])
);

// --- state.php path: withTurnIds in memory only ---
$withIds = eduLoadDialogue($session, true);
assertTrue('R7 withTurnIds memory ids', ($withIds[2]['turn_id'] ?? '') === 't-3');
assertTrue(
    'R7 session dialogue_json still raw after withTurnIds load',
    !isset($session['dialogue_json'][0]['turn_id'])
);

// --- Append: new turn only gets turn_id + phase in saved payload ---
$toSave = eduLoadDialogue($session, false);
$toSave = eduAppendDialogue($toSave, 'student', 'because...', null, 'reasoning');
assertTrue('R7 append adds fourth turn', count($toSave) === 4);
assertTrue(
    'R7 legacy rows still without turn_id after append',
    !isset($toSave[0]['turn_id']) && !isset($toSave[1]['turn_id']) && !isset($toSave[2]['turn_id'])
);
$newTurn = $toSave[3];
assertTrue('R7 new turn has turn_id t-4', ($newTurn['turn_id'] ?? '') === 't-4');
assertTrue('R7 new turn has phase', ($newTurn['phase'] ?? '') === 'reasoning');

// --- eduDialogueNextTurnId respects existing ids ---
$withExisting = [
    ['turn_id' => 't-1', 'role' => 'student', 'content' => 'a'],
    ['turn_id' => 't-5', 'role' => 'assistant', 'content' => 'b'],
];
assertTrue('eduDialogueNextTurnId max+1', eduDialogueNextTurnId($withExisting) === 't-6');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
