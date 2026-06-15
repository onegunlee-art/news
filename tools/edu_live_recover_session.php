<?php
/**
 * 라이브 막힌 세션 compose 재호출 복구 검증
 * Usage: EDU_LIVE_RECOVER_TOKEN=<X-Edu-Token> php tools/edu_live_recover_session.php [session_id]
 *
 * 토큰은 해당 세션 소유 학생의 X-Edu-Token (브라우저/파일럿 계정).
 * 토큰 없으면 세션 상태만 출력하고 exit 2.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';

$sessionId = $argv[1] ?? '852bfa06-084c-465e-ac02-91d6ef4fd7d6';
$base = getenv('EDU_LIVE_BASE') ?: 'https://www.thegist.co.kr';
$token = getenv('EDU_LIVE_RECOVER_TOKEN') ?: '';

$supabase = eduSupabase();
$session = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId, 1)[0] ?? null;
if ($session === null) {
    fwrite(STDERR, "session not found: {$sessionId}\n");
    exit(1);
}

$bp = eduLoadBlueprint($session);
$hasStruct = is_array($bp['essay_structure'] ?? null) && !empty($bp['essay_structure']['sections']);
$stage = (string) ($session['stage'] ?? '');

echo "=== Live recover session ===\n";
echo "session: {$sessionId}\n";
echo "base: {$base}\n";
echo "stage={$stage} phase=" . ($bp['phase'] ?? '') . "\n";
echo 'ready_for_compose=' . (!empty($bp['ready_for_compose']) ? 'Y' : 'N') . "\n";
echo 'essay_structure=' . ($hasStruct ? 'YES' : 'NO') . "\n";
echo 'student_id=' . ($session['student_id'] ?? '') . "\n\n";

if ($stage === 'completed' && $hasStruct) {
    echo "ALREADY_RECOVERED: session completed with structure\n";
    exit(0);
}

if ($token === '') {
    fwrite(STDERR, "EDU_LIVE_RECOVER_TOKEN not set — state dump only (exit 2)\n");
    exit(2);
}

$ch = curl_init($base . '/api/edu/session/compose.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['session_id' => $sessionId], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Edu-Token: ' . $token,
    ],
    CURLOPT_TIMEOUT => 300,
]);
if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}

$t0 = microtime(true);
$raw = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$sec = round(microtime(true) - $t0, 1);

$data = is_string($raw) ? json_decode($raw, true) : null;
$data = is_array($data) ? $data : [];

echo "compose HTTP {$http} elapsed={$sec}s\n";

if ($http !== 200 || empty($data['success'])) {
    echo ($raw ?: 'empty response') . "\n";
    exit(1);
}

$full = trim((string) ($data['full_text'] ?? ''));
echo 'title: ' . ($data['title'] ?? '') . "\n";
echo 'len: ' . mb_strlen($full) . "\n";
echo 'stage: ' . ($data['stage'] ?? '') . "\n";

$checks = [
    '여론/피로' => str_contains($full, '피로') || str_contains($full, '여론'),
    '베트남' => str_contains($full, '베트남'),
    'non_empty' => $full !== '',
];
foreach ($checks as $label => $ok) {
    echo '[' . ($ok ? 'PASS' : 'FAIL') . "] {$label}\n";
}

$session2 = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId, 1)[0] ?? null;
$bp2 = eduLoadBlueprint($session2);
$hasStruct2 = is_array($bp2['essay_structure'] ?? null) && !empty($bp2['essay_structure']['sections']);
echo "\npost_call stage=" . ($session2['stage'] ?? '') . " essay_structure=" . ($hasStruct2 ? 'YES' : 'NO') . "\n";

exit($full !== '' ? 0 : 1);
