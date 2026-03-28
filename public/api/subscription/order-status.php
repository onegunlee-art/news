<?php
/**
 * GET /api/subscription/order-status?order_code=order_XXXXX
 * 결제 실패/취소 후 에러 페이지에서 호출하여 실패 사유를 조회한다.
 *
 * 1) StepPay 주문 조회로 items[0].status 확인
 * 2) 웹훅에서 저장한 last_payment_error 확인
 * 3) 정규화된 메시지로 반환
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';
require_once __DIR__ . '/../lib/log.php';

$orderCode = $_GET['order_code'] ?? '';
if (empty($orderCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_code가 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDb();

$token = getBearerToken();
if ($token) {
    $jwt = decodeJwt($token);
    $jwtUserId = $jwt['user_id'] ?? null;
    if ($jwtUserId) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? AND steppay_order_code = ? LIMIT 1');
        $chk->execute([(int) $jwtUserId, $orderCode]);
        if (!$chk->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '이 주문에 대한 권한이 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$errorMessage = null;
$orderStatus = 'unknown';

$stmt = $pdo->prepare("SELECT last_payment_error FROM users WHERE steppay_order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$dbRow = $stmt->fetch();
if ($dbRow && !empty($dbRow['last_payment_error'])) {
    $errorMessage = $dbRow['last_payment_error'];
}

$orderResult = steppayGetOrder($orderCode);
if ($orderResult['success'] && !empty($orderResult['data'])) {
    $order = $orderResult['data'];
    $itemStatus = $order['items'][0]['status'] ?? null;

    if ($itemStatus === 'PAID') {
        $orderStatus = 'paid';
    } elseif ($itemStatus === 'PAYMENT_FAILURE') {
        $orderStatus = 'failed';
    } elseif ($itemStatus === 'CANCELLED') {
        $orderStatus = 'canceled';
    } elseif ($itemStatus === 'CREATED') {
        $orderStatus = 'pending';
    } else {
        $orderStatus = $itemStatus ?? 'unknown';
    }

    $paymentError = $order['payment']['errorMessage'] ?? null;
    if ($paymentError && !$errorMessage) {
        $errorMessage = $paymentError;
    }
}

$normalized = normalizePaymentError($errorMessage);

if ($orderStatus === 'canceled' || ($errorMessage && preg_match('/취소|cancel/iu', $errorMessage))) {
    $normalized = ['source' => '결제 취소', 'message' => '결제가 취소되었습니다.'];
}

echo json_encode([
    'success' => true,
    'data' => [
        'order_code' => $orderCode,
        'order_status' => $orderStatus,
        'source' => $normalized['source'],
        'message' => $normalized['message'],
        'raw_error' => $errorMessage,
    ],
], JSON_UNESCAPED_UNICODE);
