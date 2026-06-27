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
require_once __DIR__ . '/../lib/eduCoachLevel.php';
require_once __DIR__ . '/../lib/eduConfig.php';
require_once __DIR__ . '/../lib/eduConfig.php';
require_once __DIR__ . '/../lib/eduDraftStorage.php';
require_once __DIR__ . '/../lib/eduStudentInsights.php';
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

@set_time_limit(300);

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$sessionId = trim((string) ($body['session_id'] ?? ''));
if ($sessionId === '') {
    eduSendError('session_id required');
}

try {

$sessions = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($sessions[0])) {
    eduSendError('Session not found', 404);
}
$session = $sessions[0];

eduGuardSessionAbandoned($session, $sessionId);

if (($session['stage'] ?? '') === 'completed') {
    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    $draft = $drafts[0] ?? [];
    $essayStructure = $draft['essay_structure'] ?? [];
    if (is_string($essayStructure)) {
        $essayStructure = json_decode($essayStructure, true) ?: [];
    }
    $fullText = trim((string) ($draft['full_text'] ?? ''));
    if ($fullText === '') {
        $v2 = $draft['v2_sentences'] ?? [];
        if (is_string($v2)) {
            $v2 = json_decode($v2, true) ?: [];
        }
        $fullText = is_array($v2) ? implode("\n\n", array_filter(array_map('strval', $v2))) : '';
    }

    $insightRow = null;
    if (!eduStructureInsightExists($supabase, $sessionId)) {
        $questsEarly = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
        $questEarly = $questsEarly[0] ?? [];
        $insightRow = eduEnsureStructureInsight($supabase, $session, $questEarly, $fullText);
    } else {
        $insightRow = eduFetchStructureInsightRow($supabase, $sessionId);
    }

    $alreadyPayload = [
        'success' => true,
        'session_id' => $sessionId,
        'stage' => 'completed',
        'full_text' => $fullText,
        'essay_structure' => $essayStructure,
        'title' => $essayStructure['title'] ?? null,
        'hero_sentence' => $draft['hero_sentence'] ?? null,
        'already_completed' => true,
    ];
    if (eduStructureInsightDebugAllowed($student)) {
        $alreadyPayload['structure_insight'] = eduStructureInsightDebugPayload($insightRow);
    }
    eduSendJson($alreadyPayload);
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

if (isset($draft['success']) && $draft['success'] === false) {
    eduSendError((string) ($draft['message'] ?? '글 생성에 실패했습니다. 잠시 후 다시 시도해 주세요.'), 503);
}

$judgmentPatterns = '';
if (eduJudgmentWritingEnabled()) {
    $patterns = array_merge(
        $rag->getWritingPatterns((string) ($quest['quest_title'] ?? ''), 3),
        $rag->getJudgementPatterns(3)
    );
    $judgmentPatterns = $rag->formatWritingPatterns($patterns);
}

$evaluation = $writer->evaluateStructuredEssay($draft, $quest, $judgmentPatterns);
$verification = $gate->verify($draft);

if (!$verification['passed']) {
    $draft = $gate->polish($llm, $draft, $verification['hints'], $quest);
    $verification = $gate->verify($draft);
    $evaluation = $writer->evaluateStructuredEssay($draft, $quest, $judgmentPatterns);
}

$parts = $draft['scqa_parts'] ?? [];
$sentences = [
    $parts['situation'] ?? '',
    $parts['complication'] ?? '',
    $parts['question'] ?? '',
    $parts['answer'] ?? '',
    $parts['conclusion'] ?? '',
];

$hero = trim((string) ($draft['hero_sentence'] ?? ''));
if ($hero === '') {
    $hero = trim((string) ($evaluation['hero_sentence'] ?? ''));
}
if ($hero === '') {
    $hero = eduExtractHeroSentence($sentences);
}

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

$essayStructure = $draft['essay_structure'] ?? [];
$articleSections = $draft['sections'] ?? [];
$bodyParagraphs = is_array($draft['body_paragraphs'] ?? null) ? $draft['body_paragraphs'] : [];
$sentenceExtract = [];
if ($bodyParagraphs !== []) {
    $sentenceExtract = array_values(array_filter(array_map('strval', $bodyParagraphs)));
} else {
    foreach ($articleSections as $sec) {
        if (!is_array($sec)) {
            continue;
        }
        if (!empty($sec['heading'])) {
            $sentenceExtract[] = (string) $sec['heading'];
        }
        foreach ($sec['paragraphs'] ?? [] as $p) {
            if (trim((string) $p) !== '') {
                $sentenceExtract[] = (string) $p;
            }
        }
    }
}
if ($sentenceExtract === []) {
    $sentenceExtract = array_values(array_filter($sentences));
}

$stanceDelta = !empty($blueprint['stance_changed']) ? 'refined' : 'unchanged';
$draftPayload = [
    'v1_sentences' => $sentenceExtract,
    'v2_sentences' => $sentenceExtract,
    'full_text' => (string) ($draft['full_text'] ?? ''),
    'essay_structure' => [
        'title' => $draft['title'] ?? ($essayStructure['title'] ?? ''),
        'subtitle' => $draft['subtitle'] ?? ($essayStructure['subtitle'] ?? ''),
        'structure' => $essayStructure,
        'sections' => $articleSections,
        'conclusion_heading' => $draft['conclusion_heading'] ?? '결론',
        'conclusion_paragraphs' => $draft['conclusion_paragraphs'] ?? [],
    ],
    'hero_sentence' => $hero,
    'stance_delta' => $stanceDelta,
    'teacher_feedback' => $evaluation['feedback'] ?? '',
    'updated_at' => date('c'),
];
$existingDrafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
$draftSave = eduSaveWritingDraft(
    $supabase,
    $sessionId,
    $student['id'],
    $draftPayload,
    $existingDrafts[0] ?? null,
    'compose'
);
if (!$draftSave['ok'] && eduStrictDraftStorage()) {
    eduSendError('Failed to save essay draft: ' . ($draftSave['error'] ?: 'unknown'), 500);
}

$sessionXp = 5;
$tierRow = eduFetchTierRow($student['id']);
$insightRow = null;

$blueprint = eduMergeBlueprint($blueprint, [
    'phase' => 'completed',
    'ready_for_compose' => true,
    'essay_structure' => $essayStructure,
    'essay_artifact' => [
        'title' => $draft['title'] ?? '',
        'subtitle' => $draft['subtitle'] ?? '',
        'sections' => $articleSections,
        'body_paragraphs' => $bodyParagraphs,
        'narration_mode' => !empty($draft['narration_mode']) || $bodyParagraphs !== [],
        'conclusion_heading' => $draft['conclusion_heading'] ?? '결론',
        'conclusion_paragraphs' => $draft['conclusion_paragraphs'] ?? [],
        'full_text' => $draft['full_text'] ?? '',
    ],
    'scqa_slots' => [
        'S' => $sentences[0] ?? '',
        'C' => $sentences[1] ?? '',
        'Q' => $sentences[2] ?? '',
        'A' => $sentences[3] ?? '',
        'conclusion' => $sentences[4] ?? '',
    ],
]);
$dialogue = eduAppendDialogue($dialogue, 'assistant', $draft['full_text'] ?? '', 'composer');

$completedAt = date('c');
$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
    'blueprint_json' => $blueprint,
    'dialogue_json' => $dialogue,
    'stage' => 'completed',
    'completed_at' => $completedAt,
    'updated_at' => $completedAt,
]);

try {
    $sessionForInsight = array_merge($session, [
        'blueprint_json' => $blueprint,
        'dialogue_json' => $dialogue,
        'stage' => 'completed',
        'completed_at' => $completedAt,
    ]);
    $insightRow = eduSaveStructureInsight(
        $supabase,
        $sessionForInsight,
        $quest,
        null,
        trim((string) ($draft['full_text'] ?? ''))
    );
    if (is_array($insightRow) && isset($insightRow['xp_earned'])) {
        $sessionXp = max(5, (int) $insightRow['xp_earned']);
    }
} catch (Throwable $insightErr) {
    error_log('edu insight save: ' . $insightErr->getMessage());
}

$tierRow = eduAwardXp($supabase, $student['id'], $sessionXp, 'structure_quest', $sessionId, [
    'structure_xp' => $sessionXp,
    'quest_code' => $quest['quest_code'] ?? '',
]);
$tierRow = eduStreakOnCompletion($supabase, $student['id']);

$composePayload = [
    'success' => true,
    'session_id' => $sessionId,
    'saved' => true,
    'saved_at' => date('c'),
    'stage' => 'completed',
    'title' => $draft['title'] ?? '',
    'subtitle' => $draft['subtitle'] ?? '',
    'sections' => $articleSections,
    'body_paragraphs' => $bodyParagraphs,
    'narration_mode' => !empty($draft['narration_mode']) || $bodyParagraphs !== [],
    'conclusion_heading' => $draft['conclusion_heading'] ?? '결론',
    'conclusion_paragraphs' => $draft['conclusion_paragraphs'] ?? [],
    'essay_structure' => $essayStructure,
    'full_text' => $draft['full_text'] ?? '',
    'scqa_parts' => $parts,
    'hero_sentence' => $hero,
    'quality_score' => $evaluation['quality_score'] ?? 70,
    'structure_score' => $verification['structure_score'] ?? 3,
    'feedback' => $evaluation['feedback'] ?? '잘 정리했어요!',
    'xp_gained' => $sessionXp,
    'tier' => eduTierProgressPayload($tierRow),
    'coach_level' => eduCoachLevelProfilePayload($student),
    'level_debug_allowed' => eduLevelDebugAllowed($student),
    'progress_pct' => 100,
];
if (eduStructureInsightDebugAllowed($student)) {
    $savedInsight = $insightRow ?? eduFetchStructureInsightRow($supabase, $sessionId);
    $composePayload['structure_insight'] = eduStructureInsightDebugPayload($savedInsight);
}
eduSendJson($composePayload);

} catch (Throwable $e) {
    error_log('edu compose fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    eduSendError('글 생성 중 오류가 났어요. 잠시 후 다시 시도해 주세요.', 503);
}
