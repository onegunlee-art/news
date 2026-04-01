<?php
/**
 * POST /api/webhook/steppay
 * StepPay 웹훅 수신: 구독 상태 변경, 결제 성공/실패 등
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';
require_once __DIR__ . '/../lib/subscription_order_apply.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/promotion_codes.php';

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$cfg = getSteppayConfig();
$webhookSecret = $cfg['webhook_secret'] ?? '';
if ($webhookSecret !== '') {
    $headerSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_STEPPAY_SECRET'] ?? '';
    if (!hash_equals($webhookSecret, $headerSecret)) {
        payment_log('REJECT: 웹훅 시크릿 불일치', ['remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

payment_log('webhook 수신', ['eventType' => $payload['eventType'] ?? 'unknown', 'raw_length' => strlen($rawBody)]);

$eventType = $payload['eventType'] ?? '';
$data = $payload['data'] ?? [];

// 역검증: payment.completed 이벤트는 StepPay API로 주문 조회하여 실제 결제 여부 확인 (재시도 포함)
if ($eventType === 'payment.completed') {
    $orderCode = $data['orderCode'] ?? null;
    if ($orderCode) {
        $verified = false;
        for ($vAttempt = 0; $vAttempt < 3; $vAttempt++) {
            $verifyResult = steppayGetOrder($orderCode);
            if ($verifyResult['success'] && !empty($verifyResult['data']['paymentDate'])) {
                $verified = true;
                break;
            }
            if ($vAttempt < 2) sleep(2);
        }
        if (!$verified) {
            payment_log("REJECT: 역검증 실패 (3회 시도)", ['orderCode' => $orderCode]);
            echo json_encode(['success' => false, 'message' => 'Verification failed']);
            exit;
        }
    }
}

$pdo = getDb();

try {
    switch ($eventType) {
        case 'subscription.created':
        case 'subscription.updated':
            handleSubscriptionUpdate($pdo, $data);
            break;

        case 'payment.completed':
            handlePaymentCompleted($pdo, $data);
            break;

        case 'payment.failed':
        case 'payment.canceled':
            handlePaymentFailed($pdo, $data);
            break;

        default:
            break;
    }
} catch (Throwable $e) {
    payment_log('webhook 처리 에러', ['error' => $e->getMessage(), 'eventType' => $eventType]);
}

echo json_encode(['success' => true]);

// ─── 핸들러 함수 ───

function handleSubscriptionUpdate(PDO $pdo, array $data): void {
    $subscriptionId = $data['id'] ?? null;
    $status = $data['status'] ?? '';
    if (!$subscriptionId) return;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE steppay_subscription_id = ? LIMIT 1");
    $stmt->execute([$subscriptionId]);
    $user = $stmt->fetch();
    if (!$user) return;

    $isActive = in_array($status, ['ACTIVE', 'PENDING_PAUSE', 'PENDING_CANCEL']);
    $expiresAt = $data['currentPeriodEnd'] ?? $data['endDate'] ?? null;

    $pdo->prepare("UPDATE users SET is_subscribed = ?, subscription_expires_at = ? WHERE id = ?")
        ->execute([$isActive ? 1 : 0, $expiresAt, $user['id']]);
}

function handlePaymentCompleted(PDO $pdo, array $data): void {
    $orderCode = $data['orderCode'] ?? null;
    if (!$orderCode) return;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE steppay_order_code = ? LIMIT 1");
    $stmt->execute([$orderCode]);
    $user = $stmt->fetch();

    // 폴백: order_code가 덮어씌워져 매칭 실패 시, steppay_customer_id로 재시도
    if (!$user) {
        $customerId = $data['customerId'] ?? $data['customer_id'] ?? null;
        if ($customerId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE steppay_customer_id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            $user = $stmt->fetch();
            if ($user) {
                payment_log("FALLBACK: customer_id 매칭 성공", ['customerId' => $customerId, 'userId' => $user['id']]);
                $pdo->prepare("UPDATE users SET steppay_order_code = ? WHERE id = ?")->execute([$orderCode, $user['id']]);
            }
        }
    }

    if (!$user) {
        payment_log("WARN: 매칭 실패", ['orderCode' => $orderCode]);
        return;
    }

    $orderFetch = steppayGetOrder($orderCode);
    if (!$orderFetch['success'] || empty($orderFetch['data']['paymentDate'])) {
        payment_log('WARN: payment.completed 처리 중 주문 재조회 실패', ['orderCode' => $orderCode, 'userId' => $user['id']]);
        return;
    }

    applyVerifiedOrderToUserDb($pdo, (int) $user['id'], (string) $orderCode, $orderFetch['data']);
    payment_log('payment.completed 구독 DB 반영', ['orderCode' => $orderCode, 'userId' => $user['id']]);

    try {
        promotionMarkUsageCompleted($pdo, (int) $user['id'], (string) $orderCode);
    } catch (Throwable $e) {
        payment_log('webhook promotionMarkUsageCompleted 실패', ['error' => $e->getMessage()]);
    }
}

function handlePaymentFailed(PDO $pdo, array $data): void {
    $orderCode = $data['orderCode'] ?? null;
    if (!$orderCode) return;

    $stmt = $pdo->prepare("SELECT id, subscription_expires_at FROM users WHERE steppay_order_code = ? LIMIT 1");
    $stmt->execute([$orderCode]);
    $user = $stmt->fetch();

    if (!$user) {
        $customerId = $data['customerId'] ?? $data['customer_id'] ?? null;
        if ($customerId) {
            $stmt = $pdo->prepare("SELECT id, subscription_expires_at FROM users WHERE steppay_customer_id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            $user = $stmt->fetch();
        }
    }

    if (!$user) return;

    $errorMessage = $data['errorMessage'] ?? null;
    payment_log('payment.failed errorMessage', ['userId' => $user['id'], 'errorMessage' => $errorMessage, 'orderCode' => $orderCode]);

    $expiresAt = $user['subscription_expires_at'] ?? null;
    $periodStillActive = $expiresAt !== null && $expiresAt !== '' && strtotime((string) $expiresAt) > time();

    if ($periodStillActive) {
        $pdo->prepare("UPDATE users SET last_payment_error = ?, last_payment_error_at = NOW() WHERE id = ?")
            ->execute([$errorMessage, $user['id']]);
    } else {
        $pdo->prepare("UPDATE users SET is_subscribed = 0, last_payment_error = ?, last_payment_error_at = NOW() WHERE id = ?")
            ->execute([$errorMessage, $user['id']]);
    }
}
