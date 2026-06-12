<?php
/**
 * GET /api/edu/stats/national.php?quest_id=xxx
 * 전국 통계 조회 (% only)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

handleOptionsRequest();
setCorsHeaders();

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

$questId = $_GET['quest_id'] ?? '';
if (empty($questId)) {
    eduSendError('quest_id required');
}

$quests = $supabase->select('edu_daily_quests', 'id=eq.' . rawurlencode($questId), 1);
if (empty($quests[0])) {
    eduSendError('Quest not found', 404);
}
$quest = $quests[0];

$stats = $supabase->select('edu_national_stats', 'quest_id=eq.' . rawurlencode($questId), 1);

$studentStance = null;

$token = $_SERVER['HTTP_X_EDU_TOKEN'] ?? '';
if (!empty($token)) {
    $hash = hash('sha256', $token);
    $students = $supabase->select('edu_students', 'access_token_hash=eq.' . rawurlencode($hash), 1);
    if (!empty($students[0]['id'])) {
        $studentId = $students[0]['id'];
        $sessions = $supabase->select(
            'edu_quest_sessions',
            'student_id=eq.' . $studentId . '&quest_id=eq.' . rawurlencode($questId),
            1
        );
        if (!empty($sessions[0]['stance'])) {
            $studentStance = $sessions[0]['stance'];
        }
    }
}

eduSendJson([
    'success' => true,
    'quest' => [
        'quest_id' => $quest['id'],
        'quest_code' => $quest['quest_code'],
        'quest_title' => $quest['quest_title'],
        'pro_line' => $quest['pro_line'],
        'con_line' => $quest['con_line'],
    ],
    'stats' => [
        'pro_pct' => (float)($stats[0]['pro_pct'] ?? 50),
        'con_pct' => (float)($stats[0]['con_pct'] ?? 50),
        'stance_changed_pct' => (float)($stats[0]['stance_changed_pct'] ?? 0),
    ],
    'student_stance' => $studentStance,
]);
