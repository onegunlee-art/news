<?php
/**
 * GET /api/edu/student/profile.php — 학생 프로필 + 티어 + 완료 퀘스트 수
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';
require_once __DIR__ . '/../lib/eduConfig.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduRequireStudent();
$supabase = eduSupabase();

$tier = eduTierProgressPayload(eduFetchTierRow($student['id']));

$completedRows = $supabase->select(
    'edu_quest_sessions',
    'student_id=eq.' . $student['id'] . '&' . eduSessionStageFilterCompleted(),
    500
) ?? [];

$topicIds = [];
foreach ($completedRows as $row) {
    if (!empty($row['quest_id'])) {
        $topicIds[(string) $row['quest_id']] = true;
    }
}

eduSendJson([
    'success' => true,
    'student' => [
        'id' => $student['id'],
        'display_name' => $student['display_name'] ?? '',
        'grade_band' => $student['grade_band'] ?? 'high',
        'profile_image' => $student['profile_image'] ?? null,
        'email' => $student['email'] ?? null,
        'has_kakao' => !empty($student['kakao_id']),
        'coach_level' => eduCoachLevelProfilePayload($student)['coach_level'],
    ],
    'coach_level' => eduCoachLevelProfilePayload($student),
    'level_debug_allowed' => eduLevelDebugAllowed($student),
    'tier' => $tier,
    'completed_count' => count($completedRows),
    'topics_count' => count($topicIds),
]);
