<?php
/**
 * Search Reports API — Admin-only (Strategic Hub / 검색 메모리)
 *
 * GET  ?action=list&limit=
 * GET  ?action=detail&id=
 * GET  ?action=memory_diff&news_ids=1,2,3&cluster_name=
 * POST { action: save|update|generate, ... }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../src/backend/bootstrap_intelligence.php';
require_once __DIR__ . '/../../../src/agents/autoload.php';
require_once __DIR__ . '/../../../src/backend/autoload.php';
require_once __DIR__ . '/../lib/admin_auth.php';

function srchJson(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function srchError(string $msg, int $code = 400): never
{
    srchJson(['success' => false, 'error' => $msg], $code);
}

function srchParseNewsIds(string $raw): array
{
    if ($raw === '') {
        return [];
    }
    if ($raw[0] === '[') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }
    return array_values(array_filter(array_map('intval', explode(',', $raw)), fn ($id) => $id > 0));
}

try {
    $root = intelligenceFindProjectRoot();
    intelligenceLoadEnv($root);
    $pdo = intelligenceGetDb($root);
    requireAdminApi($pdo);
    $reportService = intelligenceCreateSearchReportService($pdo);
    $memoryService = intelligenceCreateStrategicMemoryService($pdo);
} catch (Throwable $e) {
    srchError($e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        try {
            $limit = (int) ($_GET['limit'] ?? 50);
            srchJson(['success' => true, 'data' => ['items' => $reportService->listReports($limit)]]);
        } catch (Throwable $e) {
            srchError($e->getMessage(), 500);
        }
    }

    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            srchError('id required');
        }
        try {
            $detail = $reportService->getDetail($id);
            if (!$detail) {
                srchError('Report not found', 404);
            }
            srchJson(['success' => true, 'data' => $detail]);
        } catch (Throwable $e) {
            srchError($e->getMessage(), 500);
        }
    }

    if ($action === 'memory_diff') {
        $newsIds = srchParseNewsIds((string) ($_GET['news_ids'] ?? ''));
        $clusterName = trim((string) ($_GET['cluster_name'] ?? ''));
        if ($newsIds === [] || $clusterName === '') {
            srchError('news_ids and cluster_name required');
        }
        try {
            $memory = $memoryService->compareClusterToWeeklyGist($newsIds, $clusterName);
            srchJson(['success' => true, 'data' => $memory]);
        } catch (Throwable $e) {
            srchError($e->getMessage(), 500);
        }
    }

    srchError('Unknown GET action (list|detail|memory_diff)');
}

if ($method !== 'POST') {
    srchError('GET or POST only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$action = (string) ($input['action'] ?? '');

if ($action === 'save') {
    $clusterName = trim((string) ($input['cluster_name'] ?? ''));
    $analysisText = trim((string) ($input['analysis_text'] ?? ''));
    $newsIds = $input['news_ids'] ?? [];
    if ($clusterName === '' || $analysisText === '' || !is_array($newsIds) || $newsIds === []) {
        srchError('cluster_name, analysis_text, news_ids required');
    }
    try {
        $newsIds = array_values(array_filter(array_map('intval', $newsIds), fn ($id) => $id > 0));
        $memory = $memoryService->compareClusterToWeeklyGist($newsIds, $clusterName);
        $id = $reportService->save(
            $clusterName,
            $analysisText,
            $newsIds,
            isset($input['search_query']) ? (string) $input['search_query'] : null,
            isset($input['cluster_question']) ? (string) $input['cluster_question'] : null,
            ['memory_diff' => $memory, 'source' => 'admin_save']
        );
        $detail = $reportService->getDetail($id);
        srchJson(['success' => true, 'data' => $detail, 'memory' => $memory]);
    } catch (Throwable $e) {
        srchError($e->getMessage(), 500);
    }
}

if ($action === 'update') {
    $id = (int) ($input['id'] ?? 0);
    $analysisText = trim((string) ($input['analysis_text'] ?? ''));
    if ($id < 1 || $analysisText === '') {
        srchError('id and analysis_text required');
    }
    try {
        if (!$reportService->updateAnalysis($id, $analysisText)) {
            srchError('Report not found', 404);
        }
        srchJson(['success' => true, 'data' => $reportService->getDetail($id)]);
    } catch (Throwable $e) {
        srchError($e->getMessage(), 500);
    }
}

if ($action === 'generate') {
    $clusterName = trim((string) ($input['cluster_name'] ?? ''));
    $newsIds = $input['news_ids'] ?? [];
    if ($clusterName === '' || !is_array($newsIds) || $newsIds === []) {
        srchError('cluster_name and news_ids required');
    }
    try {
        $newsIds = array_values(array_filter(array_map('intval', $newsIds), fn ($id) => $id > 0));
        $result = $reportService->generateAndSave(
            $newsIds,
            $clusterName,
            isset($input['search_query']) ? (string) $input['search_query'] : null,
            isset($input['cluster_question']) ? (string) $input['cluster_question'] : null
        );
        srchJson(['success' => true, 'data' => $result['report'], 'memory' => $result['memory']]);
    } catch (Throwable $e) {
        srchError($e->getMessage(), 500);
    }
}

srchError('Unknown POST action (save|update|generate)');
