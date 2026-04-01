<?php
/**
 * POST /api/subscription/verify
 * 결제 완료 후 order_code로 StepPay 주문 조회 → 검증 → DB 구독 상태 업데이트
 *
 * Body: { "order_code": "order_XXXXX" }
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
require_once __DIR__ . '/../lib/subscription_order_apply.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/promotion_codes.php';

$pdo = getDb();
$userId = getAuthUserId($pdo);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$orderCode = $input['order_code'] ?? '';
if (empty($orderCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_code가 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 주문 소유권 검증: 이 사용자가 생성한 주문인지 확인
$ownerStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND steppay_order_code = ? LIMIT 1');
$ownerStmt->execute([$userId, $orderCode]);
if (!$ownerStmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '이 주문에 대한 권한이 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$retryDelays = [2, 3, 3, 4, 4, 4, 5];
$maxRetries = count($retryDelays);
$order = null;
$paymentDate = null;
$lastApiError = null;

for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
    $orderResult = steppayGetOrder($orderCode);
    if (!$orderResult['success']) {
        $lastApiError = $orderResult['error'] ?? 'API 호출 실패 (HTTP ' . ($orderResult['http_code'] ?? '?') . ')';
        payment_log("verify API 실패 attempt={$attempt} orderCode={$orderCode}", $orderResult);
        if ($attempt < $maxRetries) {
            sleep($retryDelays[$attempt]);
            continue;
        }
        payment_log("verify 최종 실패 orderCode={$orderCode} userId={$userId}");
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => '주문 조회에 실패했습니다. 잠시 후 자동 반영됩니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $lastApiError = null;
    $order = $orderResult['data'];
    $paymentDate = $order['paymentDate'] ?? null;
    if (!empty($paymentDate)) break;
    if ($attempt < $maxRetries) sleep($retryDelays[$attempt]);
}

if (empty($paymentDate)) {
    echo json_encode([
        'success' => false,
        'status' => 'pending',
        'message' => '결제 확인 중입니다. 잠시 후 자동으로 반영됩니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

applyVerifiedOrderToUserDb($pdo, $userId, $orderCode, $order);

$snapshot = $pdo->prepare('SELECT subscription_expires_at, steppay_subscription_id, subscription_plan FROM users WHERE id = ? LIMIT 1');
$snapshot->execute([$userId]);
$snap = $snapshot->fetch(PDO::FETCH_ASSOC) ?: [];
$expiresAt = $snap['subscription_expires_at'] ?? null;
$subscriptionId = $snap['steppay_subscription_id'] ?? null;
$matchedPlanId = $snap['subscription_plan'] ?? null;

try {
    promotionMarkUsageCompleted($pdo, $userId, $orderCode);
} catch (Throwable $e) {
    payment_log('promotionMarkUsageCompleted 실패', ['error' => $e->getMessage(), 'userId' => $userId]);
}

payment_log("verify 성공 userId={$userId} plan=" . ($matchedPlanId ?? '') . " expires={$expiresAt}");

echo json_encode([
    'success' => true,
    'data' => [
        'is_subscribed' => true,
        'subscription_expires_at' => $expiresAt,
        'subscription_id' => $subscriptionId,
    ],
], JSON_UNESCAPED_UNICODE);
