<?php
/**
 * POST { session_id, reflection_note? } — advance hammer → writing
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$sessionId = trim((string) ($body['session_id'] ?? ''));
if ($sessionId === '') {
    eduSendError('session_id required');
}

$rows = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($rows[0])) {
    eduSendError('Session not found', 404);
}

$payload = $rows[0]['hammer_payload'] ?? [];
if (is_string($payload)) {
    $payload = json_decode($payload, true) ?: [];
}
if (!empty($body['reflection_note'])) {
    $payload['reflection_note'] = trim((string) $body['reflection_note']);
}

$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
    'stage' => 'writing',
    'hammer_payload' => $payload,
    'updated_at' => date('c'),
]);

eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'stage' => 'writing',
    'ui_step' => 3,
    'ui_label' => '5문장 쓰기',
    'writing_prompt' => '주장, 근거, 반론, 삶 연결, 결론 — 5문장으로 써 보세요.',
]);
