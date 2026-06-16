<?php
/**
 * POST { quest_id? } — start FSM session
 *
 * quest_id 명시: 해당 퀘스트의 진행 중 세션만 재개, 다른 퀘스트 세션은 무시하고 새로 시작.
 * quest_id 생략: 아무 진행 중 세션이 있으면 재개, 없으면 오늘 live 퀘스트로 시작.
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

$requestedQuestId = trim((string) ($body['quest_id'] ?? ''));

if ($requestedQuestId !== '') {
    $existing = eduActiveSessionForQuest($student['id'], $requestedQuestId);
    if ($existing !== null) {
        eduSendJson([
            'success' => true,
            'session_id' => $existing['id'],
            'stage' => $existing['stage'],
            'resumed' => true,
            'quest_id' => $requestedQuestId,
        ]);
    }
    $questId = $requestedQuestId;
} else {
    $existing = eduActiveSession($student['id']);
    if ($existing !== null) {
        eduSendJson([
            'success' => true,
            'session_id' => $existing['id'],
            'stage' => $existing['stage'],
            'resumed' => true,
            'quest_id' => $existing['quest_id'] ?? null,
        ]);
    }

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
    'quest_id' => $questId,
]);
