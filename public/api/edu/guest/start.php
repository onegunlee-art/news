<?php
/**
 * POST /api/edu/guest/start.php
 * 게스트 모드로 퀘스트 체험 (테스트용)
 * 임시 학생 계정 생성 후 토큰 발급
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduTier.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('EDU service not configured', 503);
}

$guestId = 'guest_' . bin2hex(random_bytes(8));
$displayName = '게스트_' . substr($guestId, 6, 6);
$inviteCode = 'GUEST-' . strtoupper(bin2hex(random_bytes(4)));

$token = bin2hex(random_bytes(16));
$hash = hash('sha256', $token);

$inserted = $supabase->insert('edu_students', [
    'display_name' => $displayName,
    'grade_band' => 'high',
    'invite_code' => $inviteCode,
    'access_token_hash' => $hash,
    'status' => 'active',
]);

if (empty($inserted['id'])) {
    eduSendError('게스트 생성 실패', 500);
}

$studentId = $inserted['id'];

eduFetchTierRow($studentId);

eduSendJson([
    'success' => true,
    'token' => $token,
    'student' => [
        'id' => $studentId,
        'display_name' => $displayName,
        'grade_band' => 'high',
    ],
]);
