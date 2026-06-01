<?php
/**
 * Weekly Gist API — Admin-only (Strategic Hub / Briefing)
 *
 * GET  ?action=articles&start=&end=
 * GET  ?action=list&limit=
 * GET  ?action=detail&id=
 * POST { action: "generate", ... }
 * POST { action: "update_gist", id, gist }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../src/backend/bootstrap_intelligence.php';
require_once __DIR__ . '/../../../src/agents/autoload.php';
require_once __DIR__ . '/../../../src/backend/autoload.php';
require_once __DIR__ . '/../lib/admin_auth.php';

function wgJson(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function wgError(string $msg, int $code = 400): never
{
    wgJson(['success' => false, 'error' => $msg], $code);
}

try {
    $root = intelligenceFindProjectRoot();
    intelligenceLoadEnv($root);
    $pdo = intelligenceGetDb($root);
    requireAdminApi($pdo);
    intelligenceEnsureWeeklyGistTable($pdo);
    $service = intelligenceCreateWeeklyGistService($pdo);
} catch (Throwable $e) {
    wgError($e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'articles') {
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
        $end = $_GET['end'] ?? date('Y-m-d');
        try {
            wgJson(['success' => true, 'data' => $service->fetchArticlesForPeriod($start, $end)]);
        } catch (Throwable $e) {
            wgError($e->getMessage(), 500);
        }
    }

    if ($action === 'list') {
        try {
            $limit = (int) ($_GET['limit'] ?? 50);
            wgJson(['success' => true, 'data' => ['items' => $service->listReports($limit)]]);
        } catch (Throwable $e) {
            wgError($e->getMessage(), 500);
        }
    }

    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            wgError('id 필요');
        }
        try {
            $detail = $service->getReportDetail($id);
            if (!$detail) {
                wgError('리포트를 찾을 수 없습니다.', 404);
            }
            wgJson(['success' => true, 'data' => $detail]);
        } catch (Throwable $e) {
            wgError($e->getMessage(), 500);
        }
    }

    wgError('Unknown GET action (articles|list|detail)');
}

if ($method !== 'POST') {
    wgError('GET or POST only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$action = $input['action'] ?? '';

if ($action === 'update_gist') {
    $id = (int) ($input['id'] ?? 0);
    $gist = $input['gist'] ?? null;
    if ($id < 1 || !is_array($gist)) {
        wgError('id와 gist 객체가 필요합니다.');
    }
    try {
        if (!$service->updateGist($id, $gist)) {
            wgError('리포트 id를 찾을 수 없습니다.', 404);
        }
        wgJson(['success' => true, 'data' => ['id' => $id]]);
    } catch (Throwable $e) {
        wgError($e->getMessage(), 500);
    }
}

if ($action !== 'generate') {
    wgError('POST action은 generate 또는 update_gist');
}

$startDate = $input['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $input['end'] ?? date('Y-m-d');
$articles = $input['articles'] ?? [];

try {
    $result = $service->generate($startDate, $endDate, $articles);
    wgJson([
        'success' => true,
        'data' => $result['gist'],
        'saved_id' => $result['saved_id'],
        'save_error' => $result['save_error'],
    ]);
} catch (InvalidArgumentException $e) {
    wgError($e->getMessage());
} catch (Throwable $e) {
    wgError($e->getMessage(), 500);
}
