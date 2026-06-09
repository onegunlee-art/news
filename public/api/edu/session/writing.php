<?php
/**
 * POST { session_id, v1_sentences: string[5], v2_sentences?: string[] }
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduTier.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$sessionId = trim((string) ($body['session_id'] ?? ''));
$v1 = $body['v1_sentences'] ?? null;
if ($sessionId === '' || !is_array($v1) || count($v1) < 5) {
    eduSendError('session_id and v1_sentences (5 items) required');
}

$v1 = array_map(static fn ($s) => trim((string) $s), array_slice($v1, 0, 5));
foreach ($v1 as $line) {
    if ($line === '') {
        eduSendError('All 5 sentences required');
    }
}

$rows = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($rows[0])) {
    eduSendError('Session not found', 404);
}

$v2 = $body['v2_sentences'] ?? [];
$v2 = is_array($v2) ? array_map(static fn ($s) => trim((string) $s), $v2) : [];
$feedback = null;
if (count($v2) === 0) {
    $feedback = '근거가 없는 문장이 있으면 기사 번호를 붙여 보세요. 주장과 결론이 모순되지 않는지 확인하세요.';
}

$existing = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
$hero = count($v2) > 0 ? eduExtractHeroSentence($v2) : null;

$row = [
    'session_id' => $sessionId,
    'student_id' => $student['id'],
    'v1_sentences' => $v1,
    'v2_sentences' => $v2,
    'teacher_feedback' => $feedback,
    'hero_sentence' => $hero,
    'updated_at' => date('c'),
];

if (!empty($existing[0]['id'])) {
    $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, $row);
} else {
    $supabase->insert('edu_writing_drafts', $row);
}

$stage = count($v2) >= 5 ? 'growth' : 'writing';
$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
    'stage' => $stage,
    'updated_at' => date('c'),
]);

eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'stage' => $stage,
    'teacher_feedback' => $feedback,
    'needs_v2' => count($v2) < 5,
    'hero_sentence' => $hero,
    'ui_step' => 3,
]);
