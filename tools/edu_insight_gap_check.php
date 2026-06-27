<?php
/**
 * 완주 세션 vs insight 저장 갭 확인 (Cursor/내부 검증용)
 *
 * Usage:
 *   php tools/edu_insight_gap_check.php --display-name=이원근
 *   php tools/edu_insight_gap_check.php --student-id=UUID [--json]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';

$studentId = '';
$displayName = '';
$asJson = in_array('--json', $argv ?? [], true);
$limit = 15;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--student-id=')) {
        $studentId = trim(substr($arg, 13));
    }
    if (str_starts_with($arg, '--display-name=')) {
        $displayName = trim(substr($arg, 15));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(50, (int) substr($arg, 8)));
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

if ($studentId === '' && $displayName !== '') {
    $pattern = '*' . rawurlencode($displayName) . '*';
    $students = $supabase->select(
        'edu_students',
        'display_name=ilike.' . $pattern . '&select=id,display_name&order=last_active_at.desc',
        5
    ) ?? [];
    if ($students === []) {
        fwrite(STDERR, "No student matching display-name: {$displayName}\n");
        exit(1);
    }
    if (count($students) > 1) {
        fwrite(STDERR, "Multiple students matched — use --student-id\n");
        foreach ($students as $s) {
            fwrite(STDERR, '  ' . ($s['id'] ?? '') . '  ' . ($s['display_name'] ?? '') . "\n");
        }
        exit(2);
    }
    $studentId = (string) ($students[0]['id'] ?? '');
    echo 'student: ' . ($students[0]['display_name'] ?? '') . " ({$studentId})\n\n";
}

if ($studentId === '') {
    fwrite(STDERR, "Usage: --student-id=UUID or --display-name=NAME\n");
    exit(1);
}

$insights = eduListStudentInsights($supabase, $studentId, 200);
$insightBySession = [];
foreach ($insights as $row) {
    $sid = (string) ($row['session_id'] ?? '');
    if ($sid !== '') {
        $insightBySession[$sid] = $row;
    }
}

$sessions = $supabase->select(
    'edu_quest_sessions',
    'student_id=eq.' . $studentId . '&stage=eq.completed&completed_at=not.is.null&order=completed_at.desc&select=id,quest_id,stage,completed_at,updated_at',
    $limit
) ?? [];

$quests = [];
$out = [
    'student_id' => $studentId,
    'insight_count' => count($insights),
    'recent_completed' => [],
    'missing_insight_sessions' => [],
    'latest_insight' => $insights !== [] ? end($insights) : null,
];

foreach ($sessions as $sess) {
    $sid = (string) ($sess['id'] ?? '');
    $qid = (string) ($sess['quest_id'] ?? '');
    if (!isset($quests[$qid])) {
        $qrows = $supabase->select('edu_daily_quests', 'id=eq.' . $qid . '&select=quest_code', 1);
        $quests[$qid] = (string) ($qrows[0]['quest_code'] ?? '?');
    }
    $insight = $insightBySession[$sid] ?? null;
    $entry = [
        'session_id' => $sid,
        'quest_code' => $quests[$qid],
        'completed_at' => $sess['completed_at'] ?? null,
        'has_insight' => $insight !== null,
        'diagnose_mode' => $insight['diagnose_mode'] ?? null,
        'diagnose_version' => $insight['diagnose_version'] ?? null,
        'exploration_depth_level' => $insight['exploration_depth_level'] ?? null,
    ];
    $out['recent_completed'][] = $entry;
    if ($insight === null) {
        $out['missing_insight_sessions'][] = $sid;
    }
}

if ($asJson) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

echo "=== insight gap check ===\n";
echo 'insights total: ' . count($insights) . "\n";
if ($insights !== []) {
    $last = end($insights);
    $i = count($insights);
    $level = $last['exploration_depth_level'] ?? '-';
    echo "#{$i} latest: " . substr((string) ($last['diagnosed_at'] ?? ''), 0, 10);
    echo '  ' . ($last['quest_code'] ?? '');
    echo '  mode=' . ($last['diagnose_mode'] ?? '');
    echo '  L=' . ($level !== null ? $level : '-');
    echo '  ver=' . ($last['diagnose_version'] ?? '') . "\n";
}
echo "\nRecent completed (newest first):\n";
foreach ($out['recent_completed'] as $row) {
    $flag = $row['has_insight'] ? 'OK' : 'MISSING';
    echo "  [{$flag}] " . substr((string) ($row['completed_at'] ?? ''), 0, 19);
    echo '  ' . ($row['quest_code'] ?? '');
    echo '  ' . substr($row['session_id'], 0, 8) . '…';
    if ($row['has_insight']) {
        echo '  mode=' . ($row['diagnose_mode'] ?? '');
        echo '  ver=' . ($row['diagnose_version'] ?? '');
    }
    echo "\n";
}

if ($out['missing_insight_sessions'] !== []) {
    echo "\nWARN: " . count($out['missing_insight_sessions']) . " completed session(s) without insight row.\n";
    echo "Likely cause: compose already_completed early return before insight backfill.\n";
} else {
    echo "\nAll recent completed sessions have insight rows.\n";
}
