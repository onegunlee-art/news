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

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$logDir = $_SERVER['DOCUMENT_ROOT'] . '/../storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
file_put_contents($logDir . '/steppay_webhook.log', date('[Y-m-d H:i:s] ') . $rawBody . "\n", FILE_APPEND);

$eventType = $payload['eventType'] ?? '';
$data = $payload['data'] ?? [];

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
    file_put_contents($logDir . '/steppay_webhook.log', date('[Y-m-d H:i:s] ERROR: ') . $e->getMessage() . "\n", FILE_APPEND);
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
    if (!$user) return;

    $pdo->prepare("UPDATE users SET is_subscribed = 1 WHERE id = ?")->execute([$user['id']]);
}

function handlePaymentFailed(PDO $pdo, array $data): void {
    $orderCode = $data['orderCode'] ?? null;
    if (!$orderCode) return;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE steppay_order_code = ? LIMIT 1");
    $stmt->execute([$orderCode]);
    $user = $stmt->fetch();
    if (!$user) return;

    $pdo->prepare("UPDATE users SET is_subscribed = 0 WHERE id = ?")->execute([$user['id']]);
}
