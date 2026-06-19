<?php
/** 단일 news_id 경첩 재실행 (전체 verify 파일 덮어쓰지 않음) */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';

$nid = isset($argv[1]) && is_numeric($argv[1]) ? (int) $argv[1] : 546;
$outJson = $root . '/docs/P2_HINGE_' . $nid . '_RERUN.json';

function stripPlain(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
}

function loadContentOnly(int $newsId, $supabase): ?array
{
    $rows = $supabase->select('judgement_records', 'news_id=eq.' . $newsId . '&order=created_at.desc', 1) ?? [];
    if ($rows === []) {
        return null;
    }
    $human = is_string($rows[0]['human_output'] ?? null)
        ? json_decode($rows[0]['human_output'], true)
        : ($rows[0]['human_output'] ?? []);

    $content = stripPlain((string) ($human['content'] ?? ''));
    if ($content === '') {
        return null;
    }

    return [
        'title' => (string) ($human['title'] ?? ''),
        'content' => $content,
    ];
}

$systemPrompt = <<<'PROMPT'
당신은 the gist 기사의 "경첩"(핵심 긴장) 추출기입니다.
입력은 news.content(원문 AI 분석)만입니다. why_important 등 다른 필드는 없습니다.

다음 JSON만 출력하세요. **본문에 근거가 있는 내용만** — 근거 없으면 hinge: null, confidence: "low".

규칙:
1. hinge: 이 글이 흔드는 긴장을 **한 문장**으로. 반드시 "A이지만/그러나 B" 또는 "A이면서 동시에 B" 형태.
2. side_a: 사람들이 당연하게 여기는 것 — 통념 / 표면 현상 / 단순 서사 / **질문 프레임(글이 던지는 질문의 틀 자체, 예: "~가 좋은가 나쁜가")** 중 하나 (넓게). 본문 근거 필수. 글이 찬반·평가 질문으로 시작하면 side_a는 그 **질문 틀**을 그대로 쓸 것(서사로 바꾸지 말 것).
3. side_b: 본문이 따져서 드러내는 더 복잡하거나 반대되는 진실. 본문 근거 필수.
4. hook_student: 14세용 한 문장, side_a에서 시작 (질문형 OK).
5. shake_prompt: 학생이 A만 말했을 때 코치가 B쪽으로 흔드는 한 문장. **반드시 본문의 구체적 사fact 하나** 포함 (숫자·사건명·고유명사 등).
6. article_form: content **하단** "이 글은 ~에 게재된" 출처에서 FA 또는 Economist 읽기. 없으면 "unknown". **섹션 수로 추정 금지**.
7. 금지: 본문에 없는 사실 invent, why_important 추측, 주어 없는 추상문.

JSON:
{
  "news_id": 0,
  "hinge": "A이지만 B",
  "side_a": "...",
  "side_b": "...",
  "hook_student": "...",
  "shake_prompt": "...",
  "article_form": "FA|economist|unknown",
  "confidence": "high|medium|low",
  "notes": "불확실한 부분 한 줄"
}
PROMPT;

$humanSideA = [
    546 => '일본 재무장이 좋냐 나쁘냐',
];

$sb = eduSupabase();
$llm = eduLlm();
$article = loadContentOnly($nid, $sb);

if ($article === null) {
    fwrite(STDERR, "content 없음 news_id={$nid}\n");
    exit(1);
}

$userMessage = <<<USER
news_id: {$nid}
제목: {$article['title']}

--- content (추출 대상, why_important 없음) ---
{$article['content']}
USER;

echo "Rerun news_id={$nid}...\n";
$response = $llm->chat($systemPrompt, [
    ['role' => 'user', 'content' => $userMessage],
], 2048, 0.1);

if (isset($response['error'])) {
    fwrite(STDERR, json_encode($response, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}

$raw = (string) ($response['content'] ?? '');
$parsed = null;
if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
    $parsed = json_decode($m[0], true);
}

$payload = [
    'news_id' => $nid,
    'prompt_version' => 'side_a_question_frame_v1',
    'human_side_a' => $humanSideA[$nid] ?? null,
    'result' => $parsed,
    'raw' => $raw,
    'run_at' => date('c'),
];

file_put_contents($outJson, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Wrote {$outJson}\n";
echo json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
