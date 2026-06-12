<?php
/**
 * GET /api/edu/quests/today — 오늘의 라이브 퀘스트 조회
 * 호기심 갭: 분포는 답 후 공개
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';

handleOptionsRequest();
setCorsHeaders();

$student = eduGetStudentOptional();
$supabase = eduSupabase();

$quests = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&live_at=not.is.null&live_at=lte.' . rawurlencode(date('c')) . '&order=live_at.desc',
    1
);

if (empty($quests) && $student) {
    $code = eduTodayQuestCode($student);
    $fallback = eduLoadQuestByCode($code);
    if ($fallback) {
        $quests = [$fallback];
    }
}

if (empty($quests)) {
    eduSendJson([
        'success' => true,
        'quest' => null,
        'message' => '오늘은 퀘스트가 없어요. 수, 토, 일 오후 4시에 새 퀘스트가 드랍됩니다!',
        'next_drop' => getNextDropTime(),
    ]);
}

$quest = $quests[0];

$articles = $supabase->select(
    'edu_quest_articles',
    'quest_id=eq.' . $quest['id'] . '&order=sort_order.asc',
    10
);

$stats = $supabase->select(
    'edu_national_stats',
    'quest_id=eq.' . $quest['id'],
    1
);

$totalParticipants = $stats[0]['total_participants'] ?? 0;

$existingSession = $student ? eduActiveSession($student['id']) : null;
$hasAnswered = false;
if ($existingSession && $existingSession['quest_id'] === $quest['id'] && !empty($existingSession['stance'])) {
    $hasAnswered = true;
}

$nationalStats = null;
if ($hasAnswered && !empty($stats[0])) {
    $nationalStats = [
        'pro_pct' => (float)($stats[0]['pro_pct'] ?? 50),
        'con_pct' => (float)($stats[0]['con_pct'] ?? 50),
        'stance_changed_pct' => (float)($stats[0]['stance_changed_pct'] ?? 0),
    ];
}

$articlePayload = [];
foreach ($articles as $a) {
    $articlePayload[] = [
        'news_id' => (int)($a['news_id'] ?? 0),
        'role' => $a['role'] ?? 'primary',
        'title' => $a['title'] ?? '',
        'gist_url' => $a['gist_url'] ?? '',
    ];
}

eduSendJson([
    'success' => true,
    'quest' => [
        'quest_id' => $quest['id'],
        'quest_code' => $quest['quest_code'],
        'quest_title' => $quest['quest_title'],
        'pro_line' => $quest['pro_line'],
        'con_line' => $quest['con_line'],
        'alignment_summary' => $quest['alignment_summary'] ?? '',
        'conflict_summary' => $quest['conflict_summary'],
        'grade_band' => $quest['grade_band'],
        'articles' => $articlePayload,
        'live_at' => $quest['live_at'] ?? null,
        'expires_at' => $quest['expires_at'] ?? null,
    ],
    'participation' => [
        'total' => $totalParticipants,
        'display' => $totalParticipants > 0 ? "지금 {$totalParticipants}명이 참여 중" : "첫 번째 참여자가 되어보세요!",
    ],
    'national_stats' => $nationalStats,
    'curiosity_locked' => !$hasAnswered,
    'existing_session' => $existingSession ? [
        'session_id' => $existingSession['id'],
        'stage' => $existingSession['stage'],
        'stance' => $existingSession['stance'] ?? null,
    ] : null,
]);

function getNextDropTime(): string {
    date_default_timezone_set('Asia/Seoul');
    $now = new DateTime();
    $dropDays = [0, 3, 6];
    $dropHour = 16;
    
    for ($i = 0; $i < 7; $i++) {
        $check = (clone $now)->modify("+{$i} days")->setTime($dropHour, 0);
        if ((int)$check->format('w') === 0 || (int)$check->format('w') === 3 || (int)$check->format('w') === 6) {
            if ($check > $now) {
                return $check->format('c');
            }
        }
    }
    return (clone $now)->modify('+7 days')->format('c');
}
