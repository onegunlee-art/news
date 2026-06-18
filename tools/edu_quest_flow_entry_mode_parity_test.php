<?php
/**
 * P1-2l — QuestFlowChat isOpenResponse: entry_mode derive vs quest_frame legacy parity
 *
 * Mirrors QuestFlowChat isOpenResponseQuest + resolveQuestFooterMode (behavior 0).
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
function isMythBustLegacy(array $payload): bool
{
    return ($payload['quest_frame'] ?? '') === 'myth_bust';
}

/** @param array<string, mixed> $payload */
function isOpenResponseDerived(array $payload): bool
{
    $entryMode = (string) ($payload['entry_mode'] ?? '');
    if ($entryMode === 'open_response') {
        return true;
    }
    if ($entryMode === 'stance_pick') {
        return false;
    }

    return ($payload['quest_frame'] ?? '') === 'myth_bust';
}

/** Mirrors resolveQuestFooterMode(phase, isOpenResponse) for stance */
function stanceFooterMode(bool $isOpenResponse): ?string
{
    return $isOpenResponse ? 'opening' : null;
}

echo "=== QuestFlowChat entry_mode parity (P1-2l) ===\n\n";

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
ok('nuke stance footer opening', stanceFooterMode(isOpenResponseDerived($nukePayload)) === 'opening');
ok('japan open_response false', isOpenResponseDerived($japanPayload) === false);
ok('japan stance footer null (stance buttons)', stanceFooterMode(isOpenResponseDerived($japanPayload)) === null);
ok('iran open_response false', isOpenResponseDerived($iranPayload) === false);
ok('iran stance footer null', stanceFooterMode(isOpenResponseDerived($iranPayload)) === null);

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
