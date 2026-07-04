<?php
/**
 * Phase 3 /edu/admin API verify — auth gate + org CRUD smoke (no destructive writes by default)
 *
 * Usage:
 *   php tools/edu_org_phase3_verify.php
 *
 * With admin key (full smoke including create/deactivate test org):
 *   EDU_ADMIN_API_KEY=... php tools/edu_org_phase3_verify.php --write
 *
 * Live:
 *   EDU_BASE=https://edu.thegist.co.kr EDU_ADMIN_API_KEY=... php tools/edu_org_phase3_verify.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';

$base = rtrim(getenv('EDU_BASE') ?: 'https://edu.thegist.co.kr', '/');
$adminKey = getenv('EDU_ADMIN_API_KEY') ?: '';
$doWrite = in_array('--write', $argv ?? [], true);
$fail = 0;

function ok(string $msg): void
{
    echo "OK  $msg\n";
}

function bad(string $msg): void
{
    echo "FAIL $msg\n";
    global $fail;
    $fail++;
}

function req(string $url, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? [],
        CURLOPT_CUSTOMREQUEST => $opts['method'] ?? 'GET',
        CURLOPT_POSTFIELDS => $opts['body'] ?? null,
    ]);
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        return ['code' => 0, 'json' => [], 'raw' => $err !== '' ? $err : 'curl failed'];
    }
    $json = is_string($body) ? json_decode($body, true) : null;
    return ['code' => $code, 'json' => is_array($json) ? $json : [], 'raw' => $body];
}

echo "=== EDU Phase 3 admin verify ===\n";
echo "Base: $base\n\n";

// 1) No key → 401
$r = req("$base/api/edu/admin/organizations.php");
if ($r['code'] === 401) {
    ok('organizations.php rejects missing admin key (401)');
} else {
    bad("organizations.php without key expected 401, got {$r['code']}");
}

// 2) Wrong key → 401
$r = req("$base/api/edu/admin/organizations.php", [
    'headers' => ['X-Edu-Admin-Key: invalid-key-smoke-test'],
]);
if ($r['code'] === 401) {
    ok('organizations.php rejects invalid admin key (401)');
} else {
    bad("organizations.php with bad key expected 401, got {$r['code']}");
}

// 3) Operator token must NOT work on admin endpoints
$r = req("$base/api/edu/admin/students.php", [
    'headers' => ['X-Edu-Operator-Token: fake-operator-token-smoke'],
]);
if ($r['code'] === 401) {
    ok('students.php rejects operator token (401) — admin/operator auth separated');
} else {
    bad("students.php with operator token expected 401, got {$r['code']}");
}

if ($adminKey === '') {
    echo "\nSkip authenticated checks — set EDU_ADMIN_API_KEY to run full smoke.\n";
    echo $fail === 0 ? "\nAll passed (auth gate only).\n" : "\nSome checks failed.\n";
    exit($fail > 0 ? 1 : 0);
}

$hdr = ['X-Edu-Admin-Key: ' . $adminKey, 'Content-Type: application/json'];

// 4) List orgs
$r = req("$base/api/edu/admin/organizations.php", ['headers' => $hdr]);
if ($r['code'] === 200 && ($r['json']['success'] ?? false)) {
    ok('GET organizations (200)');
} else {
    bad('GET organizations failed: HTTP ' . $r['code']);
}

// 5) List students (includes organization_id field)
$r = req("$base/api/edu/admin/students.php", ['headers' => $hdr]);
if ($r['code'] === 200 && ($r['json']['success'] ?? false)) {
    $students = $r['json']['students'] ?? [];
    ok('GET students (200), count=' . count($students));
    if ($students !== [] && !array_key_exists('organization_id', $students[0])) {
        bad('students response missing organization_id');
    } else {
        ok('students include organization_id');
    }
} else {
    bad('GET students failed: HTTP ' . $r['code']);
}

// 6) List operators
$r = req("$base/api/edu/admin/operators.php", ['headers' => $hdr]);
if ($r['code'] === 200 && ($r['json']['success'] ?? false)) {
    ok('GET operators (200)');
} else {
    bad('GET operators failed: HTTP ' . $r['code']);
}

// 7) Optional write smoke
if ($doWrite) {
    $slug = 'phase3-smoke-' . date('YmdHis');
    $r = req("$base/api/edu/admin/organizations.php", [
        'method' => 'POST',
        'headers' => $hdr,
        'body' => json_encode([
            'name' => 'Phase3 Smoke Org',
            'type' => 'academy',
            'slug' => $slug,
            'metadata' => ['contact' => 'verify-script'],
        ], JSON_THROW_ON_ERROR),
    ]);
    if ($r['code'] === 201 && !empty($r['json']['organization']['id'])) {
        ok('POST organization created');
        $orgId = (string) $r['json']['organization']['id'];
        $r2 = req("$base/api/edu/admin/organizations.php", [
            'method' => 'PATCH',
            'headers' => $hdr,
            'body' => json_encode(['id' => $orgId, 'is_active' => false], JSON_THROW_ON_ERROR),
        ]);
        if ($r2['code'] === 200) {
            ok('PATCH organization deactivated (cleanup)');
        } else {
            bad('PATCH organization failed: HTTP ' . $r2['code']);
        }
    } else {
        bad('POST organization failed: HTTP ' . $r['code']);
    }
} else {
    echo "\nTip: pass --write with EDU_ADMIN_API_KEY for create/deactivate org smoke.\n";
}

echo "\n" . ($fail === 0 ? "All passed.\n" : "Failed: $fail\n");
exit($fail > 0 ? 1 : 0);
