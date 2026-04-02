<?php
/**
 * POST /api/subscription/order
 * 구독 플랜 선택 → StepPay 주문 생성 → 결제 URL 반환
 *
 * Body: { "planId": "1m" | "3m" | "6m" | "12m", "promoCode"?: "OPEN30" }
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
$planId = $input['planId'] ?? '';
$onetimeId = $input['onetimeId'] ?? '';
$promoCodeRaw = isset($input['promoCode']) ? trim((string) $input['promoCode']) : '';

$cfg = getSteppayConfig();
$plans = $cfg['plans'] ?? [];
$onetimeProducts = $cfg['onetime_products'] ?? [];

$plan = null;
$productCode = null;
$priceCode = null;
$promotionIdForOrder = null;
$discountedAmountForLog = null;
$originalAmountForLog = null;

if ($onetimeId && isset($onetimeProducts[$onetimeId])) {
    if ($promoCodeRaw !== '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '단건 상품에는 프로모션 코드를 적용할 수 없습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $plan = $onetimeProducts[$onetimeId];
    $productCode = $plan['product_code'];
    $priceCode = $plan['price_code'];
} elseif ($planId && isset($plans[$planId])) {
    $plan = $plans[$planId];
    $productCode = $cfg['product_code'];
    $priceCode = $plan['price_code'];

    if ($promoCodeRaw !== '') {
        $pv = promotionValidateForPlan($pdo, $promoCodeRaw, $planId, $cfg);
        if (!$pv['ok']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $pv['message'] ?? '프로모션 코드를 확인해 주세요.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $block = promotionAssertUserCanUse($pdo, (int) $pv['promotion']['id'], $userId);
        if ($block !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $block], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $priceCode = $pv['price_code'];
        $productCode = $pv['product_code'];
        $promotionIdForOrder = (int) $pv['promotion']['id'];
        $discountedAmountForLog = $pv['discounted_amount'];
        $originalAmountForLog = $pv['original_amount'];
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 상품입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nickname, email, steppay_customer_id, steppay_order_code FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '사용자를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

payment_log('주문 시작', [
    'userId' => $userId,
    'planId' => $planId ?: $onetimeId,
    'steppay_customer_id' => $user['steppay_customer_id'],
    'promotion_id' => $promotionIdForOrder,
    'discounted_amount' => $discountedAmountForLog,
], $userId);

$steppayCustomerId = $user['steppay_customer_id'];

if ($steppayCustomerId) {
    $updateResult = steppayUpdateCustomer(
        (int) $steppayCustomerId,
        $user['nickname'] ?: '회원',
        $user['email']
    );
    payment_log('기존 고객 정보 갱신', ['id' => $steppayCustomerId, 'success' => $updateResult['success']], $userId);
}

if (!$steppayCustomerId) {
    $customerCode = 'thegist_user_' . $userId;

    $custResult = steppayCreateCustomer(
        $user['nickname'] ?: '회원',
        $user['email'],
        $customerCode
    );
    payment_log('고객 생성 응답', $custResult, $userId);

    if (!$custResult['success'] || empty($custResult['data']['id'])) {
        payment_log('고객 생성 실패, 기존 고객 검색 시도', ['code' => $customerCode], $userId);
        $searchResult = steppaySearchCustomerByCode($customerCode);
        payment_log('고객 검색 응답', $searchResult, $userId);

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
            $dupCheck = $pdo->prepare("SELECT id FROM users WHERE steppay_customer_id = ? AND id != ? LIMIT 1");
            $dupCheck->execute([$existingId, $userId]);
            if ($dupCheck->fetch()) {
                payment_log('WARN: 기존 고객 ID가 다른 사용자에 할당됨, 새 고객 생성', ['existingId' => $existingId, 'userId' => $userId], $userId);
                $existingId = null;
            } else {
                $steppayCustomerId = (int) $existingId;
                payment_log('기존 고객 발견', ['id' => $steppayCustomerId], $userId);
            }
        }
        if (!$existingId) {
            $uniqueCode = 'thegist_user_' . $userId . '_' . substr(md5((string)microtime(true)), 0, 6);
            $custRetry = steppayCreateCustomer(
                $user['nickname'] ?: '회원',
                $user['email'],
                $uniqueCode
            );
            payment_log('유니크 코드로 고객 재생성 응답', $custRetry, $userId);

            if (!$custRetry['success'] || empty($custRetry['data']['id'])) {
                http_response_code(502);
                echo json_encode([
                    'success' => false,
                    'source' => '일시적 오류',
                    'message' => '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $steppayCustomerId = (int) $custRetry['data']['id'];
        }
    } else {
        $steppayCustomerId = (int) $custResult['data']['id'];
    }

    $pdo->prepare("UPDATE users SET steppay_customer_id = ? WHERE id = ?")->execute([$steppayCustomerId, $userId]);
    payment_log('DB 고객 ID 저장', ['steppay_customer_id' => $steppayCustomerId], $userId);
}

// 진행 중인 미결제 주문 처리: 최근(10분 이내) CREATED 주문이면 기존 결제 URL 반환,
// 오래된 CREATED 주문이면 새 주문으로 교체 허용
$existingOrderCode = trim((string) ($user['steppay_order_code'] ?? ''));
if ($existingOrderCode !== '') {
    $existingFetch = steppayGetOrder($existingOrderCode);
    if ($existingFetch['success'] && !empty($existingFetch['data'])) {
        $ex = $existingFetch['data'];
        $itemStatus = $ex['items'][0]['status'] ?? '';
        $alreadyPaid = !empty($ex['paymentDate']) || $itemStatus === 'PAID';
        if (!$alreadyPaid && ($itemStatus === 'CREATED' || $itemStatus === '' || $itemStatus === null)) {
            $orderCreatedAt = $ex['createdAt'] ?? $ex['orderedAt'] ?? null;
            $isRecent = false;
            if ($orderCreatedAt) {
                $createdTs = strtotime($orderCreatedAt);
                $isRecent = $createdTs !== false && (time() - $createdTs) < 600;
            }
            if ($isRecent) {
                $existingPaymentUrl = steppayGetPaymentUrl($existingOrderCode);
                payment_log('기존 미결제 주문 재사용 (10분 이내)', ['orderCode' => $existingOrderCode, 'userId' => $userId], $userId);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'orderCode' => $existingOrderCode,
                        'orderId' => $ex['orderId'] ?? null,
                        'paymentUrl' => $existingPaymentUrl,
                        'plan' => $plan,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            payment_log('기존 미결제 주문 만료, 새 주문 생성 허용', ['oldOrderCode' => $existingOrderCode, 'userId' => $userId], $userId);
        }
    }
}

$orderResult = steppayCreateOrder($steppayCustomerId, $priceCode, $productCode);
payment_log('주문 생성 응답', $orderResult, $userId);

if (!$orderResult['success'] || empty($orderResult['data']['orderCode'])) {
    http_response_code(502);
    $apiMsg = $orderResult['data']['message'] ?? $orderResult['error'] ?? null;
    $normalized = normalizePaymentError($apiMsg);
    echo json_encode([
        'success' => false,
        'source' => $normalized['source'],
        'message' => $normalized['message'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderCode = $orderResult['data']['orderCode'];
$orderId = $orderResult['data']['orderId'] ?? null;

$pendingPlan = ($planId && isset($plans[$planId])) ? $planId : null;
$pendingPromo = $promotionIdForOrder;

$pdo->prepare(
    "UPDATE users SET steppay_order_code = ?, pending_checkout_plan_id = ?, pending_promotion_code_id = ? WHERE id = ?"
)->execute([$orderCode, $pendingPlan, $pendingPromo, $userId]);

if ($promotionIdForOrder !== null && $pendingPlan !== null && $originalAmountForLog !== null && $discountedAmountForLog !== null) {
    try {
        promotionUpsertPendingUsage(
            $pdo,
            $promotionIdForOrder,
            $userId,
            $pendingPlan,
            $originalAmountForLog,
            $discountedAmountForLog,
            $orderCode
        );
    } catch (Throwable $e) {
        payment_log('promotion usage upsert 실패', ['error' => $e->getMessage(), 'userId' => $userId], $userId);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => '주문 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$paymentUrl = steppayGetPaymentUrl($orderCode);

payment_log('주문 완료', ['orderCode' => $orderCode, 'paymentUrl' => $paymentUrl], $userId);

echo json_encode([
    'success' => true,
    'data' => [
        'orderCode' => $orderCode,
        'orderId' => $orderId,
        'paymentUrl' => $paymentUrl,
        'plan' => $plan,
    ],
], JSON_UNESCAPED_UNICODE);
