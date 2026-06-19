<?php
/**
 * P1-2l/n — QuestFlowChat entry_mode + stance entry action parity
 *
 * Mirrors resolveQuestEntryMode, resolveQuestFooterMode, resolveStanceEntryChatAction.
 *
 * Usage: php tools/edu_quest_flow_entry_mode_parity_test.php
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

/** @param array<string, mixed> $payload */
function resolveQuestEntryMode(array $payload): string
{
    $entryMode = (string) ($payload['entry_mode'] ?? '');
    if ($entryMode === 'open_response' || $entryMode === 'stance_pick') {
        return $entryMode;
    }

    return ($payload['quest_frame'] ?? '') === 'myth_bust' ? 'open_response' : 'stance_pick';
}

/** @param array<string, mixed> $payload */
function isOpenResponseDerived(array $payload): bool
{
    return resolveQuestEntryMode($payload) === 'open_response';
}

function resolveStanceEntryChatAction(string $entryMode): string
{
    return $entryMode === 'open_response' ? 'submit_opening' : 'select_stance';
}

/** Mirrors resolveQuestFooterMode(phase, entryMode) for stance */
function stanceFooterMode(string $entryMode): ?string
{
    return $entryMode === 'open_response' ? 'opening' : null;
}

/** Legacy myth_bust frame → open_response boolean */
function isMythBustLegacy(array $payload): bool
{
    return ($payload['quest_frame'] ?? '') === 'myth_bust';
}

echo "=== QuestFlowChat entry_mode parity (P1-2l/n) ===\n\n";

$fixtures = [
    'japan' => eduG09DecQuestFixture(),
    'iran' => eduIranDecQuestFixture(),
    'nuke' => eduNuke630QuestFixture(),
];

foreach ($fixtures as $name => $quest) {
    $payload = eduPublicQuestPayload($quest);
    $legacy = isMythBustLegacy($payload);
    $derived = isOpenResponseDerived($payload);
    ok("{$name} open_response legacy ↔ derive", $legacy === $derived);
    ok("{$name} entry_mode in payload", ($payload['entry_mode'] ?? '') !== '');
}

echo "\n--- UI branch golden ---\n";

$nukePayload = eduPublicQuestPayload($fixtures['nuke']);
$japanPayload = eduPublicQuestPayload($fixtures['japan']);
$iranPayload = eduPublicQuestPayload($fixtures['iran']);

ok('nuke open_response true', isOpenResponseDerived($nukePayload) === true);
ok('nuke entry_mode open_response', resolveQuestEntryMode($nukePayload) === 'open_response');
ok('nuke stance action submit_opening', resolveStanceEntryChatAction(resolveQuestEntryMode($nukePayload)) === 'submit_opening');
ok('nuke stance footer opening', stanceFooterMode(resolveQuestEntryMode($nukePayload)) === 'opening');
ok('japan open_response false', isOpenResponseDerived($japanPayload) === false);
ok('japan entry_mode stance_pick', resolveQuestEntryMode($japanPayload) === 'stance_pick');
ok('japan stance action select_stance', resolveStanceEntryChatAction(resolveQuestEntryMode($japanPayload)) === 'select_stance');
ok('japan stance footer null (stance buttons)', stanceFooterMode(resolveQuestEntryMode($japanPayload)) === null);
ok('iran open_response false', isOpenResponseDerived($iranPayload) === false);
ok('iran stance action select_stance', resolveStanceEntryChatAction(resolveQuestEntryMode($iranPayload)) === 'select_stance');
ok('iran stance footer null', stanceFooterMode(resolveQuestEntryMode($iranPayload)) === null);

echo "\n--- entry_mode absent fallback ---\n";

$legacyShape = [
    'quest_frame' => 'myth_bust',
    'pro_line' => 'a',
    'con_line' => 'b',
];
ok('fallback myth_bust frame', isOpenResponseDerived($legacyShape) === true);
$legacyShape['quest_frame'] = 'decision_inquiry';
ok('fallback decision frame', isOpenResponseDerived($legacyShape) === false);

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
