<?php
/**
 * POST { invite_code } → { token, student }
 * URL: /api/edu/invite/redeem.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduTier.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$body = eduJsonBody();
$code = trim((string) ($body['invite_code'] ?? ''));
if ($code === '') {
    eduSendError('invite_code is required');
}

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('EDU service not configured', 503);
}

$rows = $supabase->select('edu_students', 'invite_code=eq.' . rawurlencode($code) . '&status=eq.active', 1);
if (empty($rows[0]['id'])) {
    eduSendError('Invalid invite code', 401);
}

$token = bin2hex(random_bytes(16));
$hash = hash('sha256', $token);
$student = $rows[0];

$supabase->update('edu_students', 'id=eq.' . $student['id'], [
    'access_token_hash' => $hash,
    'last_active_at' => date('c'),
]);

eduFetchTierRow($student['id']);

eduSendJson([
    'success' => true,
    'token' => $token,
    'student' => [
        'id' => $student['id'],
        'display_name' => $student['display_name'],
        'grade_band' => $student['grade_band'],
    ],
]);
