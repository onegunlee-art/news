<?php
/**
 * Q-AUTO-NUKE-630 hammer_hints.coach_mode → narrative_bridge_v1
 * Usage: php tools/edu_patch_630_narrative_mode.php [--dry-run] [--v1|--v2]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeBridge.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeV2.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$mode = EDU_NARRATIVE_BRIDGE_MODE;
if (in_array('--v2', $argv ?? [], true)) {
    $mode = EDU_NARRATIVE_V2_MODE;
} elseif (in_array('--v1', $argv ?? [], true)) {
    $mode = EDU_NARRATIVE_BRIDGE_MODE;
}
$supabase = eduSupabase();

$rows = $supabase->select('edu_daily_quests', 'quest_code=eq.Q-AUTO-NUKE-630', 1) ?? [];
$quest = $rows[0] ?? null;
if ($quest === null) {
    fwrite(STDERR, "Q-AUTO-NUKE-630 not found\n");
    exit(1);
}

$hints = $quest['hammer_hints'] ?? [];
if (is_string($hints)) {
    $hints = json_decode($hints, true) ?: [];
}
if (!is_array($hints)) {
    $hints = [];
}

$before = (string) ($hints['coach_mode'] ?? '');
$hints['coach_mode'] = $mode;

echo "quest_id={$quest['id']}\n";
echo "coach_mode: {$before} → {$mode}\n";

if ($dryRun) {
    echo "DRY RUN — no write\n";
    exit(0);
}

$result = $supabase->update('edu_daily_quests', 'id=eq.' . ($quest['id'] ?? ''), [
    'hammer_hints' => $hints,
]);
if ($result === null) {
    fwrite(STDERR, 'Update failed: ' . $supabase->getLastError() . "\n");
    exit(1);
}

$verify = $supabase->select('edu_daily_quests', 'id=eq.' . ($quest['id'] ?? ''), 1)[0] ?? [];
$merged = eduQuestHammerHints($verify);
echo 'verified coach_mode=' . ($merged['coach_mode'] ?? 'NULL') . "\n";
echo 'eduQuestUsesNarrativeV2=' . (eduQuestUsesNarrativeV2($verify) ? 'true' : 'false') . "\n";
echo 'eduQuestUsesNarrativeBridge=' . (eduQuestUsesNarrativeBridge($verify) ? 'true' : 'false') . "\n";
echo "OK\n";
