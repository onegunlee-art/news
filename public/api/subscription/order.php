<?php
/**
 * POST /api/subscription/order
 * 구독 플랜 선택 → StepPay 주문 생성 → 결제 URL 반환
 *
 * Body: { "planId": "1m" | "3m" | "6m" | "12m" }
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
$planId = $input['planId'] ?? '';

$cfg = getSteppayConfig();
$plans = $cfg['plans'] ?? [];
if (!isset($plans[$planId])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 플랜입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$plan = $plans[$planId];
$productCode = $cfg['product_code'];
$priceCode = $plan['price_code'];

$stmt = $pdo->prepare("SELECT id, nickname, email, steppay_customer_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '사용자를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$steppayCustomerId = $user['steppay_customer_id'];

if (!$steppayCustomerId) {
    $custResult = steppayCreateCustomer(
        $user['nickname'] ?: '회원',
        $user['email'],
        'thegist_user_' . $userId
    );
    if (!$custResult['success'] || empty($custResult['data']['id'])) {
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => '결제 고객 등록에 실패했습니다.', 'detail' => $custResult], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $steppayCustomerId = (int) $custResult['data']['id'];
    $pdo->prepare("UPDATE users SET steppay_customer_id = ? WHERE id = ?")->execute([$steppayCustomerId, $userId]);
}

$orderResult = steppayCreateOrder($steppayCustomerId, $priceCode, $productCode);
if (!$orderResult['success'] || empty($orderResult['data']['orderCode'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => '주문 생성에 실패했습니다.', 'detail' => $orderResult], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderCode = $orderResult['data']['orderCode'];
$orderId = $orderResult['data']['orderId'] ?? null;

$pdo->prepare("UPDATE users SET steppay_order_code = ? WHERE id = ?")->execute([$orderCode, $userId]);

$paymentUrl = steppayGetPaymentUrl($orderCode);

echo json_encode([
    'success' => true,
    'data' => [
        'orderCode' => $orderCode,
        'orderId' => $orderId,
        'paymentUrl' => $paymentUrl,
        'plan' => $plan,
    ],
], JSON_UNESCAPED_UNICODE);
