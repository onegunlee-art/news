<?php
/**
 * P1-2j — Live reasoning phase trace (3 quest types)
 *
 * 배포 후 육안 확인용 + 자동 phase/ui_hint 로그.
 *
 * Usage:
 *   php tools/edu_chat_reasoning_phase_live_trace.php --live
 *   php tools/edu_chat_reasoning_phase_live_trace.php --live --base=https://www.thegist.co.kr
 */
declare(strict_types=1);

if (!in_array('--live', $argv ?? [], true)) {
    fwrite(STDERR, "Usage: php tools/edu_chat_reasoning_phase_live_trace.php --live\n");
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
    $data = is_string($raw) ? json_decode($raw, true) : null;

    return ['http' => $http, 'data' => is_array($data) ? $data : [], 'raw' => (string) $raw];
}

function chat(string $token, string $sid, array $payload): array
{
    return api('POST', '/api/edu/session/chat.php', array_merge(['session_id' => $sid], $payload), $token);
}

function findQuest(string $token, string $questCode): ?array
{
    $list = api('GET', '/api/edu/quests/list.php?limit=50', null, $token);
    foreach ($list['data']['quests'] ?? [] as $q) {
        if (($q['quest_code'] ?? '') === $questCode) {
            return $q;
        }
    }

    return null;
}

function startSession(string $token, string $questId): string
{
    $start = api('POST', '/api/edu/session/start.php', ['quest_id' => $questId], $token);
    $sid = (string) ($start['data']['session_id'] ?? '');
    if ($start['http'] !== 200 || $sid === '') {
        fwrite(STDERR, "FAIL session start: {$start['raw']}\n");
        exit(1);
    }

    return $sid;
}

function logTurn(string $label, array $r): string
{
    $phase = (string) ($r['data']['phase'] ?? '?');
    $uiHint = (string) ($r['data']['ui_hint'] ?? '');
    $coach = mb_substr((string) ($r['data']['assistant_message'] ?? ''), 0, 100);
    echo "  [{$label}] HTTP {$r['http']} phase={$phase} ui_hint={$uiHint}\n";
    echo "    coach: {$coach}…\n";

    return $phase;
}

$pass = 0;
$fail = 0;

function gate(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

echo "=== reasoning phase live trace (P1-2j) base={$base} ===\n\n";

$guest = api('POST', '/api/edu/guest/start.php', []);
$token = (string) ($guest['data']['token'] ?? '');
if ($guest['http'] !== 200 || $token === '') {
    fwrite(STDERR, "FAIL guest\n");
    exit(1);
}

// --- 1. myth_bust (nuke): opening → reasoning followup or evidence ---
echo "--- Q-NUKE-AXIS-630 (myth_bust / open_response) ---\n";
$nuke = findQuest($token, 'Q-NUKE-AXIS-630');
if ($nuke === null) {
    gate('nuke in list', false);
} else {
    $sid = startSession($token, (string) $nuke['quest_id']);
    $r = chat($token, $sid, [
        'action' => 'submit_opening',
        'message' => '핵이 있어도 드론 공격은 막기 어렵다고 봐. 좀 더 생각해볼게.',
    ]);
    $phase = logTurn('opening', $r);
    gate('nuke opening HTTP 200', $r['http'] === 200);
    gate('nuke post-opening phase reasoning or evidence', in_array($phase, ['reasoning', 'evidence'], true));

    if ($phase === 'reasoning') {
        $r2 = chat($token, $sid, ['message' => '러시아나 이스라엘 사례처럼 핵만으로는 작은 공격을 못 막는다고 봐.']);
        $phase2 = logTurn('reasoning-1', $r2);
        gate('nuke reasoning-1 HTTP 200', $r2['http'] === 200);
        gate('nuke reasoning-1 advances to evidence or stays reasoning', in_array($phase2, ['reasoning', 'evidence'], true));
    } else {
        gate('nuke skipped reasoning followup (direct evidence OK)', true);
        gate('nuke reasoning-1 N/A', true);
    }
}

// --- 2. Japan decision ---
echo "\n--- Q-G09-DEC-2022 (decision / stance_pick) ---\n";
$japan = findQuest($token, 'Q-G09-DEC-2022');
if ($japan === null) {
    gate('japan in list', false);
} else {
    $sid = startSession($token, (string) $japan['quest_id']);
    $r = chat($token, $sid, ['action' => 'select_stance', 'stance' => 'con']);
    $phase = logTurn('select_stance', $r);
    gate('japan stance HTTP 200', $r['http'] === 200);
    gate('japan after stance phase reasoning', $phase === 'reasoning');

    $r2 = chat($token, $sid, ['message' => '일본이 미사일을 갖추는 건 주변 나라를 더 불안하게 만들 수 있어서 위험하다고 봐.']);
    $phase2 = logTurn('reasoning-1', $r2);
    gate('japan reasoning-1 HTTP 200', $r2['http'] === 200);
    gate('japan reasoning-1 phase reasoning or evidence', in_array($phase2, ['reasoning', 'evidence'], true));

    if ($phase2 === 'reasoning') {
        $r3 = chat($token, $sid, ['message' => '대만·중국 긴장이 커지면서 일본이 먼저 공격받을까 봐 무기를 키운 것 같아.']);
        $phase3 = logTurn('reasoning-2', $r3);
        gate('japan reasoning-2 HTTP 200', $r3['http'] === 200);
        gate('japan reasoning-2 reaches evidence', $phase3 === 'evidence');
    } else {
        gate('japan reasoning-2 N/A (early evidence)', true);
        gate('japan reasoning-2 reaches evidence', true);
    }
}

// --- 3. Iran convergent (R4 quest) ---
echo "\n--- Q-IRAN-FOREVER-001 (convergent / stance_pick) ---\n";
$iran = findQuest($token, 'Q-IRAN-FOREVER-001');
if ($iran === null) {
    gate('iran in list', false);
} else {
    $sid = startSession($token, (string) $iran['quest_id']);
    $r = chat($token, $sid, ['action' => 'select_stance', 'stance' => 'con']);
    $phase = logTurn('select_stance', $r);
    gate('iran stance HTTP 200', $r['http'] === 200);
    gate('iran after stance phase reasoning', $phase === 'reasoning');

    $r2 = chat($token, $sid, ['message' => '아무리 폭격해도 이란은 쉽게 안 굴복할 거 같아.']);
    $phase2 = logTurn('reasoning-1', $r2);
    gate('iran reasoning-1 HTTP 200', $r2['http'] === 200);
    gate('iran reasoning-1 phase reasoning or evidence', in_array($phase2, ['reasoning', 'evidence'], true));

    if ($phase2 === 'reasoning') {
        $r3 = chat($token, $sid, ['message' => '결국 이란 국민이 미국 편에서 멀어지는 게 더 중요한 이유인 것 같아.']);
        $phase3 = logTurn('reasoning-2', $r3);
        gate('iran reasoning-2 HTTP 200', $r3['http'] === 200);
        gate('iran reasoning-2 reaches evidence', $phase3 === 'evidence');
    } else {
        gate('iran reasoning-2 N/A', true);
        gate('iran reasoning-2 reaches evidence', true);
    }
}

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
echo "\n[육안 확인] 위 coach 메시지가 이상한 질문/끊김 없이 자연스러운지 브라우저에서도 한 번씩 확인하세요.\n";
exit($fail > 0 ? 1 : 0);
