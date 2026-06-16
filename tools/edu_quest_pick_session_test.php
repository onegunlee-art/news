<?php
/**
 * quest_id 선택 시 다른 퀘스트 세션이 재개되지 않는지 검증
 *
 * 시나리오:
 *   1. 이란(live) 세션 시작 + stance까지 진행(미완료)
 *   2. list에서 G09 quest_id로 start
 *   3. state의 quest_code가 G09인지 확인 (이란이면 FAIL)
 *
 * Usage:
 *   php tools/edu_quest_pick_session_test.php
 *   php tools/edu_quest_pick_session_test.php --base=http://127.0.0.1:8080
 */
declare(strict_types=1);

$base = 'https://www.thegist.co.kr';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

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
        CURLOPT_TIMEOUT => 120,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return ['http' => $http, 'data' => is_array($data) ? $data : [], 'raw' => (string) $raw];
}

function fail(string $msg, array $ctx = []): void
{
    fwrite(STDERR, "FAIL: {$msg}\n");
    if ($ctx !== []) {
        fwrite(STDERR, json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }
    exit(1);
}

echo "=== quest_id pick session test (base={$base}) ===\n\n";

$guest = api('POST', '/api/edu/guest/start.php', []);
if (($guest['data']['success'] ?? false) !== true) {
    fail('guest start', $guest);
}
$token = (string) ($guest['data']['token'] ?? '');

$list = api('GET', '/api/edu/quests/list.php', null, $token);
if (($list['data']['success'] ?? false) !== true) {
    fail('list quests', $list);
}

$g09 = null;
$iranLive = null;
foreach ($list['data']['quests'] ?? [] as $q) {
    $code = (string) ($q['quest_code'] ?? '');
    if ($code === 'Q-G09-DEC-2022') {
        $g09 = $q;
    }
    if (($q['is_live'] ?? false) === true) {
        $iranLive = $q;
    }
}
if ($g09 === null) {
    fail('Q-G09-DEC-2022 not in list — seed required');
}
if ($iranLive === null) {
    fail('no live quest in list');
}

echo "live: {$iranLive['quest_code']} ({$iranLive['quest_id']})\n";
echo "g09:  {$g09['quest_code']} ({$g09['quest_id']})\n\n";

$iranStart = api('POST', '/api/edu/session/start.php', [], $token);
if (($iranStart['data']['success'] ?? false) !== true) {
    fail('start iran session', $iranStart);
}
$iranSid = (string) ($iranStart['data']['session_id'] ?? '');
echo "1) Iran session started: {$iranSid} resumed=" . json_encode($iranStart['data']['resumed'] ?? null) . "\n";

$stance = api('POST', '/api/edu/session/chat.php', [
    'session_id' => $iranSid,
    'action' => 'select_stance',
    'stance' => 'pro',
], $token);
if (($stance['data']['success'] ?? false) !== true) {
    fail('iran stance', $stance);
}
echo "   Iran stance set (in-progress)\n";

$g09Start = api('POST', '/api/edu/session/start.php', ['quest_id' => $g09['quest_id']], $token);
if (($g09Start['data']['success'] ?? false) !== true) {
    fail('start g09 session', $g09Start);
}
$g09Sid = (string) ($g09Start['data']['session_id'] ?? '');
$resumed = (bool) ($g09Start['data']['resumed'] ?? false);
echo "2) G09 start: session={$g09Sid} resumed=" . json_encode($resumed) . "\n";

if ($g09Sid === $iranSid) {
    fail('G09 pick resumed Iran session — BUG', [
        'iran_session' => $iranSid,
        'g09_start' => $g09Start['data'],
    ]);
}

$state = api('GET', '/api/edu/session/state.php?session_id=' . rawurlencode($g09Sid), null, $token);
if (($state['data']['success'] ?? false) !== true) {
    fail('session state', $state);
}
$questCode = (string) ($state['data']['quest']['quest_code'] ?? '');
$questTitle = (string) ($state['data']['quest']['quest_title'] ?? '');
echo "3) State quest: {$questCode}\n";
echo "   title: {$questTitle}\n";

if ($questCode !== 'Q-G09-DEC-2022') {
    fail("expected Q-G09-DEC-2022, got {$questCode}", $state['data']['quest'] ?? []);
}

// same quest re-pick should resume
$g09Again = api('POST', '/api/edu/session/start.php', ['quest_id' => $g09['quest_id']], $token);
if (($g09Again['data']['success'] ?? false) !== true) {
    fail('g09 resume', $g09Again);
}
if (($g09Again['data']['resumed'] ?? false) !== true || ($g09Again['data']['session_id'] ?? '') !== $g09Sid) {
    fail('same quest should resume same session', $g09Again['data']);
}
echo "4) Same G09 re-pick resumes: OK\n";

echo "\nGATE: PASS\n";
