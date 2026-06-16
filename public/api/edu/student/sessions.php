<?php
/**
 * GET /api/edu/student/sessions.php?status=completed|in_progress|all
 * 학생별 퀘스트 세션 목록 (마이페이지용)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduRequireStudent();
$supabase = eduSupabase();

$status = trim((string) ($_GET['status'] ?? 'completed'));
$query = 'student_id=eq.' . $student['id'];

if ($status === 'completed') {
    $query .= '&stage=eq.completed&order=completed_at.desc';
} elseif ($status === 'in_progress') {
    $query .= '&stage=neq.completed&order=started_at.desc';
} else {
    $query .= '&order=started_at.desc';
}

$sessions = $supabase->select('edu_quest_sessions', $query, 50) ?? [];
if ($sessions === []) {
    eduSendJson(['success' => true, 'sessions' => []]);
}

$questIds = [];
$sessionIds = [];
foreach ($sessions as $row) {
    if (!empty($row['quest_id'])) {
        $questIds[$row['quest_id']] = true;
    }
    if (!empty($row['id'])) {
        $sessionIds[] = $row['id'];
    }
}

$questMap = [];
foreach (array_keys($questIds) as $questId) {
    $quests = $supabase->select('edu_daily_quests', 'id=eq.' . $questId, 1);
    if (!empty($quests[0])) {
        $questMap[$questId] = $quests[0];
    }
}

$draftMap = [];
foreach ($sessionIds as $sessionId) {
    $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
    if (!empty($drafts[0])) {
        $draftMap[$sessionId] = $drafts[0];
    }
}

$items = [];
foreach ($sessions as $row) {
    $sessionId = $row['id'] ?? '';
    $questId = $row['quest_id'] ?? '';
    $quest = $questMap[$questId] ?? [];
    $draft = $draftMap[$sessionId] ?? [];

    $hints = $quest['hammer_hints'] ?? [];
    if (is_string($hints)) {
        $hints = json_decode($hints, true) ?: [];
    }

    $essayStructure = $draft['essay_structure'] ?? [];
    if (is_string($essayStructure)) {
        $essayStructure = json_decode($essayStructure, true) ?: [];
    }

    $title = trim((string) ($essayStructure['title'] ?? ($quest['quest_title'] ?? '')));
    $heroSentence = trim((string) ($draft['hero_sentence'] ?? ''));
    if ($heroSentence === '' && !empty($essayStructure['sections'][0]['paragraphs'][0])) {
        $heroSentence = (string) $essayStructure['sections'][0]['paragraphs'][0];
    }

    $items[] = [
        'session_id' => $sessionId,
        'quest_id' => $questId,
        'quest_code' => $quest['quest_code'] ?? '',
        'quest_title' => $quest['quest_title'] ?? '',
        'time_anchor' => $hints['time_anchor'] ?? null,
        'stance' => $row['stance'] ?? null,
        'stage' => $row['stage'] ?? '',
        'started_at' => $row['started_at'] ?? null,
        'completed_at' => $row['completed_at'] ?? null,
        'essay_title' => $title !== '' ? $title : null,
        'hero_sentence' => $heroSentence !== '' ? $heroSentence : null,
    ];
}

eduSendJson([
    'success' => true,
    'sessions' => $items,
]);
