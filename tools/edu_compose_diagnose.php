<?php
/**
 * GIST EDU — Compose LLM 격리 진단 (P1 Step1)
 * 이란 라이브 세션 blueprint+dialogue 재현 → Step1/Step2 raw LLM 응답 + 파서 판정
 *
 * Usage:
 *   php tools/edu_compose_diagnose.php
 *   php tools/edu_compose_diagnose.php --step=2 --max-tokens=8000
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/_llm.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\EduLlmJson;

eduLoadAgents();

$stepFilter = null;
$maxTokensOverride = null;
$scenario = 'default';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--step=')) {
        $stepFilter = (int) substr($arg, 7);
    }
    if (str_starts_with($arg, '--max-tokens=')) {
        $maxTokensOverride = (int) substr($arg, 13);
    }
    if (str_starts_with($arg, '--scenario=')) {
        $scenario = substr($arg, 11);
    }
}

echo "=== Edu Compose LLM 격리 진단 (이란 세션 재현) ===\n\n";

// --- 이란 퀘스트 + 라이브 세션 blueprint/dialogue 재구성 ---
$quest = [
    'quest_code' => 'Q-IRAN-FOREVER-001',
    'quest_title' => '이란 전쟁, 정말 끝낼 수 있을까?',
    'pro_line' => '기술적 관점: 정밀타격 기술의 한계가 정치적 결말을 막는다 (Freedman)',
    'con_line' => '구조적 관점: 불안정한 봉합이 전쟁의 귀결이다 (Rose)',
    'alignment_summary' => '세 명의 전문가 모두 "이란 전쟁은 미국이 원하는 대로 깔끔하게 끝나지 않는다"는 데 동의한다. 군사적 우위가 정치적 승리로 이어지지 않는다는 공통 인식.',
    'conflict_summary' => '공동 결론: 이란 전쟁은 깔끔하게 끝나지 않는다. 그러나 "왜" 안 끝나는지에 대한 이유가 다르다 — 기술의 한계인가, 국내정치의 함정인가, 전쟁 구조 자체의 문제인가.',
    'grade_band' => 'high',
    'articles' => [
        ['news_id' => 555, 'role' => 'primary', 'title' => '이란과 영원한 전쟁의 함정'],
        ['news_id' => 422, 'role' => 'context', 'title' => '끝나지 않는 전쟁의 높은 대가'],
        ['news_id' => 528, 'role' => 'context', 'title' => '이란은 베트남처럼, 우크라이나는 한국처럼'],
    ],
];

$counterArgument = <<<'HAMMER'
네가 말한 **"이란 국민이 미국편에서 멀어지고 있다"**, 그리고 **"미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다"**는 포인트는 분명 설득력이 있어요. 다만 그 근거가 **이란이나 베트남처럼 특정 상대의 민심과 반응 때문에** 전쟁이 길어진다는 뜻인지, 아니면 그런 사례들을 통해 **전쟁은 원래 개입하는 쪽의 의도와 다르게 엉키면서 깔끔하게 끝나지 않는다는 점**을 말하고 싶은 건지 한 번 더 구분해 보면 좋겠어요.
HAMMER;

$blueprint = [
    'stance' => 'pro',
    'final_stance' => 'pro',
    'reason' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지',
    'evidence' => '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지. 기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아',
    'rebuttal' => '전쟁은 원래 의도와 상관없이 얽히는거 같아',
    'counter_argument' => $counterArgument,
    'reflection_lines' => [
        '너는 이란 민심 변화가 중요하다고 봤어',
        '너는 반론 뒤 전쟁의 복잡성을 더 생각했어',
        '너는 pro를 지키며 신념을 더 단단히 했어',
    ],
    'reflection_confirmed' => true,
];

$dialogue = [
    ['role' => 'assistant', 'content' => '정밀타격이 군사적 성과는 낼 수 있어도 정치적 결말까지 보장하진 않잖아...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '아무리 폭격해도 이란은 안 굴복해요. 미사일이 정확해도 의미없는 거 같아요.'],
    ['role' => 'assistant', 'content' => '좋아요, 그럼 굴복이 정치적 결정의 변화라면...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '그냥 복잡한 상황이라서 쉽게 안 끝날 것 같아요.'],
    ['role' => 'assistant', 'content' => '그 복잡함이 정밀타격으로는 해결하기 어렵다면...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '결국 가장 중요한건 생각보다 이란 국민이 미국편에서 멀어지고 있다는 거지'],
    ['role' => 'assistant', 'content' => '기사에서 네 주장과 연결되는 근거를 2~3개 찾아보자...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '이란 전쟁의 베트남전쟁과 비교되는것도 바로 그점이야 국민들이 미국에 저항을 한다는거지'],
    ['role' => 'assistant', 'content' => '좋아, 기사에서 본 구체적인 내용을 한 가지만 더...', 'agent' => 'socratic'],
    ['role' => 'student', 'content' => '기사에서 한국전은 열강의 세력 싸움이라면 베트남전쟁이 실패한 이유는 결국 미국의 정치적 계산이 베트남 국민들에게 반감을 가져서 오랫동안 전쟁을 했다는 점인거 같아'],
    ['role' => 'assistant', 'content' => $counterArgument . "\n\n이 반론에 대해 어떻게 생각해?", 'agent' => 'hammer'],
    ['role' => 'student', 'content' => '전쟁은 원래 의도와 상관없이 얽히는거 같아'],
    ['role' => 'assistant', 'content' => "지금까지 생각을 정리해볼게:\n너는 이란 민심 변화가 중요하다고 봤어\n너는 반론 뒤 전쟁의 복잡성을 더 생각했어\n너는 pro를 지키며 신념을 더 단단히 했어\n\n맞게 정리됐어?", 'agent' => 'reflection'],
    ['role' => 'student', 'content' => '맞아'],
];

$openai = new OpenAIService([]);

// MySQL 없이 로컬 격리 — buildContext와 동일한 dialogue_text만 재구성 (RAG/기사 발췌 생략)
$dialogueText = '';
foreach ($dialogue as $turn) {
    $role = ($turn['role'] ?? '') === 'student' ? '학생' : '코치';
    $dialogueText .= "{$role}: " . ($turn['content'] ?? '') . "\n";
}
$ctx = [
    'stance' => 'pro',
    'stance_label' => '찬성',
    'reason' => (string) ($blueprint['reason'] ?? ''),
    'evidence' => (string) ($blueprint['evidence'] ?? ''),
    'rebuttal' => (string) ($blueprint['rebuttal'] ?? ''),
    'counter_argument' => (string) ($blueprint['counter_argument'] ?? ''),
    'reflection_lines' => $blueprint['reflection_lines'],
    'dialogue_text' => $dialogueText,
    'quest' => $quest,
    'narration_block' => '(로컬 격리 — narration 생략)',
    'arc_alignment' => '(로컬 격리 — arc 생략)',
    'judgment_block' => '',
];

/**
 * OpenAI Responses API raw 호출 (status/usage/incomplete 포함)
 */
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
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

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
        'raw_json' => $data,
        'raw_body' => is_string($raw) ? $raw : '',
        'text' => $text,
        'status' => $data['status'] ?? 'unknown',
        'usage' => $data['usage'] ?? [],
        'incomplete' => $data['incomplete_details'] ?? null,
    ];
}

function classifyParse(string $content): array
{
    $parsed = EduLlmJson::parse(['content' => $content]);
    $jsonErr = '';
    if ($parsed === null && $content !== '') {
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            json_decode($m[0], true);
            $jsonErr = json_last_error_msg();
        } else {
            $jsonErr = 'no_json_object_found';
        }
    }

    $verdict = 'unknown';
    if ($content === '') {
        $verdict = 'c_empty_response';
    } elseif ($parsed !== null) {
        $verdict = 'ok_parsed';
    } elseif (str_contains($content, '```') && !str_ends_with(trim($content), '}')) {
        $verdict = 'c_likely_truncated';
    } elseif ($jsonErr === 'Syntax error' || str_contains($jsonErr, 'error')) {
        $last = substr(trim($content), -80);
        if (!str_ends_with(trim($content), '}')) {
            $verdict = 'c_likely_truncated';
        } else {
            $verdict = 'a_broken_json_from_llm';
        }
    } else {
        $verdict = 'b_parser_miss_or_no_json';
    }

    return [
        'parsed' => $parsed,
        'json_error' => $jsonErr,
        'verdict' => $verdict,
        'content_len' => mb_strlen($content),
        'ends_with_brace' => str_ends_with(trim($content), '}'),
        'tail' => mb_substr(trim($content), -120),
    ];
}

function printStep(string $name, array $call, array $classify, int $maxTokens): void
{
    echo str_repeat('=', 72) . "\n";
    echo "STEP: {$name}\n";
    echo "max_output_tokens: {$maxTokens}\n";
    echo "HTTP: {$call['http']}\n";
    echo "API status: {$call['status']}\n";
    $usage = $call['usage'];
    echo 'usage: input=' . ($usage['input_tokens'] ?? '?')
        . ' output=' . ($usage['output_tokens'] ?? '?')
        . ' total=' . ($usage['total_tokens'] ?? '?');
    if (!empty($usage['output_tokens_details'])) {
        echo ' output_details=' . json_encode($usage['output_tokens_details'], JSON_UNESCAPED_UNICODE);
    }
    echo "\n";
    if (!empty($call['incomplete'])) {
        echo 'incomplete_details: ' . json_encode($call['incomplete'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "content_len: {$classify['content_len']} ends_with_brace: " . ($classify['ends_with_brace'] ? 'yes' : 'NO') . "\n";
    echo "parse_verdict: {$classify['verdict']}\n";
    if ($classify['json_error'] !== '') {
        echo "json_last_error: {$classify['json_error']}\n";
    }
    echo "\n--- RAW TEXT (first 2000 chars) ---\n";
    echo mb_substr($call['text'], 0, 2000) . "\n";
    if ($classify['content_len'] > 2000) {
        echo "\n--- RAW TEXT (last 500 chars) ---\n";
        echo mb_substr($call['text'], -500) . "\n";
    }
    echo "\n";
}

// Step1 prompts (mirror GistStyleComposer::buildStructureDiagram)
$questTitle = $quest['quest_title'];
$reflectionText = implode("\n", $blueprint['reflection_lines']);
$step1System = <<<'PROMPT'
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

$step1User = <<<MSG
퀘스트: {$questTitle}
배경: {$quest['alignment_summary']}
갈등: {$quest['conflict_summary']}
학생 입장: 찬성
이유: {$blueprint['reason']}
근거: {$blueprint['evidence']}
들은 반론: {$blueprint['counter_argument']}
반론에 대한 생각: {$blueprint['rebuttal']}
3줄 정리:
{$reflectionText}

대화:
{$dialogueText}
MSG;

$fastModel = getenv('EDU_OPENAI_FAST_MODEL') ?: 'gpt-5.4-mini';
$mainModel = getenv('EDU_OPENAI_MODEL') ?: 'gpt-5.4';

$step1Max = 2000;
$step2Max = $maxTokensOverride ?? 2200;
$class1 = ['parsed' => null];

if ($stepFilter === null || $stepFilter === 1) {
    echo ">>> Step1 previewStructure / buildStructureDiagram (haiku {$step1Max})\n\n";
    $call1 = diagnoseRawCall($openai, $fastModel, $step1System, $step1User, $step1Max, null);
    $class1 = classifyParse($call1['text']);
    printStep('Step1 structure (production max=' . $step1Max . ')', $call1, $class1, $step1Max);

    if ($class1['verdict'] !== 'ok_parsed') {
        echo ">>> Step1 retry with max_output_tokens=2000\n\n";
        $call1b = diagnoseRawCall($openai, $fastModel, $step1System, $step1User, 2000, null);
        $class1b = classifyParse($call1b['text']);
        printStep('Step1 structure (retry max=2000)', $call1b, $class1b, 2000);
    }
}

if ($stepFilter === null || $stepFilter === 2) {
    // Build structure for Step2
    if ($scenario === 'fallback') {
        echo ">>> scenario=fallback (production previewStructure 실패 재현)\n\n";
        $structure = [
            'title' => '찬성 입장에서 본 ' . $questTitle,
            'subtitle' => (string) ($quest['conflict_summary'] ?? ''),
            'sections' => [
                [
                    'heading' => '왜 이 주제가 중요한가',
                    'role' => 'background',
                    'bullets' => array_filter([(string) ($quest['alignment_summary'] ?? ''), (string) ($blueprint['reason'] ?? '')]),
                ],
                [
                    'heading' => '견해가 갈리는 지점',
                    'role' => 'tension',
                    'bullets' => [(string) ($quest['conflict_summary'] ?? '')],
                ],
                [
                    'heading' => '나의 입장',
                    'role' => 'stance',
                    'bullets' => array_filter([(string) ($blueprint['reason'] ?? ''), (string) ($blueprint['evidence'] ?? '')]),
                ],
                [
                    'heading' => '다른 시각을 듣고',
                    'role' => 'counter',
                    'bullets' => array_filter([(string) ($blueprint['counter_argument'] ?? ''), (string) ($blueprint['rebuttal'] ?? '')]),
                ],
            ],
            'conclusion_heading' => '결론',
            'conclusion_bullets' => array_filter((array) ($blueprint['reflection_lines'] ?? [])),
            'student_stance' => '찬성',
        ];
        $ctx['narration_block'] = str_repeat("the gist 원문 발췌: 정밀타격과 민심, 전쟁의 복잡성에 대한 해설 문단. ", 80);
        $ctx['judgment_block'] = str_repeat("편집 패턴: 갈등 인정 후 수렴, 학생 시각 강조. ", 40);
        $ctx['arc_alignment'] = 'arc: 이란·베트남·한국전 비교 — 민심과 정치가 군사력을 압도하는 패턴';
    } else {
        $structure = $class1['parsed'] ?? [
            'title' => '찬성 입장에서 본 ' . $questTitle,
            'subtitle' => '이란 민심과 전쟁의 복잡성',
            'sections' => [
                ['heading' => '왜 중요한가', 'bullets' => [$blueprint['reason']]],
                ['heading' => '나의 근거', 'bullets' => [$blueprint['evidence']]],
                ['heading' => '반론 이후', 'bullets' => [$blueprint['rebuttal']]],
            ],
            'conclusion_heading' => '결론',
            'conclusion_bullets' => $blueprint['reflection_lines'],
        ];
    }

    $structureJson = json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $step2System = <<<'PROMPT'
너는 the gist 편집자다. **구조도를 뼈대로** 학생의 생각이 담긴 지정학 해설 글을 처음부터 쓴다.

the gist 톤 (필수):
- 명확한 제목·소제목, 각 섹션 2~3문단
- "~거든요", "~있어요", "~이에요" 존댓말 해설체
- 갈등·다른 시각 인정 후 학생 입장으로 수렴
- 학생 대화에 없는 새 사실·수치 추가 금지
- 학생이 쓴 문장을 그대로 복붙하지 말고, gist 리듬으로 **새로 서술**

JSON만 응답:
{
  "title": "...",
  "subtitle": "...",
  "sections": [
    {"heading": "소제목", "paragraphs": ["문단1", "문단2"]}
  ],
  "conclusion_heading": "결론",
  "conclusion_paragraphs": ["문단1", "문단2"],
  "full_text": "제목부터 결론까지 읽기 좋게 이어 붙인 전체 (소제목 포함)",
  "hero_sentence": "공유카드용 핵심 문장 1개"
}
PROMPT;

    $step2User = <<<MSG
구조도:
{$structureJson}

학생 최종 입장: {$ctx['stance_label']}

the gist 원문 톤 참고:
{$ctx['narration_block']}

arc 참고:
{$ctx['arc_alignment']}

편집 패턴:
{$ctx['judgment_block']}
MSG;

    echo ">>> Step2 composeArticleFromStructure (chat {$step2Max})\n\n";
    $call2 = diagnoseRawCall($openai, $mainModel, $step2System, $step2User, $step2Max, 0.55);
    $class2 = classifyParse($call2['text']);
    printStep('Step2 article (production max=' . $step2Max . ')', $call2, $class2, $step2Max);

    if ($class2['verdict'] !== 'ok_parsed') {
        echo ">>> Step2 retry with max_output_tokens=8000\n\n";
        $call2b = diagnoseRawCall($openai, $mainModel, $step2System, $step2User, 8000, 0.55);
        $class2b = classifyParse($call2b['text']);
        printStep('Step2 article (retry max=8000)', $call2b, $class2b, 8000);
    }
}

echo "=== 판정 범례 ===\n";
echo "ok_parsed          : LLM JSON 정상 + 파서 성공\n";
echo "a_broken_json      : LLM이 깨진 JSON 전송\n";
echo "b_parser_miss      : JSON 있으나 파서 실패\n";
echo "c_empty_response   : 빈 응답\n";
echo "c_likely_truncated : } 로 안 끝남 / incomplete / 토큰 잘림 의심\n";
echo "\n=== DONE ===\n";
