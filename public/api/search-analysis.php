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
use Agents\Services\SupabaseService;

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

$openaiCfg = require $projectRoot . 'config/openai.php';
$openai = new OpenAIService($openaiCfg);
$supabase = new SupabaseService([]);

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
$newsIds = array_filter($newsIds, fn ($id) => $id > 0);
if (empty($newsIds)) {
    sendAnalysisError('No valid news_ids');
}

// 1. Fetch article metadata from MySQL
$articles = [];
try {
    $pdo = getDb();
    $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
    $st = $pdo->prepare(
        "SELECT id, title, why_important, narration, description FROM news WHERE id IN ({$placeholders})"
    );
    $st->execute(array_values($newsIds));
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $articles[(int) $row['id']] = $row;
    }
} catch (PDOException $e) {
    error_log('[search-analysis] MySQL error: ' . $e->getMessage());
    sendAnalysisError('Database error', 500);
}

if (empty($articles)) {
    sendAnalysisError('No articles found', 404);
}

// 2. Fetch chunk_text + metadata from Supabase for these news_ids
$chunks = [];
if ($supabase->isConfigured()) {
    foreach ($newsIds as $nid) {
        $rows = $supabase->select(
            'analysis_embeddings',
            'select=chunk_text,metadata&news_id=eq.' . $nid . '&order=created_at.asc',
            5
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $chunks[$nid][] = $r;
            }
        }
    }
}

// 3. Build structured prompt
$articleBlocks = [];
$idx = 1;
foreach ($newsIds as $nid) {
    $art = $articles[$nid] ?? null;
    if (!$art) {
        continue;
    }
    $title = (string) ($art['title'] ?? '');
    $whyImportant = trim((string) ($art['why_important'] ?? ''));
    $narration = trim((string) ($art['narration'] ?? ''));
    $description = trim((string) ($art['description'] ?? ''));

    $chunkSummary = '';
    if (!empty($chunks[$nid])) {
        $topChunk = $chunks[$nid][0];
        $chunkSummary = mb_substr(trim((string) ($topChunk['chunk_text'] ?? '')), 0, 800);
    }

    $block = "[기사 {$idx}] {$title}";
    if ($whyImportant !== '') {
        $block .= "\n핵심: {$whyImportant}";
    }
    if ($chunkSummary !== '') {
        $block .= "\n분석: {$chunkSummary}";
    } elseif ($narration !== '') {
        $block .= "\n분석: " . mb_substr($narration, 0, 800);
    }

    $articleBlocks[] = $block;
    $idx++;
}

$articleText = implode("\n\n", $articleBlocks);
$topicLine = $clusterName !== '' ? "주제: \"{$clusterName}\"\n\n" : '';

$systemPrompt = '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.';
$userPrompt = <<<PROMPT
{$topicLine}다음 기사들을 종합 분석하라:

{$articleText}

분석 구조 (반드시 이 순서로):
1. 각 기사의 핵심 주장을 1문장씩 요약
2. 기사 간 충돌하는 관점이 있다면 식별
3. 기사들에서 공통적으로 나타나는 핵심 흐름 도출
4. 이 주제에 대한 종합 판단 제시

규칙:
- 한국어 존댓말(~이에요, ~거든요, ~있어요)로 답변
- 마크다운 문법 사용 금지 (번호와 하이픈만 허용)
- 근거 없는 추측 금지
- 첫 문장에 핵심 결론을 구체적으로 제시
PROMPT;

// 4. SSE streaming
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
while (ob_get_level()) {
    ob_end_flush();
}

sendSSE('start', ['cluster_name' => $clusterName, 'article_count' => count($articleBlocks)]);

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
