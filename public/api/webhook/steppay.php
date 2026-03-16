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

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$logDir = $_SERVER['DOCUMENT_ROOT'] . '/../storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/steppay_webhook.log';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $rawBody . "\n", FILE_APPEND);

$eventType = $payload['eventType'] ?? '';
$data = $payload['data'] ?? [];

// 역검증: payment.completed 이벤트는 StepPay API로 주문 조회하여 실제 결제 여부 확인
if ($eventType === 'payment.completed') {
    $orderCode = $data['orderCode'] ?? null;
    if ($orderCode) {
        $verifyResult = steppayGetOrder($orderCode);
        if (!$verifyResult['success'] || empty($verifyResult['data']['paymentDate'])) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "REJECT: 역검증 실패 orderCode={$orderCode}\n", FILE_APPEND);
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
            handleSubscriptionUpdate($pdo, $data, $logFile);
            break;

        case 'payment.completed':
            handlePaymentCompleted($pdo, $data, $logFile);
            break;

        case 'payment.failed':
        case 'payment.canceled':
            handlePaymentFailed($pdo, $data, $logFile);
            break;

        default:
            break;
    }
} catch (Throwable $e) {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ERROR: ') . $e->getMessage() . "\n", FILE_APPEND);
}

echo json_encode(['success' => true]);

// ─── 핸들러 함수 ───

function handleSubscriptionUpdate(PDO $pdo, array $data, string $logFile): void {
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

function handlePaymentCompleted(PDO $pdo, array $data, string $logFile): void {
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
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "FALLBACK: customer_id={$customerId}로 매칭 성공\n", FILE_APPEND);
                $pdo->prepare("UPDATE users SET steppay_order_code = ? WHERE id = ?")->execute([$orderCode, $user['id']]);
            }
        }
    }

    if (!$user) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "WARN: 매칭 실패 orderCode={$orderCode}\n", FILE_APPEND);
        return;
    }

    $pdo->prepare("UPDATE users SET is_subscribed = 1 WHERE id = ?")->execute([$user['id']]);
}

function handlePaymentFailed(PDO $pdo, array $data, string $logFile): void {
    $orderCode = $data['orderCode'] ?? null;
    if (!$orderCode) return;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE steppay_order_code = ? LIMIT 1");
    $stmt->execute([$orderCode]);
    $user = $stmt->fetch();

    if (!$user) {
        $customerId = $data['customerId'] ?? $data['customer_id'] ?? null;
        if ($customerId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE steppay_customer_id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            $user = $stmt->fetch();
        }
    }

    if (!$user) return;

    $pdo->prepare("UPDATE users SET is_subscribed = 0 WHERE id = ?")->execute([$user['id']]);
}
