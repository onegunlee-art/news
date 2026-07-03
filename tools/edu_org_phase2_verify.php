<?php
/**
 * Phase 2 org migration verify — schema + org NULL student safety
 *
 * Usage (after Supabase runs add_edu_organizations.sql):
 *   php tools/edu_org_phase2_verify.php
 *
 * Optional live API checks:
 *   EDU_BASE=https://edu.thegist.co.kr php tools/edu_org_phase2_verify.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$base = getenv('EDU_BASE') ?: 'https://edu.thegist.co.kr';
$fail = 0;

function ok(string $msg): void
{
    echo "OK  $msg\n";
}

function warn(string $msg): void
{
    echo "WARN $msg\n";
}

function bad(string $msg): void
{
    echo "FAIL $msg\n";
    global $fail;
    $fail++;
}

echo "=== EDU Phase 2 org migration verify ===\n\n";

$sb = eduSupabase();

// 1) edu_organizations table
$orgs = $sb->select('edu_organizations', 'order=created_at.desc', 1);
if ($orgs === null) {
    bad('edu_organizations missing or unreadable — run add_edu_organizations.sql in Supabase');
    echo "\nExit early — migration not applied.\n";
    exit(1);
}
ok('edu_organizations table readable');

// 2) edu_students.organization_id column (nullable)
$students = $sb->select('edu_students', 'status=eq.active&order=last_active_at.desc.nullslast', 20) ?? [];
if ($students === []) {
    warn('no active edu_students rows (unexpected but not a schema failure)');
} else {
    $sample = $students[0];
    if (!array_key_exists('organization_id', $sample)) {
        bad('edu_students.organization_id column not present — migration incomplete');
    } else {
        ok('edu_students.organization_id column present');
    }
    $nullOrg = 0;
    $named = [];
    foreach ($students as $s) {
        if (($s['organization_id'] ?? null) === null || $s['organization_id'] === '') {
            $nullOrg++;
            $named[] = (string) ($s['display_name'] ?? $s['id'] ?? '?');
        }
    }
    ok("active students sampled: " . count($students) . ", organization_id NULL: $nullOrg");
    if ($nullOrg > 0) {
        ok('NULL org students (expected pre-assignment): ' . implode(', ', array_slice($named, 0, 5)));
    }
}

// 3) edu_operators columns
try {
    $ops = $sb->select('edu_operators', 'status=eq.active', 5) ?? [];
    if ($ops !== [] && !array_key_exists('organization_id', $ops[0])) {
        bad('edu_operators.organization_id column not present');
    } else {
        ok('edu_operators.organization_id column present (or no operators yet)');
    }
} catch (Throwable $e) {
    bad('edu_operators check failed: ' . $e->getMessage());
}

// 4) Live guest API (org NULL path — no org in request)
echo "\n--- Live API (guest) ---\n";
$ch = curl_init($base . '/api/edu/guest/start.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_TIMEOUT => 30,
]);
if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
$raw = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode((string) $raw, true) ?: [];
$token = (string) ($data['token'] ?? '');
if ($http === 200 && $token !== '') {
    ok("guest/start HTTP 200 token_len=" . strlen($token));
} else {
    bad("guest/start HTTP $http — org migration must not break guest flow");
}

// 5) Health
$healthCh = curl_init($base . '/api/edu/health.php');
curl_setopt_array($healthCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 15,
]);
if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
    curl_setopt($healthCh, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($healthCh, CURLOPT_SSL_VERIFYHOST, 0);
}
curl_exec($healthCh);
$healthCode = (int) curl_getinfo($healthCh, CURLINFO_HTTP_CODE);
if ($healthCode === 200) {
    ok('edu health HTTP 200');
} else {
    warn("edu health HTTP $healthCode (check EDU_BASE)");
}

echo "\n";
if ($fail === 0) {
    echo "All Phase 2 checks passed.\n";
    echo "Manual: 이원근·한현석 퀘스트 1회 완주 + test@edu.com 리포트 (operator 스코핑 전).\n";
    exit(0);
}
echo "$fail check(s) failed.\n";
exit(1);
