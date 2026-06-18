<?php
/**
 * Abandon all in-progress quest sessions (P1-0 legacy reset).
 *
 * Marks blueprint.abandoned_at, sets stage=completed + completed_at=null so
 * eduSessionStageFilterResumable() no longer returns them. Dialogue kept for audit.
 *
 * Optional: run database/migrations/edu_session_abandoned.sql then switch to stage=abandoned.
 *
 * Usage:
 *   php tools/edu_abandon_in_progress_sessions.php --dry-run
 *   php tools/edu_abandon_in_progress_sessions.php --apply
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;
if (in_array('--dry-run', $argv ?? [], true)) {
    $dryRun = true;
}
$reason = 'p1_0_legacy_reset';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--reason=')) {
        $reason = substr($arg, 9);
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

$filter = eduSessionStageFilterResumable();
$sessions = $supabase->select(
    'edu_quest_sessions',
    $filter . '&select=id,student_id,stage,updated_at,dialogue_json,blueprint_json&order=updated_at.desc',
    500
);
if ($sessions === null) {
    fwrite(STDERR, 'Failed to load sessions: ' . $supabase->getLastError() . "\n");
    exit(1);
}

$count = count($sessions);
echo "=== Abandon in-progress sessions ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo "reason: {$reason}\n";
echo "candidates: {$count}\n\n";

$legacyDialogue = 0;
foreach ($sessions as $row) {
    $dj = $row['dialogue_json'] ?? [];
    if (is_string($dj)) {
        $dj = json_decode($dj, true) ?: [];
    }
    $hasLegacy = false;
    foreach ((array) $dj as $turn) {
        if (is_array($turn) && trim((string) ($turn['turn_id'] ?? '')) === '') {
            $hasLegacy = true;
            break;
        }
    }
    if ($hasLegacy) {
        $legacyDialogue++;
    }
    echo '  ' . ($row['id'] ?? '') . ' stage=' . ($row['stage'] ?? '?') . ' legacy_dialogue=' . ($hasLegacy ? 'y' : 'n') . "\n";
}

echo "\nlegacy dialogue sessions: {$legacyDialogue}\n";

if ($dryRun) {
    echo "\nDry-run only. Re-run with --apply to abandon.\n";
    exit(0);
}

if ($count === 0) {
    echo "\nNothing to abandon.\n";
    exit(0);
}

$now = date('c');
$ok = 0;
foreach ($sessions as $row) {
    $id = (string) ($row['id'] ?? '');
    if ($id === '') {
        continue;
    }
    $bp = eduLoadBlueprint($row);
    $bp['abandoned_at'] = $now;
    $bp['abandoned_reason'] = $reason;
    $updated = $supabase->update('edu_quest_sessions', 'id=eq.' . $id, [
        'stage' => 'completed',
        'completed_at' => null,
        'blueprint_json' => $bp,
        'updated_at' => $now,
    ]);
    if ($updated === null) {
        fwrite(STDERR, "Failed id={$id}: " . $supabase->getLastError() . "\n");
        exit(1);
    }
    $ok++;
}

$remaining = $supabase->select('edu_quest_sessions', $filter . '&select=id', 5);
$remainCount = is_array($remaining) ? count($remaining) : -1;

echo "\nAbandoned: {$ok} sessions\n";
echo "remaining resumable (sample): {$remainCount}\n";
echo $remainCount === 0 ? "PASS: no in-progress sessions left\n" : "WARN: some in-progress remain\n";
exit($remainCount === 0 ? 0 : 1);
