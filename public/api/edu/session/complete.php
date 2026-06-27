<?php
/**
 * POST { session_id, v2_sentences?, stance_delta? }
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';
require_once __DIR__ . '/../lib/eduConfig.php';

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

$drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
$draft = $drafts[0] ?? null;

if (!empty($body['v2_sentences']) && is_array($body['v2_sentences'])) {
    $v2 = array_map(static fn ($s) => trim((string) $s), array_slice($body['v2_sentences'], 0, 5));
    $hero = eduExtractHeroSentence($v2);
    $stanceDelta = in_array($body['stance_delta'] ?? '', ['refined', 'flipped', 'unchanged'], true)
        ? $body['stance_delta']
        : 'refined';

    if ($draft !== null) {
        $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, [
            'v2_sentences' => $v2,
            'hero_sentence' => $hero,
            'stance_delta' => $stanceDelta,
            'updated_at' => date('c'),
        ]);
    } else {
        $supabase->insert('edu_writing_drafts', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'v1_sentences' => [],
            'v2_sentences' => $v2,
            'hero_sentence' => $hero,
            'stance_delta' => $stanceDelta,
        ]);
    }
    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    $draft = $drafts[0] ?? null;
}

$v2raw = $draft['v2_sentences'] ?? [];
if (is_string($v2raw)) {
    $v2raw = json_decode($v2raw, true) ?: [];
}
if (count($v2raw) < 5) {
    eduSendError('Complete v2 writing first (5 sentences)');
}

$hero = $draft['hero_sentence'] ?? eduExtractHeroSentence($v2raw);

$xpQuest = 80;
$xpWriting = 40;
eduAwardXp($supabase, $student['id'], $xpQuest, 'quest_complete', $sessionId, ['quest_complete' => true]);
$tierRow = eduAwardXp($supabase, $student['id'], $xpWriting, 'writing_v2', $sessionId, ['writing_v2' => true]);

$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
    'stage' => 'completed',
    'completed_at' => date('c'),
    'updated_at' => date('c'),
]);

if ($hero !== null) {
    $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, [
        'hero_sentence' => $hero,
    ]);
}

eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'stage' => 'completed',
    'hero_sentence' => $hero,
    'xp_gained' => $xpQuest + $xpWriting,
    'tier' => eduTierProgressPayload($tierRow, eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_L1))),
    'coach_level' => eduCoachLevelProfilePayload($student),
    'level_debug_allowed' => eduLevelDebugAllowed($student),
    'ui_step' => 4,
    'ui_label' => 'XP·티어',
    'share_card' => [
        'kicker' => '이번 달 가장 큰 변화',
        'after' => $hero,
    ],
]);
