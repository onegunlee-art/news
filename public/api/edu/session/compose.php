<?php
/**
 * POST /api/edu/session/compose — blueprint → GIST 스타일 자동 글 생성 + verify/polish
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduConfig.php';
require_once __DIR__ . '/../lib/eduBlueprint.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduAgents.php';
require_once __DIR__ . '/../lib/_llm.php';

$root = eduFindProjectRoot();
require_once $root . 'src/backend/autoload.php';
eduLoadAgents();

use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\Agents\WritingBuilder;
use Services\Edu\EduRagService;
use Services\Edu\EduWritingGate;

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

$sessions = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($sessions[0])) {
    eduSendError('Session not found', 404);
}
$session = $sessions[0];

if (($session['stage'] ?? '') === 'completed') {
    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    $v2 = $drafts[0]['v2_sentences'] ?? [];
    if (is_string($v2)) {
        $v2 = json_decode($v2, true) ?: [];
    }
    eduSendJson([
        'success' => true,
        'session_id' => $sessionId,
        'stage' => 'completed',
        'full_text' => is_array($v2) ? implode(' ', $v2) : '',
        'hero_sentence' => $drafts[0]['hero_sentence'] ?? null,
        'already_completed' => true,
    ]);
}

$quests = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
$quest = $quests[0] ?? [];
$quest['articles'] = $supabase->select(
    'edu_quest_articles',
    'quest_id=eq.' . $session['quest_id'] . '&order=sort_order.asc',
    20
) ?? [];

$blueprint = eduLoadBlueprint($session);
$dialogue = eduLoadDialogue($session);

if (!eduBlueprintReadyForCompose($blueprint) && empty($body['force'])) {
    eduSendError('Blueprint not ready for compose');
}

$llm = eduLlm();
$rag = new EduRagService($supabase);
$composer = new GistStyleComposer($llm, $rag);
$gate = new EduWritingGate();
$writer = new WritingBuilder($llm);

$draft = $composer->compose($blueprint, $quest, $dialogue);

$judgmentPatterns = '';
if (eduJudgmentWritingEnabled()) {
    $patterns = array_merge(
        $rag->getWritingPatterns((string) ($quest['quest_title'] ?? ''), 3),
        $rag->getJudgementPatterns(3)
    );
    $judgmentPatterns = $rag->formatWritingPatterns($patterns);
}

$evaluation = $writer->evaluateWriting($draft['full_text'] ?? '', $quest, $judgmentPatterns);
$verification = $gate->verify($draft);

if (!$verification['passed']) {
    $draft = $gate->polish($llm, $draft, $verification['hints'], $quest);
    $verification = $gate->verify($draft);
    $evaluation = $writer->evaluateWriting($draft['full_text'] ?? '', $quest, $judgmentPatterns);
}

$parts = $draft['scqa_parts'] ?? [];
$sentences = [
    $parts['situation'] ?? '',
    $parts['complication'] ?? '',
    $parts['question'] ?? '',
    $parts['answer'] ?? '',
    $parts['conclusion'] ?? '',
];

$hero = $evaluation['hero_sentence'] ?? ($draft['hero_sentence'] ?? eduExtractHeroSentence($sentences));

$supabase->insert('edu_writing_versions', [
    'session_id' => $sessionId,
    'student_id' => $student['id'],
    'version' => 1,
    'scqa_situation' => $sentences[0],
    'scqa_complication' => $sentences[1],
    'scqa_question' => $sentences[2],
    'scqa_answer' => $sentences[3],
    'conclusion' => $sentences[4],
    'word_count' => mb_strlen((string) ($draft['full_text'] ?? '')),
    'quality_score' => $evaluation['quality_score'] ?? 70,
    'ai_feedback' => $evaluation['feedback'] ?? '',
]);

$stanceDelta = !empty($blueprint['stance_changed']) ? 'refined' : 'unchanged';
$draftPayload = [
    'v1_sentences' => $sentences,
    'v2_sentences' => $sentences,
    'hero_sentence' => $hero,
    'stance_delta' => $stanceDelta,
    'teacher_feedback' => $evaluation['feedback'] ?? '',
    'updated_at' => date('c'),
];
$existingDrafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
if (!empty($existingDrafts[0])) {
    $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, $draftPayload);
} else {
    $supabase->insert('edu_writing_drafts', array_merge($draftPayload, [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
    ]));
}

$xpQuest = 80;
$xpWriting = 40;
eduAwardXp($supabase, $student['id'], $xpQuest, 'quest_complete', $sessionId, ['quest_complete' => true]);
$tierRow = eduAwardXp($supabase, $student['id'], $xpWriting, 'writing_v2', $sessionId, ['writing_v2' => true]);

$blueprint = eduMergeBlueprint($blueprint, [
    'phase' => 'completed',
    'ready_for_compose' => true,
    'scqa_slots' => [
        'S' => $sentences[0],
        'C' => $sentences[1],
        'Q' => $sentences[2],
        'A' => $sentences[3],
        'conclusion' => $sentences[4],
    ],
]);
$dialogue = eduAppendDialogue($dialogue, 'assistant', $draft['full_text'] ?? '', 'composer');

$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
    'blueprint_json' => $blueprint,
    'dialogue_json' => $dialogue,
    'stage' => 'completed',
    'completed_at' => date('c'),
    'updated_at' => date('c'),
]);

eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'stage' => 'completed',
    'full_text' => $draft['full_text'] ?? '',
    'scqa_parts' => $parts,
    'hero_sentence' => $hero,
    'quality_score' => $evaluation['quality_score'] ?? 70,
    'structure_score' => $verification['structure_score'] ?? 3,
    'feedback' => $evaluation['feedback'] ?? '잘 정리했어요!',
    'xp_gained' => $xpQuest + $xpWriting,
    'tier' => eduTierProgressPayload($tierRow),
    'progress_pct' => 100,
]);
