<?php
/**
 * GIST EDU — QuestConfig parity vs legacy quest booleans (P1-1 gate)
 *
 * Usage: php tools/edu_quest_config_parity_test.php
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

/**
 * @param array<string, mixed> $quest
 */
function assertQuestParity(string $label, array $quest): void
{
    $cfg = eduResolveQuestConfig($quest);

    ok("{$label} myth_bust ↔ open_response", eduIsMythBustQuest($quest) === ($cfg['entry_mode'] === 'open_response'));
    ok("{$label} decision_inquiry ↔ decision coach", eduIsDecisionInquiryQuest($quest) === ($cfg['coach_profile'] === 'decision'));
    ok("{$label} convergent ↔ hammer_mode", eduIsConvergentQuest($quest) === ($cfg['hammer_mode'] === 'convergent'));
    ok("{$label} open coach ↔ myth_bust", ($cfg['coach_profile'] === 'open') === eduIsMythBustQuest($quest));
    ok("{$label} stance_pick ↔ not myth_bust", ($cfg['entry_mode'] === 'stance_pick') === !eduIsMythBustQuest($quest));

    $frame = trim((string) (eduQuestHammerHints($quest)['quest_frame'] ?? ''));
    ok("{$label} quest_frame raw preserved", ($cfg['quest_frame'] ?? '') === $frame);
}

echo "=== QuestConfig parity ===\n\n";

$japan = eduG09DecQuestFixture();
$nuke = eduNuke630QuestFixture();
$iranMinimal = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'hammer_hints' => ['quest_frame' => 'decision_inquiry'],
];
$iranFull = eduIranDecQuestFixture();

assertQuestParity('japan', $japan);
assertQuestParity('nuke', $nuke);
assertQuestParity('iran_minimal', $iranMinimal);
assertQuestParity('iran_full', $iranFull);

$emptyHints = ['quest_code' => 'Q-EMPTY', 'hammer_hints' => []];
assertQuestParity('empty_hints', $emptyHints);
ok('empty hints entry_mode stance_pick', eduQuestEntryMode($emptyHints) === 'stance_pick');
ok('empty hints coach default', eduQuestCoachProfile($emptyHints) === 'default');
ok('empty hints hammer adversarial', eduResolveQuestConfig($emptyHints)['hammer_mode'] === 'adversarial');

$adversarial = [
    'quest_code' => 'Q-ADV',
    'hammer_hints' => ['mode' => 'adversarial', 'quest_frame' => ''],
];
assertQuestParity('adversarial_no_frame', $adversarial);

$unknownFrame = [
    'quest_code' => 'Q-UNK',
    'hammer_hints' => ['quest_frame' => 'future_frame', 'mode' => 'convergent'],
];
assertQuestParity('unknown_frame', $unknownFrame);
ok('unknown frame coach default', eduQuestCoachProfile($unknownFrame) === 'default');

$nukeCfg = eduResolveQuestConfig($nuke);
ok('nuke hook_full passthrough', $nukeCfg['hook_full'] !== '');
ok('nuke axes count', count($nukeCfg['axes']) === 3);
ok('japan shared_conclusion passthrough', eduResolveQuestConfig($japan)['shared_conclusion'] !== '');

ok('payload japan entry_mode', (eduPublicQuestPayload($japan)['entry_mode'] ?? '') === 'stance_pick');
ok('payload nuke entry_mode', (eduPublicQuestPayload($nuke)['entry_mode'] ?? '') === 'open_response');
ok('payload iran entry_mode', (eduPublicQuestPayload($iranFull)['entry_mode'] ?? '') === 'stance_pick');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
