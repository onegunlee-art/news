<?php
/**
 * P2-B 2단계 — 학생별 구조 진단 이력 조회 (미팅 데모용, 내부 CLI)
 *
 * Usage:
 *   php tools/edu_structure_insights_list.php --student-id=UUID
 *   php tools/edu_structure_insights_list.php --display-name=이원근
 *   php tools/edu_structure_insights_list.php --student-id=UUID --json
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';

$studentId = '';
$displayName = '';
$asJson = in_array('--json', $argv ?? [], true);
$limit = 50;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--student-id=')) {
        $studentId = trim(substr($arg, 13));
    }
    if (str_starts_with($arg, '--display-name=')) {
        $displayName = trim(substr($arg, 15));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(200, (int) substr($arg, 8)));
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured.\n");
    exit(1);
}

if ($studentId === '' && $displayName !== '') {
    $pattern = '*'. rawurlencode($displayName) . '*';
    $students = $supabase->select(
        'edu_students',
        'display_name=ilike.' . $pattern . '&select=id,display_name&order=updated_at.desc',
        5
    ) ?? [];
    if ($students === []) {
        fwrite(STDERR, "No student matching display-name: {$displayName}\n");
        exit(1);
    }
    if (count($students) > 1) {
        echo "Multiple students matched — use --student-id:\n";
        foreach ($students as $s) {
            echo '  ' . ($s['id'] ?? '') . '  ' . ($s['display_name'] ?? '') . "\n";
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

$rows = eduListStudentInsights($supabase, $studentId, $limit);

if ($asJson) {
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($rows === []) {
    echo "No insights for student {$studentId}\n";
    exit(0);
}

echo "=== structure insights (time asc) count=" . count($rows) . " ===\n\n";
$i = 0;
foreach ($rows as $row) {
    $i++;
    $at = substr((string) ($row['diagnosed_at'] ?? ''), 0, 10);
    $qc = (string) ($row['quest_code'] ?? '');
    $axes = (int) ($row['axes_engaged_count'] ?? 0) . '/' . (int) ($row['axes_total'] ?? 0);
    $tension = (string) ($row['tension_engaged'] ?? '');
    $clarity = (string) ($row['conclusion_clarity'] ?? '');
    $evidence = (string) ($row['evidence_linked'] ?? '');
    echo "#{$i}  {$at}  {$qc}  axes {$axes}  tension={$tension}  clarity={$clarity}  evidence={$evidence}\n";
    $note = trim((string) ($row['structure_note'] ?? ''));
    if ($note !== '') {
        echo '    ' . str_replace("\n", ' ', mb_substr($note, 0, 120)) . "\n";
    }
}
