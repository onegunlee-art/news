<?php
/**
 * GET /api/edu/session/state?session_id= — blueprint, dialogue, completed essay 복구
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduBlueprint.php';
require_once __DIR__ . '/../lib/eduCoachGuide.php';

handleOptionsRequest();
setCorsHeaders();

$student = eduRequireStudent();
$supabase = eduSupabase();

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    eduSendError('session_id required');
}

$sessions = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($sessions[0])) {
    eduSendError('Session not found', 404);
}
$session = $sessions[0];

eduGuardSessionAbandoned($session, $sessionId);

$quests = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
$quest = $quests[0] ?? [];
$quest['articles'] = $supabase->select(
    'edu_quest_articles',
    'quest_id=eq.' . $session['quest_id'] . '&order=sort_order.asc',
    20
) ?? [];

$blueprint = eduLoadBlueprint($session);
$dialogue = eduLoadDialogue($session, true);

$essay = null;
if (($session['stage'] ?? '') === 'completed') {
    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    $versions = $supabase->select(
        'edu_writing_versions',
        'session_id=eq.' . $sessionId . '&order=version.desc',
        1
    );
    $draft = $drafts[0] ?? [];
    $artifact = $draft['essay_structure'] ?? [];
    if (is_string($artifact)) {
        $artifact = json_decode($artifact, true) ?: [];
    }
    // Legacy: blueprint essay_artifact fallback until backfill completes (see tools/edu_backfill_essay_drafts.php)
    if (empty($artifact['sections']) && !empty($blueprint['essay_artifact'])) {
        $artifact = $blueprint['essay_artifact'];
    }

    $fullText = trim((string) ($draft['full_text'] ?? ($artifact['full_text'] ?? '')));
    if ($fullText === '') {
        $v2 = $draft['v2_sentences'] ?? [];
        if (is_string($v2)) {
            $v2 = json_decode($v2, true) ?: [];
        }
        if (is_array($v2) && $v2 !== []) {
            $fullText = implode("\n\n", array_filter(array_map('strval', $v2)));
        }
    }
    if ($fullText === '' && !empty($versions[0])) {
        $parts = [
            $versions[0]['scqa_situation'] ?? '',
            $versions[0]['scqa_complication'] ?? '',
            $versions[0]['scqa_question'] ?? '',
            $versions[0]['scqa_answer'] ?? '',
            $versions[0]['conclusion'] ?? '',
        ];
        $fullText = implode("\n\n", array_filter($parts));
    }

    $essay = [
        'title' => $artifact['title'] ?? ($blueprint['essay_artifact']['title'] ?? null),
        'subtitle' => $artifact['subtitle'] ?? ($blueprint['essay_artifact']['subtitle'] ?? null),
        'sections' => $artifact['sections'] ?? ($blueprint['essay_artifact']['sections'] ?? []),
        'conclusion_heading' => $artifact['conclusion_heading'] ?? '결론',
        'conclusion_paragraphs' => $artifact['conclusion_paragraphs'] ?? [],
        'full_text' => $fullText,
        'hero_sentence' => $draft['hero_sentence'] ?? null,
        'feedback' => $draft['teacher_feedback'] ?? ($versions[0]['ai_feedback'] ?? null),
        'quality_score' => (int) ($versions[0]['quality_score'] ?? 0),
        'scqa_parts' => !empty($versions[0]) ? [
            'situation' => $versions[0]['scqa_situation'] ?? '',
            'complication' => $versions[0]['scqa_complication'] ?? '',
            'question' => $versions[0]['scqa_question'] ?? '',
            'answer' => $versions[0]['scqa_answer'] ?? '',
            'conclusion' => $versions[0]['conclusion'] ?? '',
        ] : null,
        'stance_changed' => ($draft['stance_delta'] ?? '') === 'flipped'
            || ($draft['stance_delta'] ?? '') === 'refined',
    ];
}

$statePayload = [
    'success' => true,
    'session_id' => $sessionId,
    'stage' => $session['stage'] ?? 'commit',
    'quest' => eduPublicQuestPayload(array_merge($quest, ['articles' => $quest['articles'] ?? []])),
    'blueprint' => $blueprint,
    'dialogue' => $dialogue,
    'progress_pct' => eduBlueprintProgress($blueprint),
    'essay' => $essay,
];

if (eduQuestUsesAxisGuide($quest)) {
    for ($i = count($dialogue) - 1; $i >= 0; $i--) {
        if (($dialogue[$i]['role'] ?? '') === 'assistant') {
            $choiceMeta = eduCoachGuideChoiceMeta(
                $blueprint,
                $quest,
                (string) ($dialogue[$i]['content'] ?? '')
            );
            if ($choiceMeta !== null) {
                $statePayload = array_merge($statePayload, $choiceMeta);
            }
            break;
        }
    }
}

eduSendJson($statePayload);
