<?php
/**
 * 지능형 검색 API
 *
 * POST { query, category?, limit? }
 *   → 벡터 검색 (Supabase RPC) + MySQL 기사 메타 + GPT 인사이트/클러스터링
 *
 * URL: /api/search.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(120);

require_once __DIR__ . '/lib/cors.php';
handleOptionsRequest();
setCorsHeaders();

require_once __DIR__ . '/lib/auth.php';

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;

function findProjectRootSearch(): string
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

function sendSearchJson(array $data, int $code = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSearchError(string $msg, int $code = 400): void
{
    sendSearchJson(['success' => false, 'error' => $msg], $code);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendSearchError('POST only', 405);
}

try {
    $projectRoot = findProjectRootSearch();
} catch (Throwable $e) {
    sendSearchError($e->getMessage(), 500);
}

require_once $projectRoot . 'src/agents/autoload.php';

$openai = new OpenAIService(require $projectRoot . 'config/openai.php');
$supabase = new SupabaseService([]);

if (!$openai->isConfigured() || !$supabase->isConfigured()) {
    sendSearchError('OpenAI or Supabase not configured', 503);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || empty(trim((string) ($input['query'] ?? '')))) {
    sendSearchError('query is required');
}

$query = trim((string) $input['query']);
$filterCategory = isset($input['category']) && trim((string) $input['category']) !== ''
    ? trim((string) $input['category'])
    : null;
$limit = max(1, min(50, (int) ($input['limit'] ?? 20)));

// 1. Embedding
try {
    $embedding = $openai->createEmbedding($query);
} catch (Throwable $e) {
    error_log('[search.php] embedding error: ' . $e->getMessage());
    sendSearchError('Embedding generation failed', 500);
}
if (empty($embedding)) {
    sendSearchError('Empty embedding returned', 500);
}

// 2. Vector search via Supabase RPC
$rpcParams = [
    'query_embedding' => $embedding,
    'match_count' => $limit,
];
if ($filterCategory !== null) {
    $rpcParams['filter_category'] = $filterCategory;
}

$vectorResults = $supabase->rpc('search_articles_by_embedding', $rpcParams);
if ($vectorResults === null) {
    $vectorResults = [];
}

if (empty($vectorResults)) {
    sendSearchJson([
        'success' => true,
        'results' => [],
        'insight' => null,
        'clusters' => [],
        'meta' => [
            'query' => $query,
            'total' => 0,
            'filter_category' => $filterCategory,
        ],
    ]);
}

// 3. Fetch article metadata from MySQL
$newsIds = array_filter(array_map(fn ($r) => (int) ($r['news_id'] ?? 0), $vectorResults), fn ($id) => $id > 0);
$articleMeta = [];

if (!empty($newsIds)) {
    try {
        $pdo = getDb();
        $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
        $st = $pdo->prepare(
            "SELECT id, title, description, image_url, published_at, category FROM news WHERE id IN ({$placeholders})"
        );
        $st->execute(array_values($newsIds));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $articleMeta[(int) $row['id']] = $row;
        }
    } catch (PDOException $e) {
        error_log('[search.php] MySQL error: ' . $e->getMessage());
    }
}

// 4. Merge results
$results = [];
foreach ($vectorResults as $vr) {
    $nid = (int) ($vr['news_id'] ?? 0);
    $meta = $articleMeta[$nid] ?? null;
    if ($meta === null) {
        continue;
    }
    $entities = $vr['entities'] ?? [];
    if (is_string($entities)) {
        $entities = json_decode($entities, true) ?: [];
    }
    $region = $vr['region'] ?? [];
    if (is_string($region)) {
        $region = json_decode($region, true) ?: [];
    }
    $results[] = [
        'news_id' => $nid,
        'similarity' => round((float) ($vr['max_similarity'] ?? 0), 4),
        'topic_label' => (string) ($vr['topic_label'] ?? ''),
        'topic_category' => (string) ($vr['topic_category'] ?? ''),
        'entities' => $entities,
        'region' => $region,
        'title' => (string) ($meta['title'] ?? ''),
        'description' => (string) ($meta['description'] ?? ''),
        'image_url' => (string) ($meta['image_url'] ?? ''),
        'published_at' => (string) ($meta['published_at'] ?? ''),
        'category' => (string) ($meta['category'] ?? ''),
    ];
}

// 5. GPT insight + clustering (single call, topic_label list only)
$insight = null;
$clusters = [];

if (count($results) >= 2) {
    $topicLines = [];
    foreach ($results as $i => $r) {
        $cat = $r['topic_category'] !== '' ? "[{$r['topic_category']}]" : '';
        $label = $r['topic_label'] !== '' ? $r['topic_label'] : $r['title'];
        $topicLines[] = "{$i}: {$cat} {$label}";
    }
    $topicList = implode("\n", $topicLines);

    $clusterSystem = '당신은 뉴스 분석 전문가입니다. 반드시 JSON만 출력합니다.';
    $clusterPrompt = <<<PROMPT
검색 결과의 기사 주제 목록이다:

{$topicList}

조건:
1. insight: 전체를 관통하는 핵심 인사이트 1문장(한국어). 다음 중 하나를 반드시 포함:
   - 기사 간 공통 패턴 1개
   - 예상 밖의 사실 1개
   - 시장 또는 정치적 영향 1개
2. clusters: 2~3개 주제 클러스터. 각각 name(자연어, 한국어)과 article_indices(번호 배열).
   - 모든 기사 번호가 최소 1개 클러스터에 포함되어야 함
   - 기사가 3개 미만이면 클러스터 1개만 가능

JSON만 출력:
{"insight":"","clusters":[{"name":"","article_indices":[]}]}
PROMPT;

    try {
        $clusterRaw = $openai->chat($clusterSystem, $clusterPrompt, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
            'max_tokens' => 500,
            'json_mode' => true,
            'timeout' => 30,
        ]);
        $clusterData = json_decode($clusterRaw, true);
        if (is_array($clusterData)) {
            $insight = (string) ($clusterData['insight'] ?? '');
            $rawClusters = $clusterData['clusters'] ?? [];
            if (is_array($rawClusters)) {
                foreach ($rawClusters as $c) {
                    $indices = $c['article_indices'] ?? [];
                    if (!is_array($indices) || empty($indices)) {
                        continue;
                    }
                    $validIndices = array_filter($indices, fn ($idx) => is_int($idx) && $idx >= 0 && $idx < count($results));
                    if (empty($validIndices)) {
                        continue;
                    }
                    $validIndices = array_values($validIndices);
                    $heroIndex = $validIndices[0];
                    $bestSim = 0;
                    foreach ($validIndices as $idx) {
                        if ($results[$idx]['similarity'] > $bestSim) {
                            $bestSim = $results[$idx]['similarity'];
                            $heroIndex = $idx;
                        }
                    }
                    $clusters[] = [
                        'name' => (string) ($c['name'] ?? ''),
                        'article_indices' => $validIndices,
                        'hero_index' => $heroIndex,
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[search.php] cluster GPT error: ' . $e->getMessage());
    }
}

sendSearchJson([
    'success' => true,
    'results' => $results,
    'insight' => $insight,
    'clusters' => $clusters,
    'meta' => [
        'query' => $query,
        'total' => count($results),
        'filter_category' => $filterCategory,
    ],
]);
