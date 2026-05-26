<?php
/**
 * Strategic Reports API ? Admin-only (isolated from user-facing APIs)
 *
 * GET  ?action=list
 * GET  ?action=detail&id=
 * GET  ?action=articles&start=&end=
 * POST { action: generate|update, ... }
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

use App\Services\IntelligenceCollectorService;
use App\Services\StrategicReportService;
use App\Services\StrategicReportPdfService;
use App\Services\MailService;

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

    if ($action === 'stats') {
        $bySource = $pdo->query(
            "SELECT source_api, embed_status, COUNT(*) AS cnt
             FROM intelligence_source_items GROUP BY source_api, embed_status"
        )->fetchAll() ?: [];
        $pending = (int) $pdo->query(
            "SELECT COUNT(*) FROM intelligence_source_items
             WHERE embed_status IN ('pending','failed') AND duplicate_of IS NULL"
        )->fetchColumn();
        srJson(['success' => true, 'pipeline' => ['by_source_embed' => $bySource, 'pending' => $pending]]);
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

    if ($action === 'export_pdf' || $action === 'preview_pdf') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            srError('id required');
        }
        $stmt = $pdo->prepare('SELECT * FROM weekly_strategic_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $report = $stmt->fetch();
        if (!$report) {
            srError('Report not found', 404);
        }

        try {
            $pdfService = new StrategicReportPdfService();
            
            header_remove('Content-Type');
            
            if ($action === 'preview_pdf') {
                $pdfService->outputInline($report);
            } else {
                $pdfService->outputAttachment($report);
            }
            exit;
        } catch (Throwable $e) {
            srError('PDF 생성 실패: ' . $e->getMessage(), 500);
        }
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

    if ($action === 'reprocess') {
        $limit = max(1, min(200, (int) ($input['limit'] ?? 80)));
        $collector = new IntelligenceCollectorService($pdo);
        $result = $collector->reprocessSkippedPremium($limit);
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
        $editReason = (string) ($input['edit_reason'] ?? '');
        $judgmentFeedbacks = $input['judgment_feedbacks'] ?? [];
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
            'reason' => $editReason,
            'judgments' => json_encode($judgmentFeedbacks, JSON_UNESCAPED_UNICODE),
            'status' => in_array(($input['status'] ?? 'reviewed'), ['draft', 'reviewed', 'approved'], true)
                ? $input['status'] : 'reviewed',
            'notes' => (string) ($input['editor_notes'] ?? ''),
            'id' => $id,
        ]);

        $judgmentResult = ['stored' => 0, 'errors' => []];
        if ($diff !== [] || $judgmentFeedbacks !== [] || $editReason !== '') {
            try {
                $service = new StrategicReportService($pdo);
                $judgmentResult = $service->storeJudgmentFeedback($id, $diff, $judgmentFeedbacks, $editReason);
            } catch (Throwable $e) {
                $judgmentResult['errors'][] = $e->getMessage();
            }
        }

        srJson(['success' => true, 'id' => $id, 'diff' => $diff, 'judgment_stored' => $judgmentResult]);
    }

    if ($action === 'update_status') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            srError('id required');
        }
        $status = (string) ($input['status'] ?? 'reviewed');
        if (!in_array($status, ['draft', 'reviewed', 'approved'], true)) {
            srError('invalid status');
        }
        $check = $pdo->prepare('SELECT id FROM weekly_strategic_reports WHERE id = :id');
        $check->execute(['id' => $id]);
        if (!$check->fetch()) {
            srError('Report not found', 404);
        }
        $stmt = $pdo->prepare(
            'UPDATE weekly_strategic_reports SET status = :status, editor_notes = :notes WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'notes' => (string) ($input['editor_notes'] ?? ''),
            'id' => $id,
        ]);
        srJson(['success' => true, 'id' => $id, 'status' => $status]);
    }

    if ($action === 'send_email') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            srError('id required');
        }

        $to = trim((string) ($input['to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            srError('유효한 이메일 주소가 필요합니다');
        }

        $stmt = $pdo->prepare('SELECT * FROM weekly_strategic_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $report = $stmt->fetch();
        if (!$report) {
            srError('Report not found', 404);
        }

        if (($report['status'] ?? 'draft') !== 'approved') {
            srError('승인된(approved) 레포트만 이메일 발송이 가능합니다', 403);
        }

        $mailService = new MailService();
        if (!$mailService->isResendConfigured()) {
            srError('RESEND_API_KEY가 설정되지 않았습니다', 500);
        }

        try {
            $pdfService = new StrategicReportPdfService();
            $pdfBase64 = $pdfService->generateBase64($report);
            $filename = $pdfService->generateFilename($report);

            $docConfig = require dirname(__DIR__, 3) . '/config/strategic_report_document.php';
            $reportWeek = (string) ($report['report_week'] ?? date('Y-\WW'));
            
            $scqa = $report['scqa_edited_json'] ?? $report['scqa_raw_json'] ?? '{}';
            if (is_string($scqa)) {
                $scqa = json_decode($scqa, true) ?: [];
            }
            $coreQuestion = trim((string) ($scqa['core_question'] ?? $scqa['structural_shift']['headline'] ?? '주간 전략 레포트'));

            $subjectTemplate = $docConfig['email']['subject_template'] ?? '[the gist] 주간 지정학 전략 레포트 {report_week}';
            $subject = isset($input['subject']) && trim((string) $input['subject']) !== ''
                ? (string) $input['subject']
                : str_replace('{report_week}', $reportWeek, $subjectTemplate);

            $message = isset($input['message']) && trim((string) $input['message']) !== ''
                ? (string) $input['message']
                : '';

            $textBody = "the gist 주간 지정학 전략 레포트 ({$reportWeek})\n\n"
                . "핵심 질문: {$coreQuestion}\n\n"
                . ($message !== '' ? $message . "\n\n" : '')
                . "첨부된 PDF를 확인해 주세요.";

            $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: 'Noto Sans KR', sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="border-bottom: 2px solid #333; padding-bottom: 10px;">the gist. 주간 지정학 전략 레포트</h2>
        <p style="font-size: 14px; color: #666;">{$reportWeek}</p>
        <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #333; margin: 20px 0;">
            <strong>핵심 질문:</strong> {$coreQuestion}
        </div>
HTML;
            if ($message !== '') {
                $htmlBody .= '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';
            }
            $htmlBody .= <<<HTML
        <p style="margin-top: 20px;">첨부된 PDF를 확인해 주세요.</p>
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        <p style="font-size: 12px; color: #999;">
            이 이메일은 the gist 전략 인텔리전스 시스템에서 자동 발송되었습니다.
        </p>
    </div>
</body>
</html>
HTML;

            $result = $mailService->sendWithAttachment(
                $to,
                $subject,
                $textBody,
                $htmlBody,
                [['filename' => $filename, 'content' => $pdfBase64]]
            );

            if ($result['success']) {
                srJson([
                    'success' => true,
                    'message_id' => $result['message_id'] ?? null,
                    'filename' => $filename,
                    'to' => $to,
                ]);
            } else {
                srError($result['error'] ?? '이메일 발송 실패', 500);
            }
        } catch (Throwable $e) {
            srError('이메일 발송 실패: ' . $e->getMessage(), 500);
        }
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
