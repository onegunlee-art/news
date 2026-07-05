<?php
/**
 * GIST EDU — 부모 리포트 공개 조회 (인증 불필요)
 *
 * GET ?token=
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/eduParentReportShare.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    eduSendError('Method not allowed', 405);
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '') {
    eduSendError('token required', 400);
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('EDU service not configured', 503);
}

$report = eduParentReportShareFetchPublic($supabase, $token);
if ($report === null) {
    eduSendError('Report not found or expired', 404);
}

eduSendJson(['success' => true, 'report' => $report]);
