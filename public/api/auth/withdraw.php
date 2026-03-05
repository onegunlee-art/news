<?php
/**
 * 회원 탈퇴 (자기 계정 삭제)
 * POST /api/auth/withdraw
 *
 * 가입 동의 거부 시 또는 직접 탈퇴 시 사용.
 * JWT에서 user_id를 추출해 해당 사용자를 DB에서 삭제한다.
 * (CASCADE 외래키로 user_tokens, bookmarks 등 자동 삭제)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';

$pdo = getDb();
$userId = getAuthUserId($pdo);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '인증이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    echo json_encode(['success' => true, 'message' => '탈퇴가 완료되었습니다.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '탈퇴 처리 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
}
