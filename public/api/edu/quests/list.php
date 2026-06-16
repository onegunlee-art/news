<?php
/**
 * GET /api/edu/quests/list.php — approved 결정탐구 퀘스트 목록 (live_at 무관)
 *
 * Query:
 *   limit=20 (max 50)
 *   frame=decision_inquiry (default) | all
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduGetStudentOptional();
$supabase = eduSupabase();

$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$frame = trim((string) ($_GET['frame'] ?? 'decision_inquiry'));

$filter = 'status=eq.approved&order=live_at.desc.nullslast,created_at.desc';
if ($frame !== 'all') {
    $filter .= '&hammer_hints->>quest_frame=eq.' . rawurlencode($frame);
}

$rows = $supabase->select('edu_daily_quests', $filter, $limit) ?? [];

$completedIds = [];
if ($student !== null && $rows !== []) {
    $sessions = $supabase->select(
        'edu_quest_sessions',
        'student_id=eq.' . $student['id'] . '&stage=eq.completed',
        200
    ) ?? [];
    foreach ($sessions as $s) {
        if (!empty($s['quest_id'])) {
            $completedIds[$s['quest_id']] = true;
        }
    }
}

$now = time();
$quests = [];
foreach ($rows as $quest) {
    $hints = eduQuestHammerHints($quest);
    $questId = (string) ($quest['id'] ?? '');
    $liveAt = $quest['live_at'] ?? null;
    $isLive = $liveAt !== null && $liveAt !== '' && strtotime((string) $liveAt) <= $now;

    $quests[] = [
        'quest_id' => $questId,
        'quest_code' => $quest['quest_code'] ?? '',
        'quest_title' => $quest['quest_title'] ?? '',
        'pro_line' => $quest['pro_line'] ?? '',
        'con_line' => $quest['con_line'] ?? '',
        'conflict_summary' => $quest['conflict_summary'] ?? '',
        'grade_band' => $quest['grade_band'] ?? 'middle',
        'time_anchor' => $hints['time_anchor'] ?? null,
        'quest_frame' => $hints['quest_frame'] ?? null,
        'is_live' => $isLive,
        'live_at' => $liveAt,
        'completed' => isset($completedIds[$questId]),
    ];
}

eduSendJson([
    'success' => true,
    'quests' => $quests,
    'count' => count($quests),
]);
