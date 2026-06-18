<?php
/**
 * GIST EDU — evidence bridge 멘트 회귀 (고정 템플릿, LLM 미사용)
 *
 * Usage: php tools/edu_evidence_bridge_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';

$pass = 0;
$fail = 0;

function assertBridge(string $label, array $blueprint, string $expectContains, ?string $expectNotContains = null): void
{
    global $pass, $fail;
    $msg = eduBuildEvidenceBridgeMessage($blueprint);
    $ok = str_contains($msg, $expectContains);
    if ($expectNotContains !== null && str_contains($msg, $expectNotContains)) {
        $ok = false;
    }
    if ($ok) {
        echo "PASS {$label}\n  → {$msg}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n  got: {$msg}\n  expect contains: {$expectContains}\n";
    $fail++;
}

assertBridge(
    'with_reason',
    ['reason' => '핵은 방어 수단이라고 생각해'],
    "'핵은 방어 수단이라고 생각해'",
    '기사들을 참고해서'
);

assertBridge(
    'short_reason_fallback',
    ['reason' => '음'],
    '방금 말한 생각',
    "'음'"
);

assertBridge(
    'empty_reason_fallback',
    ['reason' => ''],
    '방금 말한 생각',
    '이라고 했지'
);

$long = '핵은 방어 수단이야. ' . str_repeat('그래서 ', 30) . '끝.';
$msgLong = eduBuildEvidenceBridgeMessage(['reason' => $long]);
if (str_contains($msgLong, '핵은 방어 수단이야') && !str_contains($msgLong, '그래서')) {
    echo "PASS long_reason_first_sentence\n  → {$msgLong}\n";
    $pass++;
} else {
    echo "FAIL long_reason_first_sentence\n  got: {$msgLong}\n";
    $fail++;
}

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
