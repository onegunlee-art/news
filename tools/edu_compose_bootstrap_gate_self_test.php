<?php
/**
 * compose bootstrap 배포 게이트 판정 로직 self-test
 * (500 empty → 거부, 401 JSON → 통과)
 *
 * Usage: php tools/edu_compose_bootstrap_gate_self_test.php
 */
declare(strict_types=1);

require_once __DIR__ . '/edu_compose_bootstrap_gate.php';

/** @param array{http: int, raw: string} $probe */
function gateSelfAssert(string $label, array $probe, bool $shouldPass): void
{
    $err = eduComposeBootstrapGateError($probe);
    $passed = $err === null;
    if ($passed !== $shouldPass) {
        $state = $passed ? 'PASS' : 'FAIL: ' . $err;
        fwrite(STDERR, "SELF-TEST FAIL [{$label}]: expected " . ($shouldPass ? 'PASS' : 'FAIL') . ", got {$state}\n");
        exit(1);
    }
    echo "OK {$label}\n";
}

gateSelfAssert('500 empty body (regression)', ['http' => 500, 'raw' => ''], false);
gateSelfAssert('500 with JSON error', ['http' => 500, 'raw' => '{"success":false,"error":"x"}'], false);
gateSelfAssert('401 without token message', ['http' => 401, 'raw' => '{"success":false,"error":"nope"}'], false);
gateSelfAssert(
    '401 X-Edu-Token required',
    ['http' => 401, 'raw' => '{"success":false,"error":"X-Edu-Token required"}'],
    true
);

echo "\n=== compose bootstrap gate self-test PASS ===\n";
