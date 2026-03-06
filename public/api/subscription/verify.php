<?php
/**
 * POST /api/subscription/verify
 * 결제 완료 후 order_code로 StepPay 주문 조회 → 검증 → DB 구독 상태 업데이트
 *
 * Body: { "order_code": "order_XXXXX" }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$orderCode = $input['order_code'] ?? '';
if (empty($orderCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_code가 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderResult = steppayGetOrder($orderCode);
if (!$orderResult['success']) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => '주문 조회에 실패했습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$order = $orderResult['data'];
$paymentDate = $order['paymentDate'] ?? null;

if (empty($paymentDate)) {
    echo json_encode(['success' => false, 'message' => '결제가 아직 완료되지 않았습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subscriptions = $order['subscriptions'] ?? [];
$subscriptionId = null;
$expiresAt = null;

if (!empty($subscriptions)) {
    $sub = $subscriptions[0];
    $subscriptionId = $sub['id'] ?? null;
    $expiresAt = $sub['endDate'] ?? $sub['currentPeriodEnd'] ?? null;
}

if (!$expiresAt) {
    $cfg = getSteppayConfig();
    $plans = $cfg['plans'] ?? [];
    $months = 1;
    foreach ($plans as $plan) {
        if ($plan['price_code'] === ($order['items'][0]['price']['code'] ?? '')) {
            $months = $plan['months'];
            break;
        }
    }
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$months} months"));
}

$pdo->prepare("UPDATE users SET is_subscribed = 1, subscription_expires_at = ?, steppay_subscription_id = ?, steppay_order_code = ? WHERE id = ?")
    ->execute([$expiresAt, $subscriptionId, $orderCode, $userId]);

echo json_encode([
    'success' => true,
    'data' => [
        'is_subscribed' => true,
        'subscription_expires_at' => $expiresAt,
        'subscription_id' => $subscriptionId,
    ],
], JSON_UNESCAPED_UNICODE);
