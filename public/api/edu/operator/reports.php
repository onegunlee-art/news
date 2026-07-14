<?php
/**
 * EDU 운영자 — 부모 리포트 API (X-Edu-Operator-Token)
 *
 * GET  ?action=students
 * GET  ?action=export_csv
 * GET  ?action=preview&student_id=
 * POST { action: pdf, student_id }
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduOperatorAuth.php';
require_once __DIR__ . '/../lib/eduOperatorReports.php';

handleOptionsRequest();
setCorsHeaders();
set_time_limit(180);

$operator = eduRequireOperator();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$body = null;

if ($method === 'POST') {
    eduRequirePost();
    $body = eduJsonBody();
    if ($action === '') {
        $action = (string) ($body['action'] ?? '');
    }
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('EDU service not configured', 503);
}

$studentId = '';
if ($method === 'GET') {
    $studentId = trim((string) ($_GET['student_id'] ?? ''));
} elseif ($method === 'POST') {
    $studentId = trim((string) ($body['student_id'] ?? ''));
}

eduOperatorReportsHandle($supabase, $operator, $method, $action, $studentId, $body);
