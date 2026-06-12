<?php
/**
 * POST /api/edu/session/save_essay — 완성 글 수정 저장 (자동/수동)
 *
 * Body: session_id, title?, subtitle?, sections?, conclusion_heading?,
 *       conclusion_paragraphs?, hero_sentence?, full_text?
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduBlueprint.php';
require_once __DIR__ . '/../lib/eduDraftStorage.php';

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

$title = trim((string) ($body['title'] ?? ''));
$subtitle = trim((string) ($body['subtitle'] ?? ''));
$conclusionHeading = trim((string) ($body['conclusion_heading'] ?? '결론'));
$hero = trim((string) ($body['hero_sentence'] ?? ''));

$sections = [];
$rawSections = $body['sections'] ?? [];
if (is_array($rawSections)) {
    foreach ($rawSections as $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $paragraphs = $sec['paragraphs'] ?? [];
        if (!is_array($paragraphs)) {
            $paragraphs = [$paragraphs];
        }
        $sections[] = [
            'heading' => trim((string) ($sec['heading'] ?? '')),
            'paragraphs' => array_values(array_filter(array_map(
                static fn ($p) => trim((string) $p),
                $paragraphs
            ))),
        ];
    }
}

$conclusionParagraphs = $body['conclusion_paragraphs'] ?? [];
if (!is_array($conclusionParagraphs)) {
    $conclusionParagraphs = [$conclusionParagraphs];
}
$conclusionParagraphs = array_values(array_filter(array_map(
    static fn ($p) => trim((string) $p),
    $conclusionParagraphs
)));

$fullText = trim((string) ($body['full_text'] ?? ''));
if ($fullText === '') {
    $fullText = eduBuildEssayFullText($title, $subtitle, $sections, $conclusionHeading, $conclusionParagraphs);
}

if ($fullText === '' && $sections === []) {
    eduSendError('Essay content required');
}

if ($hero === '' && $fullText !== '') {
    $hero = mb_substr($fullText, 0, 80);
}

$existingDrafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
$existing = $existingDrafts[0] ?? [];
$essayStructure = $existing['essay_structure'] ?? [];
if (is_string($essayStructure)) {
    $essayStructure = json_decode($essayStructure, true) ?: [];
}
if (!is_array($essayStructure)) {
    $essayStructure = [];
}

$structureDiagram = $essayStructure['structure'] ?? [];
if (!is_array($structureDiagram)) {
    $structureDiagram = [];
}

$sentenceExtract = [];
foreach ($sections as $sec) {
    if (($sec['heading'] ?? '') !== '') {
        $sentenceExtract[] = (string) $sec['heading'];
    }
    foreach ($sec['paragraphs'] ?? [] as $p) {
        if ($p !== '') {
            $sentenceExtract[] = (string) $p;
        }
    }
}
foreach ($conclusionParagraphs as $p) {
    if ($p !== '') {
        $sentenceExtract[] = (string) $p;
    }
}

$essayStructurePayload = [
    'title' => $title,
    'subtitle' => $subtitle,
    'structure' => $structureDiagram,
    'sections' => $sections,
    'conclusion_heading' => $conclusionHeading !== '' ? $conclusionHeading : '결론',
    'conclusion_paragraphs' => $conclusionParagraphs,
];

$draftPayload = [
    'full_text' => $fullText,
    'essay_structure' => $essayStructurePayload,
    'hero_sentence' => $hero,
    'student_edited' => true,
    'updated_at' => date('c'),
];
if ($sentenceExtract !== []) {
    $draftPayload['v1_sentences'] = $sentenceExtract;
    $draftPayload['v2_sentences'] = $sentenceExtract;
}

$draftSave = eduSaveWritingDraft(
    $supabase,
    $sessionId,
    $student['id'],
    $draftPayload,
    !empty($existing['id']) ? $existing : null,
    'save_essay'
);
if (!$draftSave['ok'] && eduStrictDraftStorage()) {
    eduSendError('Failed to save essay: ' . ($draftSave['error'] ?: 'unknown'), 500);
}

$blueprint = eduLoadBlueprint($session);
$blueprint = eduMergeBlueprint($blueprint, [
    'phase' => 'completed',
    'essay_artifact' => [
        'title' => $title,
        'subtitle' => $subtitle,
        'sections' => $sections,
        'conclusion_heading' => $conclusionHeading !== '' ? $conclusionHeading : '결론',
        'conclusion_paragraphs' => $conclusionParagraphs,
        'full_text' => $fullText,
        'hero_sentence' => $hero,
    ],
]);

$sessionPayload = [
    'blueprint_json' => $blueprint,
    'stage' => 'completed',
    'updated_at' => date('c'),
];
if (($session['stage'] ?? '') !== 'completed') {
    $sessionPayload['completed_at'] = date('c');
}
$supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, $sessionPayload);

if ($hero !== '') {
    $shareCards = $supabase->select('edu_share_cards', 'session_id=eq.' . $sessionId, 1);
    if (!empty($shareCards[0]['id'])) {
        $supabase->update('edu_share_cards', 'session_id=eq.' . $sessionId, [
            'hero_sentence' => $hero,
            'updated_at' => date('c'),
        ]);
    }
}

eduSendJson([
    'success' => true,
    'session_id' => $sessionId,
    'saved' => true,
    'saved_at' => date('c'),
    'title' => $title,
    'subtitle' => $subtitle,
    'sections' => $sections,
    'conclusion_heading' => $conclusionHeading !== '' ? $conclusionHeading : '결론',
    'conclusion_paragraphs' => $conclusionParagraphs,
    'full_text' => $fullText,
    'hero_sentence' => $hero,
]);
