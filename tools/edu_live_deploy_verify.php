<?php
/**
 * 배포 후 라이브 compose/adversarial 스모크 검증
 * Usage: php tools/edu_live_deploy_verify.php [--compose-only] [--adversarial-only]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once __DIR__ . '/edu_compose_bootstrap_gate.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduConfig.php';
require_once $root . '/public/api/edu/lib/eduQuest.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();

use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\Agents\Hammer;

$base = 'https://www.thegist.co.kr';
$flags = $argv ?? [];
$composeOnly = in_array('--compose-only', $flags, true);
$adversarialOnly = in_array('--adversarial-only', $flags, true);

function httpJson(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $hdrs = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_TIMEOUT => 180,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return ['http' => $code, 'data' => is_array($data) ? $data : [], 'raw' => is_string($raw) ? $raw : ''];
}

echo "=== EDU Live Deploy Verify ===\n\n";

echo "--- compose.php bootstrap (live HTTP, no token) ---\n";
$bootstrap = httpJson('POST', $base . '/api/edu/session/compose.php', ['session_id' => 'x']);
$gateErr = eduComposeBootstrapGateError(['http' => $bootstrap['http'], 'raw' => $bootstrap['raw']]);
if ($gateErr !== null) {
    echo "FAIL: {$gateErr}\n";
    if (trim($bootstrap['raw']) !== '') {
        echo substr(trim($bootstrap['raw']), 0, 200) . "\n";
    }
    exit(1);
}
echo "compose_bootstrap: OK (HTTP 401 JSON)\n\n";

// 1) today 퀘스트
$today = httpJson('GET', $base . '/api/edu/quests/today.php');
$questCode = $today['data']['quest']['quest_code'] ?? '?';
echo "[today] HTTP {$today['http']} quest_code={$questCode}\n";

// 2) Supabase 이란 퀘스트 상태
$supabase = eduSupabase();
$iranRows = $supabase->select('edu_daily_quests', 'quest_code=eq.Q-IRAN-FOREVER-001', 1);
$iran = $iranRows[0] ?? [];
echo '[iran_db] status=' . ($iran['status'] ?? '?') . ' live_at=' . ($iran['live_at'] ?? 'null') . "\n\n";

if (!$adversarialOnly) {
    echo "--- LIVE compose (이란 fixture, 서버 MySQL+RAG) ---\n";
    $counterArgument = '네가 말한 이란 국민이 미국편에서 멀어지고 있다는 포인트는 설득력이 있어요. 다만 한 번 더 구분해 보면 좋겠어요.';
    $quest = [
        'quest_code' => 'Q-IRAN-FOREVER-001',
        'quest_title' => (string) ($iran['quest_title'] ?? '이란 전쟁, 정말 끝낼 수 있을까?'),
        'alignment_summary' => (string) ($iran['alignment_summary'] ?? ''),
        'conflict_summary' => (string) ($iran['conflict_summary'] ?? ''),
        'articles' => [
            ['news_id' => 555, 'role' => 'primary'],
            ['news_id' => 422, 'role' => 'context'],
            ['news_id' => 528, 'role' => 'context'],
        ],
    ];
    $blueprint = [
        'stance' => 'pro', 'final_stance' => 'pro',
        'reason' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지',
        'evidence' => '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지. 기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아',
        'rebuttal' => '전쟁은 원래 의도와 상관없이 얽히는거 같아',
        'counter_argument' => $counterArgument,
        'reflection_lines' => ['너는 이란 민심 변화가 중요하다고 봤어', '너는 반론 뒤 전쟁의 복잡성을 더 생각했어', '너는 pro를 지키며 신념을 더 단단히 했어'],
        'reflection_confirmed' => true,
    ];
    $dialogue = [
        ['role' => 'student', 'content' => '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.'],
        ['role' => 'student', 'content' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지'],
        ['role' => 'student', 'content' => '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지'],
        ['role' => 'student', 'content' => '기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아'],
        ['role' => 'student', 'content' => '전쟁은 원래 의도와 상관없이 얽히는거 같아'],
    ];

    $llm = eduLlm();
    $composer = new GistStyleComposer($llm);
    $t0 = microtime(true);
    $result = $composer->compose($blueprint, $quest, $dialogue);
    $sec = round(microtime(true) - $t0, 1);

    if (isset($result['success']) && $result['success'] === false) {
        echo "COMPOSE FAIL: " . ($result['message'] ?? '') . "\n";
        exit(1);
    }
    $full = trim((string) ($result['full_text'] ?? ''));
    echo "compose_ok elapsed={$sec}s len=" . mb_strlen($full) . "\n";
    echo "title: " . ($result['title'] ?? '') . "\n";
    $checks = [
        '민심' => str_contains($full, '민심') || str_contains($full, '국민'),
        '베트남' => str_contains($full, '베트남'),
        'conflict복붙' => str_contains($full, (string) ($quest['conflict_summary'] ?? '')),
        'Hammer복붙' => str_contains($full, '한 번 더 구분해 보면'),
    ];
    foreach ($checks as $k => $v) {
        echo ($v ? '[HIT]' : '[ok]') . " {$k}\n";
    }
    echo "\n--- full_text ---\n{$full}\n\n";
}

if (!$composeOnly) {
    echo "--- adversarial Hammer (mode 없음 퀘스트) ---\n";
    $advQuest = [
        'quest_title' => '중국 정부의 AI에 대한 기대와 고민?',
        'pro_line' => '찬성 라인',
        'con_line' => '반대 라인',
        'conflict_summary' => 'AI 규제 갈등',
        'hammer_hints' => ['con' => '규제 완화 필요', 'pro' => '안전 우선'],
    ];
    $mixup = eduBuildMixupContext($advQuest, null);
    $payload = eduHammerPayload($advQuest, 'pro');
    echo 'mixup_mode_default: ' . (($payload['mode'] ?? 'adversarial') === 'convergent' ? 'WRONG' : 'adversarial OK') . "\n";
    echo 'mixup_rag_skipped: ' . ($mixup['mixup_context'] === '' ? 'yes (no rag client)' : 'has context') . "\n";

    $llm = eduLlm();
    $hammer = new Hammer($llm);
    $strike = $hammer->strike('pro', 'AI는 일자리를 대체할 수 있어요', $advQuest, 'medium', [], $mixup);
    $mode = $strike['mode'] ?? '?';
    echo "hammer_mode={$mode}\n";
    $hasCounter = trim((string) ($strike['counter_argument'] ?? '')) !== '';
    echo 'counter_argument: ' . ($hasCounter ? 'OK' : 'EMPTY') . "\n";
    if ($mode === 'convergent' || $mode === 'convergent_meta_ask') {
        echo "FAIL: adversarial quest got convergent mode\n";
        exit(1);
    }
}

echo "\n=== VERIFY PASS ===\n";
