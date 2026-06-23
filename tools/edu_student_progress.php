<?php
/**
 * 게임화 조각 2 — 학생 XP/스트릭/티어 CLI
 *
 * Usage:
 *   php tools/edu_student_progress.php --display-name=이원근
 *   php tools/edu_student_progress.php --student-id=UUID
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduTier.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';

$studentId = '';
$displayName = '';

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--student-id=')) {
        $studentId = trim(substr($arg, 13));
    }
    if (str_starts_with($arg, '--display-name=')) {
        $displayName = trim(substr($arg, 15));
    }
}

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

if ($studentId === '' && $displayName !== '') {
    $students = $sb->select(
        'edu_students',
        'display_name=ilike.*' . rawurlencode($displayName) . '*&select=id,display_name',
        5
    ) ?? [];
    if ($students === []) {
        fwrite(STDERR, "No student: {$displayName}\n");
        exit(1);
    }
    if (count($students) > 1) {
        fwrite(STDERR, "Multiple matches — use --student-id\n");
        foreach ($students as $s) {
            fwrite(STDERR, '  ' . ($s['id'] ?? '') . ' ' . ($s['display_name'] ?? '') . "\n");
        }
        exit(2);
    }
    $studentId = (string) ($students[0]['id'] ?? '');
    echo 'student: ' . ($students[0]['display_name'] ?? '') . "\n\n";
}

if ($studentId === '') {
    fwrite(STDERR, "Usage: --display-name=NAME or --student-id=UUID\n");
    exit(1);
}

$tier = eduFetchTierRow($studentId);
$payload = eduTierProgressPayload($tier);
$insights = eduListStudentInsights($sb, $studentId, 5);
$xpFromInsights = 0;
foreach ($insights as $row) {
    if (isset($row['xp_earned'])) {
        $xpFromInsights += (int) $row['xp_earned'];
    }
}

echo "=== progress ===\n";
echo 'tier: ' . ($payload['tier_label_ko'] ?? '') . ' (' . ($payload['tier_id'] ?? '') . ")\n";
echo 'xp_current: ' . ($payload['xp_current'] ?? 0) . "\n";
echo 'streak_days: ' . ($payload['streak_days'] ?? 0) . "\n";
echo 'streak_freeze: ' . ($payload['streak_freeze_available'] ?? '?') . "\n";
echo 'last_quest_date: ' . ($tier['last_quest_date'] ?? 'null') . "\n";
echo 'recent insights: ' . count($insights) . " (xp_earned sum last 5: {$xpFromInsights})\n";

if ($insights !== []) {
    echo "\n--- last sessions ---\n";
    foreach (array_reverse($insights) as $row) {
        echo '  ' . ($row['quest_code'] ?? '') . ' xp=' . ($row['xp_earned'] ?? '?')
            . ' axes=' . ($row['axes_engaged_count'] ?? '?') . '/' . ($row['axes_total'] ?? '?')
            . ' tension=' . ($row['tension_engaged'] ?? '') . "\n";
    }
}
