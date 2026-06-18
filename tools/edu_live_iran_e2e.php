<?php
/**
 * 라이브 E2E — Q-IRAN-FOREVER-001 이란 convergent 완주 (R4, hammer 반론 포함)
 *
 * quest_id로 퀘스트를 고정한다. start.php 기본(today)에 의존하지 않는다.
 */
declare(strict_types=1);

$base = 'https://www.thegist.co.kr';
$expectedQuest = 'Q-IRAN-FOREVER-001';

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

echo "=== Live Iran E2E (R4 convergent) ===\n";

$guest = api('POST', '/api/edu/guest/start.php', []);
$token = (string) ($guest['data']['token'] ?? '');
echo "guest HTTP {$guest['http']}";
if (!empty($guest['curl_error'])) {
    echo " curl_error={$guest['curl_error']}";
}
echo "\n";
if ($guest['http'] !== 200 || $token === '') {
    echo $guest['raw'] . "\n";
    exit(1);
}

$list = api('GET', '/api/edu/quests/list.php?limit=50', null, $token);
if ($list['http'] !== 200) {
    echo $list['raw'] . "\n";
    exit(1);
}
$questId = null;
foreach ($list['data']['quests'] ?? [] as $q) {
    if (($q['quest_code'] ?? '') === $expectedQuest) {
        $questId = (string) ($q['quest_id'] ?? '');
        break;
    }
}
if ($questId === null || $questId === '') {
    echo "FAIL: {$expectedQuest} not in list — seed required\n";
    exit(1);
}
echo "quest={$expectedQuest} quest_id={$questId}\n";

$start = api('POST', '/api/edu/session/start.php', ['quest_id' => $questId], $token);
$sid = (string) ($start['data']['session_id'] ?? '');
echo "session {$sid} HTTP {$start['http']}\n";
if ($start['http'] !== 200 || $sid === '') {
    echo $start['raw'] . "\n";
    exit(1);
}

$state = api('GET', '/api/edu/session/state.php?session_id=' . rawurlencode($sid), null, $token);
$startedCode = (string) ($state['data']['quest']['quest_code'] ?? '');
echo "started quest={$startedCode}\n";
if ($startedCode !== $expectedQuest) {
    echo "FAIL: expected {$expectedQuest}, got {$startedCode}\n";
    exit(1);
}

api('POST', '/api/edu/session/chat.php', [
    'session_id' => $sid,
    'action' => 'select_stance',
    'stance' => 'pro',
], $token);

$msgs = [
    '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.',
    '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지',
    '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지',
    '기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아',
    '전쟁은 원래 의도와 상관없이 얽히는거 같아',
];

$shouldCompose = false;
$phase = '';
foreach ($msgs as $i => $msg) {
    $r = chat($token, $sid, $msg);
    $phase = (string) ($r['data']['phase'] ?? '?');
    $hammer = $r['data']['hammer_mode'] ?? '';
    echo 'step' . ($i + 2) . " HTTP {$r['http']} phase={$phase}";
    if ($hammer !== '') {
        echo " hammer={$hammer}";
    }
    if (!empty($r['data']['should_compose'])) {
        $shouldCompose = true;
        echo ' should_compose=YES';
    }
    echo "\n";
    if ($r['http'] >= 400) {
        echo $r['raw'] . "\n";
        exit(1);
    }
}

if ($phase === 'hammer') {
    $r = chat($token, $sid, '전쟁은 원래 의도와 상관없이 얽히는거 같아');
    $phase = (string) ($r['data']['phase'] ?? '?');
    echo "hammer-rebuttal HTTP {$r['http']} phase={$phase}\n";
    if ($r['http'] >= 400) {
        echo $r['raw'] . "\n";
        exit(1);
    }
}

$r = chat($token, $sid, '맞아');
$phase = (string) ($r['data']['phase'] ?? '?');
echo "confirm HTTP {$r['http']} phase={$phase}";
if (!empty($r['data']['should_compose'])) {
    $shouldCompose = true;
    echo ' should_compose=YES';
}
if (!empty($r['data']['structure_preview']['title'])) {
    echo ' step1_title=' . $r['data']['structure_preview']['title'];
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
    if (!empty($r['data']['structure_preview']['title'])) {
        echo ' step1_title=' . $r['data']['structure_preview']['title'];
    }
    echo "\n";
}

$t0 = microtime(true);
$compose = api('POST', '/api/edu/session/compose.php', ['session_id' => $sid], $token);
$sec = round(microtime(true) - $t0, 1);
echo "compose HTTP {$compose['http']} elapsed={$sec}s\n";

if ($compose['http'] !== 200) {
    echo $compose['raw'] . "\n";
    exit(1);
}

$full = trim((string) ($compose['data']['full_text'] ?? ''));
echo 'title: ' . ($compose['data']['title'] ?? '') . "\n";
echo 'len: ' . mb_strlen($full) . "\n";
echo ($full !== '' ? 'PASS' : 'FAIL') . " full_text\n";
echo ($shouldCompose ? 'PASS' : 'WARN') . " should_compose\n";

exit($full !== '' ? 0 : 1);
