<?php
/**
 * Abandon in-progress 630 (Q-AUTO-NUKE-630) sessions only — v2 pollution reset.
 *
 * Marks blueprint.abandoned_at, sets stage=completed + completed_at=null so
 * start.php creates a fresh session on next visit. Other quests untouched.
 *
 * Usage:
 *   php tools/edu_reset_630_v2_sessions.php --dry-run
 *   php tools/edu_reset_630_v2_sessions.php --apply
 *   php tools/edu_reset_630_v2_sessions.php --apply --student=이원근
 *   php tools/edu_reset_630_v2_sessions.php --apply --student=guest
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/eduCoachGuideNarrativeV2.php';

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;
if (in_array('--dry-run', $argv ?? [], true)) {
    $dryRun = true;
}

$studentFilter = '';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--student=')) {
        $studentFilter = trim(substr($arg, 10));
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

$quest = eduLoadQuestByCode(EDU_NARRATIVE_V2_QUEST_CODE);
if ($quest === null) {
    fwrite(STDERR, '630 quest not found in edu_daily_quests.' . "\n");
    exit(1);
}
$questId = (string) ($quest['id'] ?? '');
if ($questId === '') {
    fwrite(STDERR, "630 quest id missing.\n");
    exit(1);
}

$studentId = '';
if ($studentFilter !== '') {
    $students = $supabase->select(
        'edu_students',
        'display_name=ilike.' . rawurlencode($studentFilter) . '&select=id,display_name',
        5
    );
    if ($students === null || $students === []) {
        fwrite(STDERR, "Student not found: {$studentFilter}\n");
        exit(1);
    }
    if (count($students) > 1) {
        fwrite(STDERR, "Multiple students match '{$studentFilter}':\n");
        foreach ($students as $st) {
            fwrite(STDERR, '  ' . ($st['display_name'] ?? '?') . ' id=' . ($st['id'] ?? '') . "\n");
        }
        exit(1);
    }
    $studentId = (string) ($students[0]['id'] ?? '');
}

$filter = 'quest_id=eq.' . $questId . '&' . eduSessionStageFilterResumable();
if ($studentId !== '') {
    $filter .= '&student_id=eq.' . $studentId;
}
$filter .= '&select=id,student_id,stage,updated_at,dialogue_json,blueprint_json&order=updated_at.desc';

$sessions = $supabase->select('edu_quest_sessions', $filter, 200);
if ($sessions === null) {
    fwrite(STDERR, 'Failed to load sessions: ' . $supabase->getLastError() . "\n");
    exit(1);
}

echo "=== Reset 630 in-progress sessions (v2 pollution) ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'quest: ' . EDU_NARRATIVE_V2_QUEST_CODE . " id={$questId}\n";
if ($studentFilter !== '') {
    echo "student filter: {$studentFilter} id={$studentId}\n";
}
echo 'candidates: ' . count($sessions) . "\n\n";

$polluted = 0;
foreach ($sessions as $row) {
    $bp = eduLoadBlueprint($row);
    $dj = $row['dialogue_json'] ?? [];
    if (is_string($dj)) {
        $dj = json_decode($dj, true) ?: [];
    }
    $isPolluted = eduNarrativeV2SessionIsPolluted($bp, (array) $dj);
    if ($isPolluted) {
        $polluted++;
    }

    $st = $supabase->select('edu_students', 'id=eq.' . ($row['student_id'] ?? ''), 1)[0] ?? null;
    $phase = (string) ($bp['phase'] ?? '?');
    $turns = count((array) $dj);
    echo '  ' . ($row['id'] ?? '')
        . ' student=' . ($st['display_name'] ?? '?')
        . " stage=" . ($row['stage'] ?? '?')
        . " phase={$phase} dialogue={$turns}"
        . ' polluted=' . ($isPolluted ? 'y' : 'n')
        . ' updated=' . ($row['updated_at'] ?? '')
        . "\n";
}

echo "\npolluted sessions: {$polluted}\n";

if ($dryRun) {
    echo "\nDry-run only. Re-run with --apply to abandon 630 sessions.\n";
    exit(0);
}

if ($sessions === []) {
    echo "\nNothing to reset.\n";
    exit(0);
}

$now = date('c');
$reason = 'narrative_v2_pollution_reset';
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

echo "\nAbandoned: {$ok} session(s)\n";
echo "remaining 630 resumable (sample): {$remainCount}\n";
echo $remainCount === 0 ? "PASS: no in-progress 630 sessions left\n" : "WARN: some 630 in-progress remain\n";
exit($remainCount === 0 ? 0 : 1);
