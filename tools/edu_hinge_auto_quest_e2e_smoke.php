<?php
/**
 * P2-A3 — Q-AUTO-NUKE-630 myth_bust E2E smoke (--live only, today 의존 없음)
 *
 * Usage:
 *   php tools/edu_hinge_auto_quest_e2e_smoke.php --live
 *   php tools/edu_hinge_auto_quest_e2e_smoke.php --live --base=https://www.thegist.co.kr
 */
declare(strict_types=1);

if (!in_array('--live', $argv ?? [], true)) {
    fwrite(STDERR, "Usage: php tools/edu_hinge_auto_quest_e2e_smoke.php --live\n");
    exit(0);
}

$base = 'https://www.thegist.co.kr';
$questCode = 'Q-AUTO-NUKE-630';
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
        CURLOPT_TIMEOUT => 300,
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
    $data = is_string($raw) ? json_decode($raw, true) : null;

    return ['http' => $http, 'data' => is_array($data) ? $data : [], 'raw' => (string) $raw];
}

function chat(string $token, string $sid, array $payload): array
{
    return api('POST', '/api/edu/session/chat.php', array_merge(['session_id' => $sid], $payload), $token);
}

function fail(string $msg, array $ctx = []): void
{
    fwrite(STDERR, "FAIL: {$msg}\n");
    if ($ctx !== []) {
        fwrite(STDERR, json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }
    exit(1);
}

echo "=== P2-A3 auto hinge quest e2e ({$questCode}) ===\n\n";

$guest = api('POST', '/api/edu/guest/start.php', []);
$token = (string) ($guest['data']['token'] ?? '');
if ($guest['http'] !== 200 || $token === '') {
    fail('guest start', $guest);
}

$list = api('GET', '/api/edu/quests/list.php?frame=myth_bust&limit=50', null, $token);
$target = null;
foreach ($list['data']['quests'] ?? [] as $q) {
    if (($q['quest_code'] ?? '') === $questCode) {
        $target = $q;
        break;
    }
}
if ($target === null) {
    fail("{$questCode} not in list — run seed first");
}

$start = api('POST', '/api/edu/session/start.php', ['quest_id' => $target['quest_id']], $token);
$sid = (string) ($start['data']['session_id'] ?? '');
if ($start['http'] !== 200 || $sid === '') {
    fail('session start', $start);
}
echo "quest_id={$target['quest_id']} session={$sid}\n";

$opening = '핵무기가 있으면 나라들이 함부로 못 싸울 거라고 생각해. 큰 전쟁은 막을 수 있을 것 같아.';
$r1 = chat($token, $sid, ['action' => 'submit_opening', 'message' => $opening]);
if ($r1['http'] !== 200) {
    fail('submit_opening', $r1);
}
echo "opening phase=" . ($r1['data']['phase'] ?? '?') . "\n";

$evidenceMsg = '기사에 러시아 전략폭격기 기지가 드론 공격을 받았다고 나와. 그래도 핵 대신 재래식으로만 맞대응했다는 점이 중요한 것 같아.';
$turns = 0;
$phase = (string) ($r1['data']['phase'] ?? '');
$last = $r1;
while ($phase !== 'hammer' && $turns < 6) {
    $last = chat($token, $sid, ['action' => 'continue', 'message' => $evidenceMsg]);
    if ($last['http'] !== 200) {
        fail('evidence/reasoning turn', $last);
    }
    $phase = (string) ($last['data']['phase'] ?? '');
    $turns++;
    echo "turn {$turns} phase={$phase}\n";
}

if ($phase !== 'hammer') {
    fail('never reached hammer', ['phase' => $phase, 'last' => $last['data']]);
}

$counter = (string) ($last['data']['counter_argument'] ?? '');
if (mb_strlen($counter) < 20) {
    fail('empty counter_argument', $last['data']);
}
echo "hammer OK len=" . mb_strlen($counter) . "\n";
echo 'counter snippet: ' . mb_substr($counter, 0, 80) . "…\n\n";
echo "PASS: hook → evidence → hammer for {$questCode}\n";
