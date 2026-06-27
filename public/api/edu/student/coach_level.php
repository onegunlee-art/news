<?php
/**
 * POST { coach_level: 1~5 } — 테스트 스위치 (eduLevelDebugAllowed만)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';
require_once __DIR__ . '/../lib/eduConfig.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$student = eduRequireStudent();

if (!eduLevelDebugAllowed($student)) {
    eduSendError('Level debug not allowed', 403);
}

$body = eduJsonBody();
$level = eduCoachLevelNormalize((int) ($body['coach_level'] ?? 0));

$supabase = eduSupabase();
$updated = $supabase->update('edu_students', 'id=eq.' . $student['id'], [
    'coach_level' => $level,
]);
if ($updated === null) {
    eduSendError('Failed to update coach level: ' . $supabase->getLastError(), 500);
}

$student['coach_level'] = $level;

eduSendJson([
    'success' => true,
    'coach_level' => eduCoachLevelProfilePayload($student),
    'level_debug_allowed' => true,
    'message' => '다음 퀘스트부터 이 코치 깊이가 적용돼요.',
]);
