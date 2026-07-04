<?php
/**
 * Phase 4-A operator org scoping verify
 *
 * Static (always):
 *   php tools/edu_org_phase4a_scope_verify.php
 *
 * Live isolation (operator tokens):
 *   EDU_BASE=https://edu.thegist.co.kr \
 *   EDU_OP_TOKEN_A=... EDU_OP_TOKEN_B=... \
 *   php tools/edu_org_phase4a_scope_verify.php --live
 *
 * Live + super (test@edu.com):
 *   EDU_OP_TOKEN_SUPER=... php tools/edu_org_phase4a_scope_verify.php --live --super
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduOperatorScope.php';

$live = in_array('--live', $argv ?? [], true);
$withSuper = in_array('--super', $argv ?? [], true);
$fail = 0;

function ok4a(string $msg): void
{
    echo "OK  $msg\n";
}

function bad4a(string $msg): void
{
    echo "FAIL $msg\n";
    global $fail;
    $fail++;
}

function assertTrue4a(bool $cond, string $msg): void
{
    if ($cond) {
        ok4a($msg);
    } else {
        bad4a($msg);
    }
}

echo "=== EDU Phase 4-A operator org scoping verify ===\n\n";

// --- Static logic ---
$superLegacy = ['email' => 'test@edu.com', 'organization_id' => null];
$scopedA = ['email' => 'owner-a@academy.test', 'organization_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'];
$scopedB = ['email' => 'owner-b@school.test', 'organization_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'];
$studentNull = ['organization_id' => null];
$studentA = ['organization_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'];
$studentB = ['organization_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'];

assertTrue4a(eduOperatorHasSuperScope($superLegacy), 'legacy NULL org operator = super scope');
assertTrue4a(!eduOperatorHasSuperScope($scopedA), 'org-bound operator A is not super');
assertTrue4a(eduOperatorCanAccessStudent($superLegacy, $studentNull), 'super sees org NULL student');
assertTrue4a(eduOperatorCanAccessStudent($superLegacy, $studentA), 'super sees org A student');
assertTrue4a(!eduOperatorCanAccessStudent($scopedA, $studentNull), 'scoped A cannot see NULL org student');
assertTrue4a(eduOperatorCanAccessStudent($scopedA, $studentA), 'scoped A sees own org student');
assertTrue4a(!eduOperatorCanAccessStudent($scopedA, $studentB), 'scoped A cannot see org B student');
assertTrue4a(!eduOperatorCanAccessStudent($scopedB, $studentA), 'scoped B cannot see org A student');

$filterA = eduOperatorStudentsSelectFilter($scopedA);
assertTrue4a(
    str_contains($filterA, 'organization_id=eq.aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
    'scoped A filter includes organization_id'
);
assertTrue4a(
    !str_contains(eduOperatorStudentsSelectFilter($superLegacy), 'organization_id=eq.'),
    'super filter has no organization_id constraint'
);

echo "\n--- Static logic done ---\n\n";

if (!$live) {
    echo "Tip: --live with EDU_OP_TOKEN_A/B for cross-org isolation on live API.\n";
    echo ($fail === 0 ? "\nAll passed.\n" : "\nFailed: $fail\n");
    exit($fail > 0 ? 1 : 0);
}

$base = rtrim(getenv('EDU_BASE') ?: 'https://edu.thegist.co.kr', '/');
$tokenA = getenv('EDU_OP_TOKEN_A') ?: '';
$tokenB = getenv('EDU_OP_TOKEN_B') ?: '';
$tokenSuper = getenv('EDU_OP_TOKEN_SUPER') ?: '';

function opFetchStudents(string $base, string $token): array
{
    $ch = curl_init($base . '/api/edu/operator/reports.php?action=students&limit=200');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['X-Edu-Operator-Token: ' . $token],
    ]);
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        return ['http' => 0, 'ids' => [], 'error' => 'curl failed'];
    }
    $data = json_decode($raw, true);
    if ($code !== 200 || !is_array($data) || empty($data['success'])) {
        return ['http' => $code, 'ids' => [], 'error' => (string) ($data['error'] ?? $raw)];
    }
    $ids = [];
    foreach ($data['students'] ?? [] as $row) {
        $id = (string) ($row['id'] ?? '');
        if ($id !== '') {
            $ids[] = $id;
        }
    }
    return ['http' => $code, 'ids' => $ids, 'error' => ''];
}

if ($tokenA === '' || $tokenB === '') {
    bad4a('live mode requires EDU_OP_TOKEN_A and EDU_OP_TOKEN_B (two org operators)');
} else {
    $listA = opFetchStudents($base, $tokenA);
    $listB = opFetchStudents($base, $tokenB);

    if ($listA['http'] !== 200) {
        bad4a('operator A students HTTP ' . $listA['http'] . ' ' . $listA['error']);
    } else {
        ok4a('operator A students HTTP 200, count=' . count($listA['ids']));
    }
    if ($listB['http'] !== 200) {
        bad4a('operator B students HTTP ' . $listB['http'] . ' ' . $listB['error']);
    } else {
        ok4a('operator B students HTTP 200, count=' . count($listB['ids']));
    }

    if ($listA['http'] === 200 && $listB['http'] === 200) {
        $overlap = array_intersect($listA['ids'], $listB['ids']);
        if ($overlap === []) {
            ok4a('A/B student lists have zero overlap (org isolation)');
        } else {
            bad4a('A/B overlap detected: ' . implode(', ', array_slice($overlap, 0, 5)));
        }

        if ($tokenA !== '' && $listB['ids'] !== []) {
            $probeId = $listB['ids'][0];
            $ch = curl_init($base . '/api/edu/operator/reports.php?action=preview&student_id=' . rawurlencode($probeId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['X-Edu-Operator-Token: ' . $tokenA],
            ]);
            if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 404) {
                ok4a('operator A cannot preview operator B student (404)');
            } else {
                bad4a("operator A preview B student expected 404, got HTTP $code body=" . substr((string) $raw, 0, 120));
            }
        }
    }
}

if ($withSuper && $tokenSuper !== '') {
    $superList = opFetchStudents($base, $tokenSuper);
    if ($superList['http'] === 200) {
        ok4a('super operator students HTTP 200, count=' . count($superList['ids']));
        if ($tokenA !== '' && $superList['http'] === 200 && opFetchStudents($base, $tokenA)['http'] === 200) {
            $scopedCount = count(opFetchStudents($base, $tokenA)['ids']);
            if (count($superList['ids']) >= $scopedCount) {
                ok4a('super list count >= scoped A (super sees equal or more)');
            } else {
                bad4a('super list smaller than scoped A — unexpected');
            }
        }
    } else {
        bad4a('super operator students failed HTTP ' . $superList['http']);
    }
} elseif ($withSuper) {
    echo "WARN skip super check — set EDU_OP_TOKEN_SUPER\n";
}

echo "\n" . ($fail === 0 ? "All passed.\n" : "Failed: $fail\n");
exit($fail > 0 ? 1 : 0);
