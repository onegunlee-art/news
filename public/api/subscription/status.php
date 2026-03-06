<?php
/**
 * GET /api/subscription/status
 * 현재 사용자의 구독 상태 조회
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../lib/auth.php';

$pdo = getDb();
$userId = getAuthUserId($pdo);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT is_subscribed, subscription_expires_at, steppay_subscription_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$isSubscribed = (bool)($user['is_subscribed'] ?? false);
$expiresAt = $user['subscription_expires_at'] ?? null;

if ($isSubscribed && $expiresAt && strtotime($expiresAt) < time()) {
    $isSubscribed = false;
    $pdo->prepare("UPDATE users SET is_subscribed = 0 WHERE id = ?")->execute([$userId]);
}

echo json_encode([
    'success' => true,
    'data' => [
        'is_subscribed' => $isSubscribed,
        'subscription_expires_at' => $expiresAt,
        'subscription_id' => $user['steppay_subscription_id'] ?? null,
    ],
], JSON_UNESCAPED_UNICODE);
