<?php
/**
 * 조각 1-B — 150 플레이 후 빈틈 A·C 일괄 확인
 *
 * Usage:
 *   php tools/edu_session_gaps_verify.php --session=UUID
 *   php tools/edu_session_gaps_verify.php --session=UUID --live
 *   php tools/edu_session_gaps_verify.php --session=UUID --compare-student=테스트학생
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';
require_once $root . '/public/api/edu/lib/eduStructureDiagnose.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$sessionId = '';
$useLive = in_array('--live', $argv ?? [], true);
$compareName = '';

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--session=')) {
        $sessionId = trim(substr($arg, 10));
    }
    if (str_starts_with($arg, '--compare-student=')) {
        $compareName = trim(substr($arg, 18));
    }
}

if ($sessionId === '') {
    fwrite(STDERR, "Usage: php tools/edu_session_gaps_verify.php --session=UUID [--live] [--compare-student=NAME]\n");
    exit(1);
}

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$sessions = $sb->select('edu_quest_sessions', 'id=eq.' . $sessionId, 1);
$session = $sessions[0] ?? null;
if ($session === null) {
    fwrite(STDERR, "Session not found: {$sessionId}\n");
    exit(1);
}

$studentId = (string) ($session['student_id'] ?? '');
$students = $sb->select('edu_students', 'id=eq.' . $studentId, 1);
$studentName = $students[0]['display_name'] ?? $studentId;

$quests = $sb->select('edu_daily_quests', 'id=eq.' . ($session['quest_id'] ?? ''), 1);
$quest = $quests[0] ?? [];

echo "=== Session gaps verify ===\n";
echo "session: {$sessionId}\n";
echo "student: {$studentName} ({$studentId})\n";
echo "stage: " . ($session['stage'] ?? '?') . "\n";
echo "quest: " . ($quest['quest_code'] ?? '?') . "\n\n";

// A-2: realtime insight row
$insights = $sb->select('edu_student_insights', 'session_id=eq.' . $sessionId, 1);
$insight = $insights[0] ?? null;
echo "## A-2 realtime insight\n";
if ($insight === null) {
    echo "FAIL — no edu_student_insights row (compose hook or migration?)\n";
} else {
    echo "OK — insight id=" . ($insight['id'] ?? '') . "\n";
    echo "  axes: " . ($insight['axes_engaged_count'] ?? '?') . '/' . ($insight['axes_total'] ?? '?') . "\n";
    echo "  tension: " . ($insight['tension_engaged'] ?? '') . "\n";
    echo "  mode: " . ($insight['diagnose_mode'] ?? '') . "\n";
    echo "  xp_earned: " . ($insight['xp_earned'] ?? '(column pending migration)') . "\n";
}

// A-1: --live diagnose
echo "\n## A-1 live tension\n";
$llm = $useLive ? eduLlm() : null;
if (!$useLive) {
    echo "SKIP — pass --live for LLM tension check\n";
} else {
    $blueprint = eduLoadBlueprint($session);
    $dialogue = eduLoadDialogue($session, true);
    $drafts = $sb->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    $essay = trim((string) ($drafts[0]['full_text'] ?? ''));
    $diag = eduStructureDiagnoseSession($sessionId, $quest, $blueprint, $dialogue, $llm, $essay);
    $tension = (string) ($diag['tension_engaged'] ?? '');
    $mode = (string) ($diag['diagnose_mode'] ?? '');
    echo "  tension_engaged: {$tension}\n";
    echo "  diagnose_mode: {$mode}\n";
    if ($tension === '없음' && $mode === 'rule_fallback') {
        echo "WARN — still rule fallback '없음' (LLM may not have run)\n";
    } elseif ($tension === '없음') {
        echo "WARN — tension '없음'\n";
    } else {
        echo "OK — tension engaged: {$tension}\n";
    }
}

// C: multi-user separation
echo "\n## C multi-user\n";
$allForStudent = eduListStudentInsights($sb, $studentId, 10);
echo "  insights for {$studentName}: " . count($allForStudent) . " row(s)\n";
$found = false;
foreach ($allForStudent as $row) {
    if (($row['session_id'] ?? '') === $sessionId) {
        $found = true;
        break;
    }
}
echo $found ? "OK — session in this student's list only\n" : "WARN — session not in student insight list\n";

if ($compareName !== '') {
    $others = $sb->select(
        'edu_students',
        'display_name=ilike.*' . rawurlencode($compareName) . '*&select=id,display_name',
        3
    ) ?? [];
    if (count($others) !== 1) {
        echo "  compare: could not resolve single student for {$compareName}\n";
    } else {
        $oid = (string) ($others[0]['id'] ?? '');
        $otherRows = eduListStudentInsights($sb, $oid, 20);
        $leaked = false;
        foreach ($otherRows as $row) {
            if (($row['session_id'] ?? '') === $sessionId) {
                $leaked = true;
                break;
            }
        }
        echo $leaked
            ? "FAIL — session leaked into {$compareName}'s insights\n"
            : "OK — session NOT in {$compareName}'s insights (" . count($otherRows) . " rows)\n";
    }
}

echo "\nDone.\n";
