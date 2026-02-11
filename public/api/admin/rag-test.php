<?php
/**
 * RAG Test API – Admin-only retrieval test
 *
 * POST { query, top_k? } → { critiques, analyses }
 * GET  → Status check (Supabase/OpenAI configured)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(60);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function findProjectRoot(): string {
    $rawCandidates = [__DIR__.'/../../../', __DIR__.'/../../', __DIR__.'/../'];
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
        if (file_exists($dir . '/src/agents/autoload.php')) return rtrim($dir, '/\\') . '/';
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
            if ($name !== '') { putenv("$name=$value"); $_ENV[$name] = $value; }
        }
    }
    return true;
}

try {
    $projectRoot = findProjectRoot();
} catch (\Throwable $e) {
    ob_clean();
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
use Agents\Services\RAGService;

$openai = new OpenAIService([]);
$supabase = new SupabaseService([]);
$rag = new RAGService($openai, $supabase);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_clean();
    echo json_encode([
        'success' => true,
        'rag_configured' => $rag->isConfigured(),
        'openai_configured' => $openai->isConfigured(),
        'supabase_configured' => $supabase->isConfigured(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input || empty(trim($input['query'] ?? ''))) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'query is required']);
    exit;
}

$query = trim($input['query']);
$topK = (int) ($input['top_k'] ?? 5);
$topK = max(1, min(20, $topK));

if (!$rag->isConfigured()) {
    ob_clean();
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'RAG not configured (OpenAI + Supabase required)',
        'critiques' => [],
        'analyses' => [],
        'system_prompt_preview' => null,
    ]);
    exit;
}

$context = $rag->retrieveRelevantContext($query, $topK);
$basePrompt = "당신은 외교·안보·국제정치 전문 뉴스 편집자 AI입니다.";
$systemPromptPreview = $rag->buildSystemPromptWithRAG($basePrompt, $context);

ob_clean();
echo json_encode([
    'success' => true,
    'query' => $query,
    'top_k' => $topK,
    'critiques' => $context['critiques'] ?? [],
    'analyses' => $context['analyses'] ?? [],
    'system_prompt_preview' => $systemPromptPreview,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
