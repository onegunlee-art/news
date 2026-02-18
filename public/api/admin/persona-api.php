<?php
/**
 * GPT Persona API
 *
 * - GET: list personas, get active
 * - POST action=chat: SSE streaming (Persona Playground)
 * - POST action=extract_and_save: 대화 → GPT 추출 → DB 저장
 * - POST action=test_consistency: 실제 분석 실행 + 일관성 체크리스트
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function findProjectRoot(): string {
    $rawCandidates = [__DIR__ . '/../../../', __DIR__ . '/../../', __DIR__ . '/../'];
    foreach ($rawCandidates as $raw) {
        $path = realpath($raw) ?: rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    for ($i = 0, $dir = __DIR__; $i < 6; $i++, $dir = dirname($dir)) {
        if (file_exists($dir . '/src/agents/autoload.php')) {
            return rtrim($dir, '/\\') . '/';
        }
    }
    throw new \RuntimeException('Project root not found');
}

function loadEnvFile(string $path): bool {
    if (!is_file($path) || !is_readable($path)) return false;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

try {
    $projectRoot = findProjectRoot();
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production', dirname($projectRoot) . '/.env'] as $f) {
    if (loadEnvFile($f)) break;
}

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;
use Agents\Services\PersonaService;
use Agents\Services\RAGService;
use Agents\Pipeline\AgentPipeline;

function sendJson(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSSE(string $event, $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// ── GET: list / active ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $personaService = new PersonaService(new SupabaseService([]));
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $personas = $personaService->listPersonas(50);
        sendJson(['success' => true, 'personas' => $personas]);
    }

    if ($action === 'active') {
        $persona = $personaService->getActivePersona();
        sendJson(['success' => true, 'persona' => $persona]);
    }

    sendJson(['success' => false, 'error' => 'Unknown action']);
}

// ── POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'error' => 'POST only']);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $input['action'] ?? '';

// ── action=chat: Persona Playground SSE ─────────────────
if ($action === 'chat') {
    $message = trim($input['message'] ?? '');
    if ($message === '') {
        sendJson(['success' => false, 'error' => 'message required']);
    }

    $history = $input['history'] ?? [];
    $openai = new OpenAIService([]);

    $systemPrompt = <<<'PROMPT'
당신은 "페르소나 정의" 모드입니다. 사용자가 The Gist의 GPT 에디터 페르소나를 대화로 정의합니다.
당신은 질문에 답하고, 톤·스타일·원칙을 함께 구체화해주세요. 정의가 끝나면 사용자가 "저장"을 요청할 것입니다.
PROMPT;

    $userInput = '';
    foreach ($history as $msg) {
        $role = ($msg['role'] ?? 'user') === 'assistant' ? 'Assistant' : 'User';
        $userInput .= "[{$role}]: {$msg['content']}\n\n";
    }
    $userInput .= "[User]: {$message}";

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    while (ob_get_level()) ob_end_flush();

    sendSSE('start', []);
    $fullResponse = '';

    $openai->chatStream(
        $systemPrompt,
        $userInput,
        function (string $delta) use (&$fullResponse) {
            $fullResponse .= $delta;
            sendSSE('token', ['text' => $delta]);
        },
        function (string $full) {
            sendSSE('done', ['full_text' => $full]);
        },
        ['max_tokens' => 4000, 'timeout' => 120]
    );
    sendSSE('end', []);
    exit;
}

// ── action=extract_and_save ─────────────────────────────
if ($action === 'extract_and_save') {
    $history = $input['history'] ?? [];
    $name = trim($input['name'] ?? 'The Gist 수석 에디터 v1');

    if (empty($history)) {
        sendJson(['success' => false, 'error' => 'history required']);
    }

    $conversationText = '';
    foreach ($history as $msg) {
        $role = ($msg['role'] ?? 'user') === 'assistant' ? 'Assistant' : 'User';
        $conversationText .= "[{$role}]: {$msg['content']}\n\n";
    }

    $extractPrompt = "위 대화에서 정의된 페르소나를 바탕으로, The Gist 뉴스 분석 GPT가 사용할 system prompt를 작성하세요.\n"
        . "- 500자 이내\n- 한국어\n- 역할, 톤, 원칙, 출력 형식 지시 포함\n"
        . "- 반드시 JSON만 응답: {\"system_prompt\": \"...\"}";

    $userPrompt = "=== 대화 내용 ===\n\n" . mb_substr($conversationText, 0, 8000) . "\n\n=== 요청 ===\n\n" . $extractPrompt;

    $openai = new OpenAIService([]);
    $response = $openai->chat(
        '당신은 system prompt 추출 전문가입니다. 요청된 JSON 형식으로만 응답하세요.',
        $userPrompt,
        ['max_tokens' => 1000, 'timeout' => 60]
    );

    $systemPrompt = '';
    if (preg_match('/\{[\s\S]*\}/', $response, $jsonMatch)) {
        $parsed = json_decode($jsonMatch[0], true);
        if (is_array($parsed) && !empty($parsed['system_prompt'])) {
            $systemPrompt = trim($parsed['system_prompt']);
        }
    }
    if (empty($systemPrompt)) {
        $systemPrompt = trim($response);
    }

    if (empty($systemPrompt)) {
        sendJson(['success' => false, 'error' => 'Could not extract system_prompt from GPT response']);
    }

    $personaService = new PersonaService(new SupabaseService([]));
    $saved = $personaService->savePersona($name, $systemPrompt, [
        'extracted_at' => date('c'),
        'source' => 'playground',
    ]);

    sendJson([
        'success' => true,
        'persona' => $saved,
        'system_prompt' => $systemPrompt,
        'message' => '페르소나가 저장되었습니다.',
    ]);
}

// ── action=test_consistency ─────────────────────────────
if ($action === 'test_consistency') {
    $url = trim($input['url'] ?? '');
    $articleId = isset($input['article_id']) ? (int) $input['article_id'] : 0;

    if ($url === '' && $articleId <= 0) {
        sendJson(['success' => false, 'error' => 'url or article_id required']);
    }

    if ($articleId > 0 && $url === '') {
        $dbConfig = require $projectRoot . 'config/database.php';
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
        try {
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'] ?? '');
            $stmt = $pdo->query("SELECT url, source_url FROM news WHERE id = " . (int) $articleId);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $url = trim($row['source_url'] ?? $row['url'] ?? '');
        } catch (\Throwable $e) {
            sendJson(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        }
        if ($url === '' || $url === '#') {
            sendJson(['success' => false, 'error' => 'Article has no URL']);
        }
    }

    $supabase = new SupabaseService([]);
    $ragService = $supabase->isConfigured() ? new RAGService(new OpenAIService([]), $supabase) : null;
    $personaService = new PersonaService($supabase);

    $googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php') ? require $projectRoot . 'config/google_tts.php' : [];
    $pipelineConfig = [
        'project_root' => rtrim($projectRoot, '/\\'),
        'openai' => [],
        'scraper' => ['timeout' => 60],
        'enable_interpret' => false,
        'enable_learning' => false,
        'google_tts' => $googleTtsConfig,
        'rag_service' => $ragService,
        'persona_service' => $personaService,
        'analysis' => [
            'enable_tts' => false,
            'persona_service' => $personaService,
        ],
        'stop_on_failure' => true,
    ];

    $pipeline = new AgentPipeline($pipelineConfig);
    $pipeline->setupDefaultPipeline();

    try {
        $result = $pipeline->run($url);
    } catch (\Throwable $e) {
        sendJson(['success' => false, 'error' => 'Pipeline error: ' . $e->getMessage()]);
    }

    if (!$result->isSuccess()) {
        sendJson(['success' => false, 'error' => 'Pipeline failed', 'result' => $result->toArray()]);
    }

    $finalAnalysis = $result->getFinalAnalysis();
    $narration = $finalAnalysis['narration'] ?? '';
    $contentSummary = $finalAnalysis['content_summary'] ?? '';
    $newsTitle = $finalAnalysis['news_title'] ?? '';

    $checklist = [
        'has_jister' => (bool) preg_match('/지스터/', $narration),
        'narration_min_900' => mb_strlen($narration) >= 900,
        'content_summary_min_600' => mb_strlen($contentSummary) >= 600,
        'has_news_title' => !empty(trim($newsTitle ?? '')),
        'has_key_points' => !empty($finalAnalysis['key_points'] ?? []),
    ];
    $score = array_sum(array_map('intval', $checklist));
    $checklist['score'] = $score . '/' . count($checklist);

    sendJson([
        'success' => true,
        'analysis_result' => [
            'news_title' => $newsTitle,
            'content_summary' => mb_substr($contentSummary, 0, 500) . (mb_strlen($contentSummary) > 500 ? '...' : ''),
            'narration' => mb_substr($narration, 0, 500) . (mb_strlen($narration) > 500 ? '...' : ''),
            'key_points' => $finalAnalysis['key_points'] ?? [],
        ],
        'checklist' => $checklist,
    ]);
}

// ── action=set_active ───────────────────────────────────
if ($action === 'set_active') {
    $personaId = trim($input['persona_id'] ?? '');
    if ($personaId === '') {
        sendJson(['success' => false, 'error' => 'persona_id required']);
    }
    $personaService = new PersonaService(new SupabaseService([]));
    $personaService->setActive($personaId);
    sendJson(['success' => true, 'message' => '활성 페르소나가 변경되었습니다.']);
}

sendJson(['success' => false, 'error' => 'Unknown action: ' . $action]);
