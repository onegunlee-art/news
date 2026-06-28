<?php
/**
 * GIST EDU — 부모 리포트 운영자 API (news admin JWT only)
 *
 * GET  ?action=students
 * GET  ?action=preview&student_id=
 * POST { action: pdf|preview, student_id }
 *
 * ★ 이원근( users.role=admin )만 접근. EDU 학생 토큰으로는 401/403.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(180);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../edu/lib/bootstrap.php';
require_once __DIR__ . '/../edu/lib/eduParentReportData.php';
require_once __DIR__ . '/../edu/lib/eduParentReportPdf.php';
require_once __DIR__ . '/../edu/lib/eduQuest.php';
require_once __DIR__ . '/../edu/lib/eduTier.php';
require_once __DIR__ . '/../edu/lib/eduCoachLevel.php';

function eprJson(array $data, int $code = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function eprError(string $msg, int $code = 400): never
{
    eprJson(['success' => false, 'error' => $msg], $code);
}

try {
    $pdo = getDb();
    requireAdminApi($pdo);
} catch (Throwable $e) {
    eprError($e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $raw = file_get_contents('php://input') ?: '{}';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        eprError('Invalid JSON body');
    }
    if ($action === '') {
        $action = (string) ($body['action'] ?? '');
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eprError('EDU service not configured', 503);
}

if ($action === 'students' && $method === 'GET') {
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $students = $supabase->select(
        'edu_students',
        'status=eq.active&order=last_active_at.desc.nullslast,created_at.desc',
        $limit
    ) ?? [];

    $items = [];
    foreach ($students as $student) {
        $studentId = (string) ($student['id'] ?? '');
        if ($studentId === '') {
            continue;
        }
        $completedRows = $supabase->select(
            'edu_quest_sessions',
            'student_id=eq.' . $studentId . '&' . eduSessionStageFilterCompleted(),
            500
        ) ?? [];
        $coachLevel = eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_L1));
        $coachPayload = eduCoachLevelProfilePayload($student);
        $tierRow = eduFetchTierRow($studentId);
        $items[] = [
            'id' => $studentId,
            'display_name' => (string) ($student['display_name'] ?? ''),
            'grade_band' => (string) ($student['grade_band'] ?? ''),
            'coach_level' => $coachLevel,
            'coach_label_ko' => $coachPayload['label_ko'],
            'completed_count' => count($completedRows),
            'streak_days' => (int) ($tierRow['streak_days'] ?? 0),
            'last_active_at' => $student['last_active_at'] ?? null,
        ];
    }

    eprJson(['success' => true, 'students' => $items, 'count' => count($items)]);
}

$studentId = '';
if ($method === 'GET') {
    $studentId = trim((string) ($_GET['student_id'] ?? ''));
} elseif ($method === 'POST') {
    $studentId = trim((string) ($body['student_id'] ?? ''));
}

if ($studentId === '' && in_array($action, ['preview', 'pdf'], true)) {
    eprError('student_id required');
}

if ($action === 'preview') {
    try {
        $report = eduParentReportBuildPayload($supabase, $studentId, true);
    } catch (InvalidArgumentException $e) {
        eprError($e->getMessage(), 404);
    } catch (Throwable $e) {
        error_log('edu-parent-report preview: ' . $e->getMessage());
        eprError('Report preview failed', 500);
    }
    eprJson(['success' => true, 'report' => $report]);
}

if ($action === 'pdf' && $method === 'POST') {
    try {
        $report = eduParentReportBuildPayload($supabase, $studentId, true);
        $pdf = eduParentReportRenderPdf($report);
    } catch (InvalidArgumentException $e) {
        eprError($e->getMessage(), 404);
    } catch (Throwable $e) {
        error_log('edu-parent-report pdf: ' . $e->getMessage());
        eprError('PDF generation failed', 500);
    }

    $filename = eduParentReportPdfFilename($report);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store');
    echo $pdf;
    exit;
}

eprError('Unknown action', 404);
