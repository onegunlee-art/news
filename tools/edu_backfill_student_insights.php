<?php
/**
 * P2-B 2단계 — completed 세션 구조 진단 → edu_student_insights 백필 (멱등)
 *
 * Usage:
 *   php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630 --dry-run
 *   php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630
 *   php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630 --student-id=UUID
 *   php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630 --live
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$questCode = 'Q-AUTO-NUKE-630';
$studentId = '';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$useLive = in_array('--live', $argv ?? [], true);

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--quest-code=')) {
        $questCode = trim(substr($arg, 13));
    }
    if (str_starts_with($arg, '--student-id=')) {
        $studentId = trim(substr($arg, 13));
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

$quests = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
if (empty($quests[0]['id'])) {
    fwrite(STDERR, "Quest not found: {$questCode}\n");
    exit(1);
}
$questId = (string) $quests[0]['id'];
$quest = $quests[0];

$query = 'quest_id=eq.' . $questId
    . '&stage=eq.completed'
    . '&completed_at=not.is.null'
    . '&order=completed_at.asc';
if ($studentId !== '') {
    $query .= '&student_id=eq.' . $studentId;
}

$sessions = $supabase->select('edu_quest_sessions', $query, 500) ?? [];
$llm = $useLive ? eduLlm() : null;

echo "=== edu_student_insights backfill ===\n";
echo "quest_code={$questCode} sessions=" . count($sessions) . ' dry_run=' . ($dryRun ? 'yes' : 'no') . ' live=' . ($useLive ? 'yes' : 'no') . "\n\n";

$saved = 0;
$skipped = 0;
$failed = 0;

foreach ($sessions as $session) {
    $sid = (string) ($session['id'] ?? '');
    $sidStudent = (string) ($session['student_id'] ?? '');
    if ($sid === '') {
        $failed++;
        continue;
    }

    if (eduStructureInsightExists($supabase, $sid)) {
        $skipped++;
        echo "skip existing session={$sid}\n";
        continue;
    }

    if ($dryRun) {
        echo "would save session={$sid} student={$sidStudent}\n";
        $saved++;
        continue;
    }

    $row = eduSaveStructureInsight($supabase, $session, $quest, $llm);
    if ($row === null) {
        $failed++;
        echo "FAIL session={$sid} err=" . $supabase->getLastError() . "\n";
        continue;
    }

    $saved++;
    $axes = (int) ($row['axes_engaged_count'] ?? 0) . '/' . (int) ($row['axes_total'] ?? 0);
    $at = (string) ($row['diagnosed_at'] ?? '');
    echo "saved session={$sid} student={$sidStudent} axes={$axes} tension="
        . ($row['tension_engaged'] ?? '') . " at={$at}\n";
}

echo "\nDone: saved={$saved} skipped={$skipped} failed={$failed}\n";
exit($failed > 0 ? 1 : 0);
