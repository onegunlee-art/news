<?php
/**
 * POST { session_id, stance: pro|con }
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

$sessionId = trim((string) ($body['session_id'] ?? ''));
$stance = trim((string) ($body['stance'] ?? ''));
if ($sessionId === '' || !in_array($stance, ['pro', 'con'], true)) {
    eduSendError('session_id and stance (pro|con) required');
}

$rows = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($rows[0])) {
    eduSendError('Session not found', 404);
}

$session = $rows[0];
$quests = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
$quest = $quests[0] ?? [];
$quest['articles'] = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $session['quest_id'] . '&order=sort_order.asc', 20) ?? [];

$hammer = eduHammerPayload($quest, $stance);

$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
    'stance' => $stance,
    'stage' => 'hammer',
    'hammer_payload' => $hammer,
    'updated_at' => date('c'),
]);

eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'stage' => 'hammer',
    'hammer' => $hammer,
    'ui_step' => 2,
    'ui_label' => '반론 읽기',
]);
