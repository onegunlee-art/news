<?php
/**
 * GET  /api/edu/admin/parent_report.php?student_id= — 미리보기 JSON
 * POST /api/edu/admin/parent_report.php { student_id, format?: pdf|json }
 * Header: X-Edu-Admin-Key
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';
require_once __DIR__ . '/../lib/eduParentReportData.php';
require_once __DIR__ . '/../lib/eduParentReportPdf.php';

eduRequireAdminKey();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

$studentId = '';
$skipNarrative = false;

if ($method === 'GET') {
    $studentId = trim((string) ($_GET['student_id'] ?? ''));
    $skipNarrative = isset($_GET['skip_narrative']) && $_GET['skip_narrative'] === '1';
} elseif ($method === 'POST') {
    eduRequirePost();
    $input = eduJsonBody();
    $studentId = trim((string) ($input['student_id'] ?? ''));
    $skipNarrative = !empty($input['skip_narrative']);
} else {
    eduSendError('GET or POST only', 405);
}

if ($studentId === '') {
    eduSendError('student_id required');
}

try {
    $payload = eduParentReportBuildPayload($supabase, $studentId, !$skipNarrative);
} catch (InvalidArgumentException $e) {
    eduSendError($e->getMessage(), 404);
} catch (Throwable $e) {
    error_log('edu admin parent_report: ' . $e->getMessage());
    eduSendError('Report generation failed', 500);
}

if ($method === 'POST') {
    $format = 'pdf';
    if (isset($input['format'])) {
        $format = strtolower(trim((string) $input['format']));
    }
    if ($format === 'json') {
        eduSendJson(['success' => true, 'report' => $payload]);
    }

    try {
        $pdf = eduParentReportRenderPdf($payload);
    } catch (Throwable $e) {
        error_log('edu admin parent_report pdf: ' . $e->getMessage());
        eduSendError('PDF generation failed', 500);
    }

    $filename = eduParentReportPdfFilename($payload);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store');
    echo $pdf;
    exit;
}

eduSendJson([
    'success' => true,
    'report' => $payload,
]);
