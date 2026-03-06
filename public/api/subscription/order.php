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

$logDir = $_SERVER['DOCUMENT_ROOT'] . '/../storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
function orderLog(string $msg, $data = null): void {
    global $logDir;
    $line = date('[Y-m-d H:i:s] ') . $msg;
    if ($data !== null) $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($logDir . '/subscription_order.log', $line . "\n", FILE_APPEND);
}

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

orderLog('주문 시작', ['userId' => $userId, 'planId' => $planId, 'steppay_customer_id' => $user['steppay_customer_id']]);

$steppayCustomerId = $user['steppay_customer_id'];

if (!$steppayCustomerId) {
    $customerCode = 'thegist_user_' . $userId;

    $custResult = steppayCreateCustomer(
        $user['nickname'] ?: '회원',
        $user['email'],
        $customerCode
    );
    orderLog('고객 생성 응답', $custResult);

    if (!$custResult['success'] || empty($custResult['data']['id'])) {
        orderLog('고객 생성 실패, 기존 고객 검색 시도', ['code' => $customerCode]);
        $searchResult = steppaySearchCustomerByCode($customerCode);
        orderLog('고객 검색 응답', $searchResult);

        $existingId = null;
        if ($searchResult['success'] && !empty($searchResult['data'])) {
            if (isset($searchResult['data']['content']) && !empty($searchResult['data']['content'])) {
                $existingId = $searchResult['data']['content'][0]['id'] ?? null;
            } elseif (isset($searchResult['data']['id'])) {
                $existingId = $searchResult['data']['id'];
            } elseif (isset($searchResult['data'][0]['id'])) {
                $existingId = $searchResult['data'][0]['id'];
            }
        }

        if ($existingId) {
            $steppayCustomerId = (int) $existingId;
            orderLog('기존 고객 발견', ['id' => $steppayCustomerId]);
        } else {
            $custRetry = steppayCreateCustomer(
                $user['nickname'] ?: '회원',
                $user['email']
            );
            orderLog('코드 없이 고객 재생성 응답', $custRetry);

            if (!$custRetry['success'] || empty($custRetry['data']['id'])) {
                http_response_code(502);
                echo json_encode(['success' => false, 'message' => '결제 고객 등록에 실패했습니다.', 'detail' => $custRetry], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $steppayCustomerId = (int) $custRetry['data']['id'];
        }
    } else {
        $steppayCustomerId = (int) $custResult['data']['id'];
    }

    $pdo->prepare("UPDATE users SET steppay_customer_id = ? WHERE id = ?")->execute([$steppayCustomerId, $userId]);
    orderLog('DB 고객 ID 저장', ['steppay_customer_id' => $steppayCustomerId]);
}

$orderResult = steppayCreateOrder($steppayCustomerId, $priceCode, $productCode);
orderLog('주문 생성 응답', $orderResult);

if (!$orderResult['success'] || empty($orderResult['data']['orderCode'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => '주문 생성에 실패했습니다.', 'detail' => $orderResult], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderCode = $orderResult['data']['orderCode'];
$orderId = $orderResult['data']['orderId'] ?? null;

$pdo->prepare("UPDATE users SET steppay_order_code = ? WHERE id = ?")->execute([$orderCode, $userId]);

$paymentUrl = steppayGetPaymentUrl($orderCode);

orderLog('주문 완료', ['orderCode' => $orderCode, 'paymentUrl' => $paymentUrl]);

echo json_encode([
    'success' => true,
    'data' => [
        'orderCode' => $orderCode,
        'orderId' => $orderId,
        'paymentUrl' => $paymentUrl,
        'plan' => $plan,
    ],
], JSON_UNESCAPED_UNICODE);
