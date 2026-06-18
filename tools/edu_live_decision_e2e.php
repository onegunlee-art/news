<?php
/**
 * 라이브 E2E — Q-G09-DEC-2022 일본 decision_inquiry 완주 (R5)
 *
 * quest_id로 퀘스트를 고정한다. evidence gate는 nudge 1회 후 advance — flake 방지 4턴.
 */
declare(strict_types=1);

$base = 'https://www.thegist.co.kr';
$expectedQuest = 'Q-G09-DEC-2022';

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

echo "=== Live Decision Inquiry E2E (R5 Japan) ===\n";

$guest = api('POST', '/api/edu/guest/start.php', []);
$token = (string) ($guest['data']['token'] ?? '');
if ($guest['http'] !== 200 || $token === '') {
    echo $guest['raw'] . "\n";
    exit(1);
}

$list = api('GET', '/api/edu/quests/list.php?limit=50&frame=decision_inquiry', null, $token);
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
    echo "FAIL: {$expectedQuest} not in decision_inquiry list — seed/filter check\n";
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

$stance = api('POST', '/api/edu/session/chat.php', [
    'session_id' => $sid,
    'action' => 'select_stance',
    'stance' => 'pro',
], $token);
$coachQ = (string) ($stance['data']['assistant_message'] ?? '');
echo "stance HTTP {$stance['http']} coach=" . mb_substr($coachQ, 0, 80) . "…\n";
if ($stance['http'] !== 200) {
    echo $stance['raw'] . "\n";
    exit(1);
}

$reasoningMsgs = [
    '중국·대만 때문에 일본 주변도 위험해져서 미사일이 필요하다고 봐요.',
    '기사에서 일본은 2022년에 먼 적을 맞출 미사일을 갖추기로 했다고 했어요. 주변 위협 때문에 선택한 거 같아요.',
];
$evidenceTurns = [
    '일본이 스스로 방어할 수 있어야 미국이 늦게 와도 버틸 수 있다는 점이 중요해요.',
    '기사 546번에서 일본의 재무장이 돌이킬 수 없는 흐름이라고 했어요. 그게 미사일 결정의 배경인 것 같아요.',
    '452번 기사에서 일본 방산 산업이 다시 힘을 키우고 있다고 했어요. 미사일도 그 흐름의 일부인 것 같아요.',
    '동북아 안보가 불안해지면서 일본이 먼저 맞대응할 수단이 필요하다는 분석이 기사에 나왔어요.',
];

$phase = '';
$shouldCompose = false;
$step = 2;
foreach ($reasoningMsgs as $msg) {
    $r = chat($token, $sid, $msg);
    $phase = (string) ($r['data']['phase'] ?? '?');
    $uiHint = (string) ($r['data']['ui_hint'] ?? '');
    echo "step{$step} HTTP {$r['http']} phase={$phase} ui_hint={$uiHint}\n";
    $step++;
    if ($r['http'] >= 400) {
        echo $r['raw'] . "\n";
        exit(1);
    }
}
foreach ($evidenceTurns as $msg) {
    $r = chat($token, $sid, $msg);
    $phase = (string) ($r['data']['phase'] ?? '?');
    $uiHint = (string) ($r['data']['ui_hint'] ?? '');
    echo "step{$step} HTTP {$r['http']} phase={$phase} ui_hint={$uiHint}\n";
    $step++;
    if ($r['http'] >= 400) {
        echo $r['raw'] . "\n";
        exit(1);
    }
    if ($phase !== 'evidence') {
        break;
    }
}

if ($phase === 'evidence') {
    echo "FAIL: evidence gate not passed after " . count($evidenceTurns) . " turns\n";
    exit(1);
}

if ($phase === 'hammer') {
    $r = chat($token, $sid, '주변 불안이 커져서 미사일이 필요하다는 생각은 유지하지만, 이웃 나라 반응도 걱정돼요.');
    $phase = (string) ($r['data']['phase'] ?? '?');
    echo "hammer-rebuttal HTTP {$r['http']} phase={$phase}\n";
    if ($r['http'] >= 400) {
        echo $r['raw'] . "\n";
        exit(1);
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

if ($shouldCompose) {
    echo "PASS: reached compose\n";
    exit(0);
}

echo "FAIL: phase={$phase} (compose not reached)\n";
exit(1);
