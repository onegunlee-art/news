<?php
/**
 * GET /api/subscription/status
 * 현재 사용자의 구독 상태 조회 — StepPay 실제 상태와 DB를 능동적으로 동기화
 */

require_once __DIR__ . '/../lib/cors.php';
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';

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
$subscriptionId = $user['steppay_subscription_id'] ?? null;

// StepPay 실제 상태와 DB 동기화
if ($subscriptionId) {
    $spResult = steppayGetSubscription((int) $subscriptionId);
    if ($spResult['success'] && !empty($spResult['data'])) {
        $spData = $spResult['data'];
        $spStatus = $spData['status'] ?? '';
        $spActive = in_array($spStatus, ['ACTIVE', 'PENDING_PAUSE', 'PENDING_CANCEL'], true);
        $spExpiresAt = $spData['currentPeriodEnd'] ?? $spData['endDate'] ?? $expiresAt;

        if ($spActive !== $isSubscribed || $spExpiresAt !== $expiresAt) {
            $pdo->prepare("UPDATE users SET is_subscribed = ?, subscription_expires_at = ? WHERE id = ?")
                ->execute([$spActive ? 1 : 0, $spExpiresAt, $userId]);
            $isSubscribed = $spActive;
            $expiresAt = $spExpiresAt;
        }
    }
} elseif ($isSubscribed && $expiresAt && strtotime($expiresAt) < time()) {
    $isSubscribed = false;
    $pdo->prepare("UPDATE users SET is_subscribed = 0 WHERE id = ?")->execute([$userId]);
}

echo json_encode([
    'success' => true,
    'data' => [
        'is_subscribed' => $isSubscribed,
        'subscription_expires_at' => $expiresAt,
        'subscription_id' => $subscriptionId,
    ],
], JSON_UNESCAPED_UNICODE);
