<?php
/**
 * GET /api/edu/session/structure_insight.php?session_id=UUID
 * 완주 세션 구조 진단 — 내부 디버그 뷰용 (허용 학생만)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduConfig.php';
require_once __DIR__ . '/../lib/eduStudentInsights.php';

handleOptionsRequest();
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduRequireStudent();
if (!eduStructureInsightDebugAllowed($student)) {
    eduSendError('Not found', 404);
}

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    eduSendError('session_id required');
}

$supabase = eduSupabase();
$sessions = $supabase->select(
    'edu_quest_sessions',
    'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'],
    1
);
if (empty($sessions[0])) {
    eduSendError('Session not found', 404);
}

$row = eduFetchStructureInsightRow($supabase, $sessionId);
eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'structure_insight' => eduStructureInsightDebugPayload($row),
]);
