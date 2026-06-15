<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduBlueprint.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\EduLlmJson;

eduLoadAgents();

$sessionId = $argv[1] ?? '852bfa06-084c-465e-ac02-91d6ef4fd7d6';

function diagnoseRawCall(OpenAIService $openai, string $model, string $system, string $user, int $maxTokens, ?float $temp): array
{
    $payload = [
        'model' => $model,
        'instructions' => $system,
        'input' => $user,
        'max_output_tokens' => $maxTokens,
    ];
    if ($temp !== null && !str_starts_with($model, 'gpt-5')) {
        $payload['temperature'] = $temp;
    }

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY'),
        ],
        CURLOPT_TIMEOUT => 180,
    ]);

    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $data = is_string($raw) ? json_decode($raw, true) : null;
    $text = '';
    if (is_array($data)) {
        foreach ($data['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'output_text') {
                    $text .= $block['text'] ?? '';
                }
            }
        }
    }

    return [
        'http' => $http,
        'text' => $text,
        'status' => $data['status'] ?? 'unknown',
        'usage' => $data['usage'] ?? [],
        'incomplete' => $data['incomplete_details'] ?? null,
        'api_error' => $data['error'] ?? null,
    ];
}

$supabase = eduSupabase();
$session = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId, 1)[0] ?? null;
if ($session === null) {
    fwrite(STDERR, "not found\n");
    exit(1);
}

$blueprint = eduLoadBlueprint($session);
$dialogue = eduLoadDialogue($session);
$quest = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1)[0] ?? [];
$quest['articles'] = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $session['quest_id'], 20) ?? [];

echo "=== Step1 diagnose {$sessionId} ===\n";
echo 'stance=' . ($blueprint['stance'] ?? '') . " stage=" . ($session['stage'] ?? '') . "\n";
echo 'reason: ' . ($blueprint['reason'] ?? '') . "\n";
echo 'evidence: ' . ($blueprint['evidence'] ?? '') . "\n";
echo 'essay_structure saved: ' . (empty($blueprint['essay_structure']['sections']) ? 'NO' : 'YES') . "\n\n";

$llm = eduLlm();
$composer = new GistStyleComposer($llm);

$stance = (string) ($blueprint['final_stance'] ?? $blueprint['stance'] ?? 'pro');
$stanceLabel = $stance === 'pro' ? '찬성' : '반대';
$dialogueText = '';
foreach ($dialogue as $turn) {
    if (($turn['role'] ?? '') !== 'student') {
        continue;
    }
    $content = trim((string) ($turn['content'] ?? ''));
    if ($content !== '') {
        $dialogueText .= "학생: {$content}\n";
    }
}
$reflectionLines = $blueprint['reflection_lines'] ?? [];
if (!is_array($reflectionLines)) {
    $reflectionLines = [];
}
$reflectionText = implode("\n", array_map('strval', $reflectionLines));

$ctx = [
    'stance_label' => $stanceLabel,
    'reason' => (string) ($blueprint['reason'] ?? ''),
    'evidence' => (string) ($blueprint['evidence'] ?? ''),
    'counter_argument' => (string) ($blueprint['counter_argument'] ?? ''),
    'rebuttal' => (string) ($blueprint['rebuttal'] ?? ''),
    'dialogue_text' => $dialogueText,
];
$systemPrompt = <<<'PROMPT'
너는 the gist 편집장이다. 학생과의 대화를 바탕으로 **글 구조도**만 만든다.
학생이 말한 생각·근거·반론 반응을 섹션별로 배치하되, 아직 본문은 쓰지 않는다.

구조도 규칙:
- title: 학생 시각이 드러나는 제목 (the gist 헤드라인 톤)
- subtitle: 한 줄 핵심 요약
- sections: 3~4개, 각각 heading(소제목) + bullets(이 섹션에 넣을 핵심 생각 2~3개, 학생 발화 기반)
- conclusion_heading: "결론" 또는 맥락에 맞는 마무리 제목
- conclusion_bullets: 결론에 담을 핵심 2~3개

JSON만 응답:
{
  "title": "...",
  "subtitle": "...",
  "sections": [
    {"heading": "...", "role": "background|tension|stance|counter", "bullets": ["...", "..."]}
  ],
  "conclusion_heading": "결론",
  "conclusion_bullets": ["...", "..."]
}
PROMPT;

$userMessage = <<<MSG
퀘스트: {$quest['quest_title']}
배경: {$quest['alignment_summary']}
갈등: {$quest['conflict_summary']}
학생 입장: {$ctx['stance_label']}
이유: {$ctx['reason']}
근거: {$ctx['evidence']}
들은 반론: {$ctx['counter_argument']}
반론에 대한 생각: {$ctx['rebuttal']}
3줄 정리:
{$reflectionText}

대화:
{$ctx['dialogue_text']}
MSG;

echo "user_message_chars=" . mb_strlen($userMessage) . "\n";
echo "dialogue_student_turns=" . substr_count($ctx['dialogue_text'], "학생:") . "\n\n";

$openai = new OpenAIService([]);
$fastModel = getenv('EDU_OPENAI_FAST_MODEL') ?: 'gpt-5.4-mini';

foreach ([700, 2000] as $max) {
    echo ">>> max_output_tokens={$max}\n";
    $call = diagnoseRawCall($openai, $fastModel, $systemPrompt, $userMessage, $max, null);
    echo "http={$call['http']} status={$call['status']}\n";
    if (!empty($call['incomplete'])) {
        echo 'incomplete: ' . json_encode($call['incomplete'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (!empty($call['api_error'])) {
        echo 'api_error: ' . json_encode($call['api_error'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo 'usage: ' . json_encode($call['usage'], JSON_UNESCAPED_UNICODE) . "\n";
    echo 'text_len=' . mb_strlen($call['text']) . ' ends_with_brace=' . (str_ends_with(trim($call['text']), '}') ? 'Y' : 'N') . "\n";
    $parsed = EduLlmJson::parse(['content' => $call['text']]);
    echo 'parse=' . ($parsed !== null ? 'OK' : 'FAIL') . "\n";
    echo "--- RAW ---\n" . $call['text'] . "\n--- END ---\n\n";
}

echo ">>> previewStructure() production path (may need MySQL for full context)\n";
try {
    $r = $composer->previewStructure($blueprint, $quest, $dialogue);
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    echo 'previewStructure error: ' . $e->getMessage() . "\n";
}
