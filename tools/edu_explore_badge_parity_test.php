<?php
/**
 * P1-2k — Explore badge label: entry_mode derive vs quest_frame legacy parity
 *
 * Mirrors EduExplorePage exploreQuestBadge / frameLabel (behavior 0).
 *
 * Usage: php tools/edu_explore_badge_parity_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';
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

function frameLabelLegacy(?string $frame): ?string
{
    if ($frame === 'myth_bust') {
        return 'Myth Bust';
    }
    if ($frame === 'decision_inquiry') {
        return '결정 탐구';
    }

    return null;
}

/** @param array<string, mixed> $listItem */
function exploreBadgeDerived(array $listItem): ?string
{
    $entryMode = (string) ($listItem['entry_mode'] ?? '');
    $frame = (string) ($listItem['quest_frame'] ?? '');

    if ($entryMode === 'open_response') {
        return 'Myth Bust';
    }
    if ($entryMode === 'stance_pick') {
        if ($frame === 'decision_inquiry') {
            return '결정 탐구';
        }

        return frameLabelLegacy($frame !== '' ? $frame : null);
    }

    return frameLabelLegacy($frame !== '' ? $frame : null);
}

echo "=== Explore badge entry_mode parity (P1-2k) ===\n\n";

$fixtures = [
    'japan' => array_merge(eduG09DecQuestFixture(), ['id' => 'badge-japan']),
    'nuke' => array_merge(eduNuke630QuestFixture(), ['id' => 'badge-nuke']),
    'iran' => [
        'id' => 'badge-iran',
        'quest_code' => 'Q-IRAN-FOREVER-001',
        'quest_title' => 'Iran',
        'hammer_hints' => ['quest_frame' => 'decision_inquiry', 'mode' => 'convergent'],
    ],
    'auto_frame' => [
        'id' => 'badge-auto',
        'quest_code' => 'Q-AUTO',
        'quest_title' => 'Auto',
        'hammer_hints' => [],
    ],
];

foreach ($fixtures as $name => $quest) {
    $item = eduQuestToListItem($quest, []);
    $legacy = frameLabelLegacy($item['quest_frame'] ?? null);
    $derived = exploreBadgeDerived($item);
    ok("{$name} badge legacy ↔ derive", $legacy === $derived);
    ok("{$name} entry_mode present", ($item['entry_mode'] ?? '') !== '');
}

echo "\n--- golden badges ---\n";
ok('japan 결정 탐구', exploreBadgeDerived(eduQuestToListItem($fixtures['japan'], [])) === '결정 탐구');
ok('nuke Myth Bust', exploreBadgeDerived(eduQuestToListItem($fixtures['nuke'], [])) === 'Myth Bust');
ok('iran 결정 탐구', exploreBadgeDerived(eduQuestToListItem($fixtures['iran'], [])) === '결정 탐구');

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
