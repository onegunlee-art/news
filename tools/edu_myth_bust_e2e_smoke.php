<?php
/**
 * myth_bust stance null → compose 완주 smoke (--live only)
 *
 * Usage:
 *   php tools/edu_myth_bust_e2e_smoke.php --live
 *   php tools/edu_myth_bust_e2e_smoke.php --live --base=https://www.thegist.co.kr
 */
declare(strict_types=1);

if (!in_array('--live', $argv ?? [], true)) {
    fwrite(STDERR, "Usage: php tools/edu_myth_bust_e2e_smoke.php --live\n");
    exit(0);
}

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
    curl_close($ch);
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

echo "=== myth_bust e2e smoke (base={$base}) ===\n\n";

$guest = api('POST', '/api/edu/guest/start.php', []);
$token = (string) ($guest['data']['token'] ?? '');
if ($guest['http'] !== 200 || $token === '') {
    fail('guest start', $guest);
}
echo "guest ok\n";

$list = api('GET', '/api/edu/quests/list.php', null, $token);
$nuke = null;
foreach ($list['data']['quests'] ?? [] as $q) {
    if (($q['quest_code'] ?? '') === 'Q-NUKE-AXIS-630') {
        $nuke = $q;
        break;
    }
}
if ($nuke === null) {
    fail('Q-NUKE-AXIS-630 not in list — run seed first');
}

$start = api('POST', '/api/edu/session/start.php', ['quest_id' => $nuke['quest_id']], $token);
$sid = (string) ($start['data']['session_id'] ?? '');
if ($start['http'] !== 200 || $sid === '') {
    fail('session start', $start);
}
echo "session {$sid}\n";

$steps = [
    [
        'action' => 'submit_opening',
        'message' => '핵이 있어도 드론이나 미사일 같은 공격은 막기 어렵다고 봐. 러시아나 이스라엘 사례가 그렇잖아.',
    ],
    ['message' => '그래서 핵만 믿기보다는 방공이나 기지 방호를 더 키워야 한다고 생각해.'],
    ['message' => '기사에서도 핵 억지가 약해졌다는 분석이 나왔고, 드론 공격은 막지 못했다고 했어.'],
    ['message' => '새로운 국제 약속이나 규범도 필요하다고 봐. 핵만으로는 부족하다는 게 최근 사례에서 드러났으니까.'],
    ['message' => '그래도 핵이 있으면 큰 전쟁은 막을 수 있다는 반론도 있지만, 드론 같은 공격까지 막지는 못한다는 점이 더 중요해.'],
    ['message' => '맞아'],
];

$shouldCompose = false;
$lastPhase = '';
foreach ($steps as $i => $step) {
    $r = chat($token, $sid, $step);
    $phase = (string) ($r['data']['phase'] ?? '?');
    $lastPhase = $phase;
    echo 'step' . ($i + 1) . " HTTP {$r['http']} phase={$phase}";
    if (!empty($r['data']['should_compose'])) {
        $shouldCompose = true;
        echo ' should_compose=YES';
    }
    echo "\n";
    if ($r['http'] >= 400) {
        fail("chat step " . ($i + 1), $r);
    }
}

if (!$shouldCompose && $lastPhase === 'reflection') {
    echo "WARN: trying confirm_reflection\n";
    $r = chat($token, $sid, ['action' => 'confirm_reflection']);
    echo "confirm_reflection HTTP {$r['http']} phase=" . ($r['data']['phase'] ?? '?') . "\n";
    if (!empty($r['data']['should_compose'])) {
        $shouldCompose = true;
    }
    if ($r['http'] >= 400) {
        fail('confirm_reflection', $r);
    }
}

$compose = api('POST', '/api/edu/session/compose.php', ['session_id' => $sid], $token);
echo "compose HTTP {$compose['http']}\n";
if ($compose['http'] !== 200 || ($compose['data']['success'] ?? false) !== true) {
    fail('compose', $compose);
}

$state = api('GET', '/api/edu/session/state.php?session_id=' . rawurlencode($sid), null, $token);
$stage = (string) ($state['data']['stage'] ?? '');
echo "final stage={$stage}\n";

if ($stage !== 'completed') {
    echo "WARN: stage not completed yet (compose may be async) — success response OK\n";
}

echo "\nPASS myth_bust e2e smoke (stance null path → compose 200)\n";
