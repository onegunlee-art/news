<?php
/**
 * POST { quest_id? } — start FSM session
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$existing = eduActiveSession($student['id']);
if ($existing !== null) {
    eduSendJson([
        'success' => true,
        'session_id' => $existing['id'],
        'stage' => $existing['stage'],
        'resumed' => true,
    ]);
}

$questId = trim((string) ($body['quest_id'] ?? ''));
if ($questId === '') {
    $quest = eduLoadTodayQuest($student);
    if ($quest === null) {
        eduSendError('Quest not found', 404);
    }
    $questId = $quest['id'];
}

$inserted = $supabase->insert('edu_quest_sessions', [
    'student_id' => $student['id'],
    'quest_id' => $questId,
    'stage' => 'commit',
]);
if ($inserted === null || empty($inserted[0]['id'])) {
    eduSendError('Failed to start session: ' . $supabase->getLastError(), 500);
}

eduSendJson([
    'success' => true,
    'session_id' => $inserted[0]['id'],
    'stage' => 'commit',
    'resumed' => false,
]);
