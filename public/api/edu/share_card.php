<?php
/**
 * GIST EDU — Share Card API
 * 
 * GET  /api/edu/share_card?session_id=xxx — 공유 카드 조회
 * POST /api/edu/share_card               — 공유 카드 생성
 * GET  /api/edu/share_card/view?hash=xxx — 공개 공유 카드 조회 (인증 불필요)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

handleOptionsRequest();
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$supabase = eduSupabase();

if ($method === 'GET' && isset($_GET['hash'])) {
    $hash = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['hash']);
    
    $cards = $supabase->select('edu_share_cards', 'share_hash=eq.' . $hash, 1);
    if (empty($cards[0])) {
        eduSendError('Card not found', 404);
    }
    
    $card = $cards[0];
    
    $supabase->update('edu_share_cards', 'id=eq.' . $card['id'], [
        'views_count' => ((int)($card['views_count'] ?? 0)) + 1,
    ]);
    
    $stats = $supabase->select('edu_national_stats', 'quest_id=in.(' . 
        "(select id from edu_daily_quests where quest_code='" . $card['quest_code'] . "')" . ')', 1);
    
    eduSendJson([
        'success' => true,
        'card' => [
            'quest_code' => $card['quest_code'],
            'quest_title' => $card['quest_title'],
            'initial_stance' => $card['initial_stance'],
            'final_stance' => $card['final_stance'],
            'stance_changed' => (bool)$card['stance_changed'],
            'streak_days' => (int)$card['streak_days'],
            'tier_name' => $card['tier_name'],
            'hero_sentence' => $card['hero_sentence'],
            'national_changed_pct' => $card['national_changed_pct'],
        ],
        'views' => (int)$card['views_count'] + 1,
    ]);
}

require_once __DIR__ . '/lib/eduAuth.php';
$student = eduRequireStudent();

if ($method === 'GET') {
    $sessionId = $_GET['session_id'] ?? '';
    if (empty($sessionId)) {
        eduSendError('session_id required');
    }
    
    $existing = $supabase->select('edu_share_cards', 'session_id=eq.' . $sessionId, 1);
    if (!empty($existing[0])) {
        eduSendJson([
            'success' => true,
            'card' => $existing[0],
            'share_url' => 'https://edu.thegist.co.kr/share/' . $existing[0]['share_hash'],
        ]);
    }
    
    eduSendError('Card not found. Create one first.', 404);
}

if ($method === 'POST') {
    eduRequirePost();
    $body = eduJsonBody();
    
    $sessionId = trim((string)($body['session_id'] ?? ''));
    if (empty($sessionId)) {
        eduSendError('session_id required');
    }
    
    $sessions = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
    if (empty($sessions[0]) || $sessions[0]['stage'] !== 'completed') {
        eduSendError('Session not found or not completed', 404);
    }
    $session = $sessions[0];
    
    $existing = $supabase->select('edu_share_cards', 'session_id=eq.' . $sessionId, 1);
    if (!empty($existing[0])) {
        eduSendJson([
            'success' => true,
            'card' => $existing[0],
            'share_url' => 'https://edu.thegist.co.kr/share/' . $existing[0]['share_hash'],
            'already_exists' => true,
        ]);
    }
    
    $quests = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
    $quest = $quests[0] ?? [];
    
    $hypothesis = $supabase->select(
        'edu_hypothesis_versions',
        'session_id=eq.' . $sessionId . '&order=version.asc',
        2
    );
    
    $v1 = $hypothesis[0] ?? [];
    $v2 = $hypothesis[1] ?? $v1;
    
    $initialStance = $v1['stance'] ?? $session['stance'] ?? 'pro';
    $finalStance = $v2['stance'] ?? $initialStance;
    $stanceChanged = $initialStance !== $finalStance;
    
    $tier = $supabase->select('edu_user_tier', 'student_id=eq.' . $student['id'], 1);
    $tierName = $tier[0]['tier_id'] ?? 'bronze';
    $streak = (int)($tier[0]['streak_days'] ?? 0);
    
    $writing = $supabase->select('edu_writing_versions', 'session_id=eq.' . $sessionId . '&order=version.desc', 1);
    $heroSentence = '';
    if (!empty($writing[0])) {
        $heroSentence = $writing[0]['scqa_answer'] ?? $writing[0]['conclusion'] ?? '';
    }
    if (empty($heroSentence)) {
        $drafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
        $heroSentence = $drafts[0]['hero_sentence'] ?? '';
    }
    
    $stats = $supabase->select('edu_national_stats', 'quest_id=eq.' . $session['quest_id'], 1);
    $nationalChangedPct = $stats[0]['stance_changed_pct'] ?? null;
    
    $shareHash = substr(bin2hex(random_bytes(8)), 0, 12);
    
    $cardData = [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
        'quest_code' => $quest['quest_code'] ?? '',
        'quest_title' => $quest['quest_title'] ?? '',
        'initial_stance' => $initialStance,
        'final_stance' => $finalStance,
        'stance_changed' => $stanceChanged,
        'streak_days' => $streak,
        'tier_name' => $tierName,
        'national_changed_pct' => $nationalChangedPct,
        'hero_sentence' => $heroSentence,
        'share_hash' => $shareHash,
    ];
    
    $inserted = $supabase->insert('edu_share_cards', $cardData);
    $card = $inserted[0] ?? $cardData;
    
    eduSendJson([
        'success' => true,
        'card' => $card,
        'share_url' => 'https://edu.thegist.co.kr/share/' . $shareHash,
    ]);
}

eduSendError('Method not allowed', 405);
