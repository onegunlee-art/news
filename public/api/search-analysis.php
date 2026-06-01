<?php
/**
 * 종합 분석 SSE 스트리밍 API
 *
 * POST { news_ids: int[], cluster_name: string }
 *   → 해당 기사들의 chunk_text + MySQL 메타를 조합하여
 *     구조화 프롬프트로 GPT 스트리밍 분석 결과 반환
 *
 * URL: /api/search-analysis.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

require_once __DIR__ . '/lib/cors.php';
handleOptionsRequest();
setCorsHeaders();

require_once __DIR__ . '/lib/auth.php';

use Agents\Services\OpenAIService;
use App\Services\SearchAnalysisService;

function findProjectRootAnalysis(): string
{
    $candidates = [__DIR__ . '/../../', __DIR__ . '/../../../'];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found');
}

function sendAnalysisError(string $msg, int $code = 400): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSSE(string $event, $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendAnalysisError('POST only', 405);
}

try {
    $projectRoot = findProjectRootAnalysis();
} catch (Throwable $e) {
    sendAnalysisError($e->getMessage(), 500);
}

require_once $projectRoot . 'src/agents/autoload.php';
require_once $projectRoot . 'src/backend/autoload.php';

$openaiCfg = require $projectRoot . 'config/openai.php';
$openai = new OpenAIService($openaiCfg);

if (!$openai->isConfigured()) {
    sendAnalysisError('OpenAI not configured', 503);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    sendAnalysisError('Invalid JSON');
}

$newsIds = $input['news_ids'] ?? [];
$clusterName = trim((string) ($input['cluster_name'] ?? ''));

if (!is_array($newsIds) || count($newsIds) < 1 || count($newsIds) > 10) {
    sendAnalysisError('news_ids must be an array of 1-10 IDs');
}
$newsIds = array_map('intval', $newsIds);
$newsIds = array_values(array_filter($newsIds, fn ($id) => $id > 0));
if (empty($newsIds)) {
    sendAnalysisError('No valid news_ids');
}

try {
    $pdo = getDb();
    $analysisService = new SearchAnalysisService($pdo, $openai);
    $ctx = $analysisService->buildClusterContext($newsIds);
} catch (PDOException $e) {
    error_log('[search-analysis] MySQL error: ' . $e->getMessage());
    sendAnalysisError('Database error', 500);
} catch (Throwable $e) {
    sendAnalysisError($e->getMessage(), 400);
}

if ($ctx['blocks'] === []) {
    sendAnalysisError('No articles found', 404);
}

$systemPrompt = $analysisService->systemPrompt();
$userPrompt = $analysisService->buildPrompt($clusterName, $ctx['blocks']);

// 4. SSE streaming
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
while (ob_get_level()) {
    ob_end_flush();
}

sendSSE('start', ['cluster_name' => $clusterName, 'article_count' => count($ctx['blocks'])]);

$openai->chatStream(
    $systemPrompt,
    $userPrompt,
    function (string $delta) {
        sendSSE('token', ['text' => $delta]);
    },
    function (string $full) {
        sendSSE('done', ['full_text' => $full]);
    },
    [
        'max_tokens' => 2000,
        'timeout' => 120,
        'temperature' => 0.5,
        'model' => $openaiCfg['model'] ?? 'gpt-4o-mini',
    ]
);

sendSSE('end', []);
