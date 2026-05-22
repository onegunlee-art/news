<?php
/**
 * Strategic Reports API ? Admin-only (isolated from user-facing APIs)
 *
 * GET  ?action=list
 * GET  ?action=detail&id=
 * GET  ?action=articles&start=&end=
 * POST { action: generate|update, ... }
 */

declare(strict_types=1);

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

use App\Services\IntelligenceCollectorService;
use App\Services\StrategicReportService;

function srJson(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function srError(string $msg, int $code = 400): never
{
    srJson(['success' => false, 'error' => $msg], $code);
}

try {
    $root = intelligenceFindProjectRoot();
    intelligenceLoadEnv($root);
    $pdo = intelligenceGetDb($root);
    intelligenceEnsureTables($pdo);
} catch (Throwable $e) {
    srError($e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));
        $stmt = $pdo->prepare(
            'SELECT id, report_week, period_start, period_end, status, confidence, executive_summary, created_at, updated_at
             FROM weekly_strategic_reports ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        srJson(['success' => true, 'reports' => $stmt->fetchAll() ?: []]);
    }

    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            srError('id required');
        }
        $stmt = $pdo->prepare('SELECT * FROM weekly_strategic_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            srError('Report not found', 404);
        }
        foreach (['scqa_raw_json', 'scqa_edited_json', 'edit_diff_json', 'judgment_feedbacks', 'source_articles_json', 'meta_json'] as $col) {
            if (!empty($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true);
            }
        }
        srJson(['success' => true, 'report' => $row]);
    }

    if ($action === 'articles') {
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
        $end = $_GET['end'] ?? date('Y-m-d');
        $stmt = $pdo->prepare(
            "SELECT id, source_api, title, url, published_at, relevance_score, trust_tier,
                    region, topic, event_type, embed_status, fetch_status, categorize_status
             FROM intelligence_source_items
             WHERE published_at BETWEEN :start AND :end
             ORDER BY relevance_score DESC, published_at DESC LIMIT 100"
        );
        $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['region', 'topic'] as $jsonCol) {
                if (!empty($row[$jsonCol]) && is_string($row[$jsonCol])) {
                    $row[$jsonCol] = json_decode($row[$jsonCol], true);
                }
            }
        }
        unset($row);
        srJson(['success' => true, 'articles' => $rows]);
    }

    srError('Unknown action');
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        srError('Invalid JSON');
    }
    $action = (string) ($input['action'] ?? '');

    if ($action === 'collect') {
        $collector = new IntelligenceCollectorService($pdo);
        $result = $collector->runDaily();
        srJson(['success' => true, 'result' => $result]);
    }

    if ($action === 'generate') {
        $service = new StrategicReportService($pdo);
        $week = isset($input['report_week']) ? (string) $input['report_week'] : null;
        $result = $service->generateForWeek($week);
        srJson($result, ($result['success'] ?? false) ? 200 : 500);
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            srError('id required');
        }
        $stmt = $pdo->prepare('SELECT scqa_raw_json, scqa_edited_json FROM weekly_strategic_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            srError('Report not found', 404);
        }
        $edited = $input['scqa_edited_json'] ?? null;
        if (!is_array($edited)) {
            srError('scqa_edited_json required');
        }
        $raw = json_decode((string) ($existing['scqa_raw_json'] ?? '{}'), true) ?: [];
        $diff = computeJsonDiff($raw, $edited);
        $upd = $pdo->prepare(
            'UPDATE weekly_strategic_reports SET
               scqa_edited_json = :edited,
               edit_diff_json = :diff,
               edit_reason = :reason,
               judgment_feedbacks = :judgments,
               status = :status,
               editor_notes = :notes
             WHERE id = :id'
        );
        $upd->execute([
            'edited' => json_encode($edited, JSON_UNESCAPED_UNICODE),
            'diff' => json_encode($diff, JSON_UNESCAPED_UNICODE),
            'reason' => (string) ($input['edit_reason'] ?? ''),
            'judgments' => json_encode($input['judgment_feedbacks'] ?? [], JSON_UNESCAPED_UNICODE),
            'status' => in_array(($input['status'] ?? 'reviewed'), ['draft', 'reviewed', 'approved'], true)
                ? $input['status'] : 'reviewed',
            'notes' => (string) ($input['editor_notes'] ?? ''),
            'id' => $id,
        ]);
        srJson(['success' => true, 'id' => $id, 'diff' => $diff]);
    }

    srError('Unknown action');
}

srError('Method not allowed', 405);

function computeJsonDiff(array $before, array $after, string $prefix = ''): array
{
    $changes = [];
    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    foreach ($keys as $key) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $b = $before[$key] ?? null;
        $a = $after[$key] ?? null;
        if (is_array($b) && is_array($a)) {
            $changes = array_merge($changes, computeJsonDiff($b, $a, $path));
            continue;
        }
        if ($b !== $a) {
            $changes[] = ['path' => $path, 'before' => $b, 'after' => $a];
        }
    }
    return $changes;
}
