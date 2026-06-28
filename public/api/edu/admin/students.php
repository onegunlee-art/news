<?php
/**
 * GET /api/edu/admin/students.php — 운영자용 학생 목록 (X-Edu-Admin-Key)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';

eduRequireAdminKey();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

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
        'created_at' => $student['created_at'] ?? null,
    ];
}

eduSendJson([
    'success' => true,
    'students' => $items,
    'count' => count($items),
]);
