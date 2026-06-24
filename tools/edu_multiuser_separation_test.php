<?php
/**
 * 멀티유저 분리 — insight·session이 student_id 경계를 넘지 않는지 확인
 *
 * Usage:
 *   php tools/edu_multiuser_separation_test.php
 *   php tools/edu_multiuser_separation_test.php --student-a=이원근 --student-b=테스트학생
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduStudentInsights.php';

$studentAName = '이원근';
$studentBName = '테스트학생';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--student-a=')) {
        $studentAName = trim(substr($arg, 12));
    }
    if (str_starts_with($arg, '--student-b=')) {
        $studentBName = trim(substr($arg, 12));
    }
}

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

function resolveStudent($sb, string $name): ?array
{
    $rows = $sb->select(
        'edu_students',
        'display_name=ilike.*' . rawurlencode($name) . '*&select=id,display_name,status',
        5
    ) ?? [];
    if (count($rows) !== 1) {
        return null;
    }

    return $rows[0];
}

$pass = 0;
$fail = 0;

function check(bool $ok, string $label): void
{
    global $pass, $fail;
    if ($ok) {
        echo "PASS {$label}\n";
        $pass++;
    } else {
        echo "FAIL {$label}\n";
        $fail++;
    }
}

$a = resolveStudent($sb, $studentAName);
$b = resolveStudent($sb, $studentBName);

echo "=== Multi-user separation ===\n";
if ($a === null || $b === null) {
    echo "WARN: need exactly one match per student (A={$studentAName}, B={$studentBName})\n";
    echo "  A: " . ($a === null ? 'not found' : ($a['display_name'] ?? '')) . "\n";
    echo "  B: " . ($b === null ? 'not found' : ($b['display_name'] ?? '')) . "\n";
    exit(2);
}

$aid = (string) ($a['id'] ?? '');
$bid = (string) ($b['id'] ?? '');
echo "A: {$a['display_name']} ({$aid})\n";
echo "B: {$b['display_name']} ({$bid})\n\n";

$insightsA = eduListStudentInsights($sb, $aid, 30);
$insightsB = eduListStudentInsights($sb, $bid, 30);

check($aid !== $bid, 'two distinct student ids');

$sessionIdsA = array_values(array_unique(array_filter(array_map(
    static fn (array $row) => (string) ($row['session_id'] ?? ''),
    $insightsA
))));
$sessionIdsB = array_values(array_unique(array_filter(array_map(
    static fn (array $row) => (string) ($row['session_id'] ?? ''),
    $insightsB
))));

$leakAtoB = array_intersect($sessionIdsA, $sessionIdsB);
check($leakAtoB === [], 'no shared session_id in insights A vs B');

foreach ($insightsA as $row) {
    $sid = (string) ($row['student_id'] ?? '');
    check($sid === $aid || $sid === '', 'insight A row student_id matches A');
}

foreach ($insightsB as $row) {
    $sid = (string) ($row['student_id'] ?? '');
    check($sid === $bid || $sid === '', 'insight B row student_id matches B');
}

if ($sessionIdsA !== []) {
    $probe = $sessionIdsA[0];
    $ownedByA = $sb->select('edu_quest_sessions', 'id=eq.' . $probe . '&student_id=eq.' . $aid, 1);
    $ownedByB = $sb->select('edu_quest_sessions', 'id=eq.' . $probe . '&student_id=eq.' . $bid, 1);
    check(!empty($ownedByA), 'sample session A owned by A');
    check(empty($ownedByB), 'sample session A NOT owned by B');
}

echo "\nInsights: A=" . count($insightsA) . " B=" . count($insightsB) . "\n";
echo "\n=== {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
