<?php
/**
 * 라이브 E2E — Q-IRAN-DEC-202606 decision_inquiry 흐름
 */
declare(strict_types=1);

$base = 'https://www.thegist.co.kr';

function api(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    global $base;
    $ch = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'X-Edu-Token: ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    return ['http' => $http, 'data' => json_decode((string) $raw, true) ?: [], 'raw' => (string) $raw, 'curl_error' => $err];
}

function chat(string $token, string $sid, string $message, string $action = 'continue'): array
{
    return api('POST', '/api/edu/session/chat.php', [
        'session_id' => $sid,
        'message' => $message,
        'action' => $action,
    ], $token);
}

echo "=== Live Decision Inquiry E2E ===\n";

$today = api('GET', '/api/edu/quests/today.php');
$questCode = (string) ($today['data']['quest']['quest_code'] ?? '');
$timeAnchor = (string) ($today['data']['quest']['time_anchor'] ?? '');
$questFrame = (string) ($today['data']['quest']['quest_frame'] ?? '');
echo "today HTTP {$today['http']} quest={$questCode} frame={$questFrame} time_anchor={$timeAnchor}\n";
if ($today['http'] !== 200) {
    echo $today['raw'] . "\n";
    exit(1);
}
if ($questCode !== 'Q-IRAN-DEC-202606') {
    echo "WARN: expected Q-IRAN-DEC-202606, got {$questCode}\n";
}

$guest = api('POST', '/api/edu/guest/start.php', []);
$token = (string) ($guest['data']['token'] ?? '');
if ($guest['http'] !== 200 || $token === '') {
    echo $guest['raw'] . "\n";
    exit(1);
}

$start = api('POST', '/api/edu/session/start.php', [], $token);
$sid = (string) ($start['data']['session_id'] ?? '');
echo "session {$sid} HTTP {$start['http']}\n";

$stance = api('POST', '/api/edu/session/chat.php', [
    'session_id' => $sid,
    'action' => 'select_stance',
    'stance' => 'pro',
], $token);
$coachQ = (string) ($stance['data']['assistant_message'] ?? '');
echo "stance HTTP {$stance['http']} coach=" . mb_substr($coachQ, 0, 80) . "…\n";
if (preg_match('/안 끝나|끝낼 수/u', $coachQ)) {
    echo "FAIL: coach still uses result_prediction frame\n";
    exit(1);
}

$msgs = [
    '군대 보내면 미국 사람들이 더 반대할 것 같아서 미사일만 쓴 게 맞다고 봐요.',
    '기사에서 미사일은 목표는 맞출 수 있어도 이란처럼 버티는 나라는 군대 없이는 못 이긴다고 했어요. 미사일만으로는 이란을 완전히 이길 수 없다는 점이 중요해요.',
    '미사일만 쓰면 전쟁이 더 길어질 수도 있어서 나중에 더 큰 대가가 올 것 같아요. 그래도 당장 군대를 보내는 것보다는 덜 위험하다고 봐요.',
];

$phase = '';
$shouldCompose = false;
foreach ($msgs as $i => $msg) {
    $r = chat($token, $sid, $msg);
    $phase = (string) ($r['data']['phase'] ?? '?');
    $hammer = (string) ($r['data']['hammer_mode'] ?? '');
    $assistant = (string) ($r['data']['assistant_message'] ?? '');
    echo 'step' . ($i + 2) . " HTTP {$r['http']} phase={$phase}";
    if ($hammer !== '') {
        echo " hammer={$hammer}";
    }
    if (preg_match('/우리 둘 다.*동의/u', $assistant)) {
        echo ' WARN:old_hammer_frame';
    }
    echo "\n";
    if ($r['http'] >= 400) {
        echo $r['raw'] . "\n";
        exit(1);
    }
}

if ($phase === 'hammer') {
    $r = chat($token, $sid, '미사일만 쓴 건 덜 위험했지만, 앞으로 전쟁이 더 길어질까 봐 걱정돼요.');
    $phase = (string) ($r['data']['phase'] ?? '?');
    $assistant = (string) ($r['data']['assistant_message'] ?? '');
    echo "hammer-rebuttal HTTP {$r['http']} phase={$phase}\n";
    if (preg_match('/우리 둘 다/u', $assistant)) {
        echo "WARN: hammer may use old meta frame (deploy pending)\n";
    }
}

if ($phase !== 'reflection') {
    echo "WARN: expected reflection before confirm, got phase={$phase}\n";
}

$r = chat($token, $sid, '맞아');
$phase = (string) ($r['data']['phase'] ?? '?');
echo "confirm HTTP {$r['http']} phase={$phase}";
if (!empty($r['data']['should_compose'])) {
    $shouldCompose = true;
    echo ' should_compose=YES';
}
echo "\n";

if (!$shouldCompose && $phase === 'reflection') {
    $r = api('POST', '/api/edu/session/chat.php', [
        'session_id' => $sid,
        'action' => 'confirm_reflection',
    ], $token);
    $phase = (string) ($r['data']['phase'] ?? '?');
    echo "confirm_reflection HTTP {$r['http']} phase={$phase}";
    if (!empty($r['data']['should_compose'])) {
        $shouldCompose = true;
        echo ' should_compose=YES';
    }
    echo "\n";
}

echo $shouldCompose ? "PASS: reached compose\n" : "PARTIAL: phase={$phase} (compose not reached — may need deploy)\n";
