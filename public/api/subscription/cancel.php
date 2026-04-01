<?php
/**
 * POST /api/subscription/cancel
 * StepPay 구독 취소 (whenToCancel = END_OF_PERIOD: 현재 기간 만료 후 취소)
 */

require_once __DIR__ . '/../lib/cors.php';
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
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

$stmt = $pdo->prepare("SELECT steppay_subscription_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$subscriptionId = $user['steppay_subscription_id'] ?? null;
if (empty($subscriptionId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '구독 정보가 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subscriptionId = (int) $subscriptionId;
$result = steppayCancelSubscription($subscriptionId, 'END_OF_PERIOD');

if (!$result['success']) {
    $message = $result['data']['errorMessage'] ?? $result['data']['message'] ?? $result['error'] ?? '구독 취소에 실패했습니다.';
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$subResult = steppayGetSubscription($subscriptionId);
if ($subResult['success'] && !empty($subResult['data'])) {
    $sub = $subResult['data'];
    $status = $sub['status'] ?? '';
    $isActive = in_array($status, ['ACTIVE', 'PENDING_PAUSE', 'PENDING_CANCEL']);
    $expiresAt = $sub['currentPeriodEnd'] ?? $sub['endDate'] ?? null;
    $pdo->prepare("UPDATE users SET is_subscribed = ?, subscription_expires_at = ? WHERE id = ?")
        ->execute([$isActive ? 1 : 0, $expiresAt, $userId]);
}

echo json_encode(['success' => true, 'message' => '다음 결제일부터 구독이 해지됩니다. 현재 기간까지는 정상 이용 가능합니다.'], JSON_UNESCAPED_UNICODE);
