<?php
/**
 * AI Workspace – SSE Streaming Chat Endpoint
 *
 * Admin-only. POST { message, conversation_id?, article_context? }
 * Returns Server-Sent Events with GPT 5.2 streaming tokens + RAG context.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

// ── CORS ────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── Project root & env ──────────────────────────────────
function findProjectRoot(): string {
    $rawCandidates = [
        __DIR__ . '/../../../',
        __DIR__ . '/../../',
        __DIR__ . '/../',
    ];
    foreach ($rawCandidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $dir = dirname($dir);
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
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

foreach ([
    $projectRoot . 'env.txt',
    $projectRoot . '.env',
    $projectRoot . '.env.production',
    dirname($projectRoot) . '/.env',
] as $f) {
    if (loadEnvFile($f)) break;
}

require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;
use Agents\Services\RAGService;

// ── Helpers ─────────────────────────────────────────────

function sendJsonError(string $msg, int $status = 400): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSSE(string $event, $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// ── GET handlers (대화 목록 / 메시지 로드) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    $action = $_GET['action'] ?? '';
    $supabaseGET = new SupabaseService([]);

    if (!$supabaseGET->isConfigured()) {
        echo json_encode([]);
        exit;
    }

    if ($action === 'list_conversations') {
        $rows = $supabaseGET->select('conversations', 'order=created_at.desc', 50);
        echo json_encode($rows ?? [], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get_messages') {
        $convId = $_GET['conversation_id'] ?? '';
        if ($convId === '') {
            echo json_encode([]);
            exit;
        }
        $rows = $supabaseGET->select(
            'messages',
            'conversation_id=eq.' . urlencode($convId) . '&order=created_at.asc',
            200
        );
        echo json_encode($rows ?? [], JSON_UNESCAPED_UNICODE);
        exit;
    }

    sendJsonError('Unknown action', 400);
}

// ── Only POST beyond this point ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('POST only', 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input || empty($input['message'])) {
    sendJsonError('message is required');
}

$message        = trim($input['message']);
$conversationId = $input['conversation_id'] ?? null;
$articleContext  = $input['article_context'] ?? null; // { title, url, content, narration, analysis }
$history        = $input['history'] ?? [];           // previous messages [{role, content}]

// ── Init services ───────────────────────────────────────
$openai   = new OpenAIService([]);
$supabase = new SupabaseService([]);
$rag      = new RAGService($openai, $supabase);

// ── Build system prompt ─────────────────────────────────
$systemPrompt = <<<'PROMPT'
당신은 외교·안보·국제정치 전문 뉴스 편집자 AI입니다.
편집자가 기사를 분석하고 개선하는 것을 돕습니다.

역할:
- 기사 분석의 품질을 평가하고 개선점을 제안합니다
- 내레이션의 톤과 정확성을 점검합니다
- 누락된 관점이나 맥락을 보완합니다
- 편집자가 선택한 텍스트를 다시 작성합니다
- 외교/정책 도메인의 전문 지식을 활용합니다

응답 언어: 한국어 (편집자가 영어로 질문하면 영어로 답변)
PROMPT;

// Inject article context
if ($articleContext) {
    $ctx = "\n\n--- 현재 기사 컨텍스트 ---\n";
    if (!empty($articleContext['title'])) {
        $ctx .= "제목: {$articleContext['title']}\n";
    }
    if (!empty($articleContext['url'])) {
        $ctx .= "URL: {$articleContext['url']}\n";
    }
    if (!empty($articleContext['analysis'])) {
        $analysisText = $articleContext['analysis'];
        // 프론트에서 JSON.stringify된 객체가 올 수 있음
        if (is_string($analysisText)) {
            $decoded = json_decode($analysisText, true);
            if (is_array($decoded)) {
                $analysisText = $decoded['content_summary'] ?? $decoded['narration'] ?? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        } elseif (is_array($analysisText)) {
            $analysisText = $analysisText['content_summary'] ?? $analysisText['narration'] ?? json_encode($analysisText, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $ctx .= "GPT 분석 요약:\n{$analysisText}\n";
    }
    if (!empty($articleContext['narration'])) {
        $ctx .= "내레이션:\n" . mb_substr($articleContext['narration'], 0, 1000) . "\n";
    }
    $systemPrompt .= $ctx;
}

// RAG: retrieve relevant critiques/analyses
if ($rag->isConfigured()) {
    $ragContext = $rag->retrieveRelevantContext($message, 3);
    $systemPrompt = $rag->buildSystemPromptWithRAG($systemPrompt, $ragContext);
}

// Build user input with conversation history
$userInput = '';
if (!empty($history)) {
    foreach ($history as $msg) {
        $role = ($msg['role'] ?? 'user') === 'assistant' ? 'Assistant' : 'User';
        $userInput .= "[{$role}]: {$msg['content']}\n\n";
    }
}
$userInput .= "[User]: {$message}";

// ── SSE streaming ───────────────────────────────────────
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // nginx buffering off

// Disable output buffering
while (ob_get_level()) {
    ob_end_flush();
}

sendSSE('start', ['conversation_id' => $conversationId]);

$fullResponse = '';

$openai->chatStream(
    $systemPrompt,
    $userInput,
    function (string $delta) use (&$fullResponse) {
        $fullResponse .= $delta;
        sendSSE('token', ['text' => $delta]);
    },
    function (string $full) use ($supabase, &$conversationId, $message, $articleContext) {
        // Save to Supabase if configured
        if ($supabase->isConfigured()) {
            // Create conversation if needed
            if (!$conversationId) {
                $title = mb_substr($message, 0, 50);
                $convRows = $supabase->insert('conversations', [
                    'admin_user' => 'admin',
                    'title' => $title,
                    'article_url' => $articleContext['url'] ?? null,
                    'context' => $articleContext ?? [],
                ]);
                $conversationId = $convRows[0]['id'] ?? null;
            }

            if ($conversationId) {
                // Save user message
                $supabase->insert('messages', [
                    'conversation_id' => $conversationId,
                    'role' => 'user',
                    'content' => $message,
                ]);
                // Save assistant response
                $supabase->insert('messages', [
                    'conversation_id' => $conversationId,
                    'role' => 'assistant',
                    'content' => $full,
                ]);
            }
        }

        sendSSE('done', [
            'conversation_id' => $conversationId,
            'full_text' => $full,
        ]);
    },
    ['max_tokens' => 4000, 'timeout' => 180]
);

sendSSE('end', []);
