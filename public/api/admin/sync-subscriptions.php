<?php
/**
 * GET /api/admin/sync-subscriptions
 * 일회용: steppay_order_code가 있는 모든 사용자의 구독 상태를 StepPay API 실제 값으로 동기화.
 * 웹훅 실패로 DB에 반영되지 않은 결제/구독을 복구.
 * 실행 후 이 파일을 삭제하세요.
 */

require_once __DIR__ . '/../lib/cors.php';
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';
require_once __DIR__ . '/../lib/subscription_order_apply.php';
require_once __DIR__ . '/../lib/log.php';

$pdo = getDb();

$authenticated = false;

$userId = getAuthUserId($pdo);
if ($userId) {
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$userId]);
    $role = ($roleStmt->fetch())['role'] ?? '';
    if ($role === 'admin') $authenticated = true;
}

if (!$authenticated) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin only'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->query(
    "SELECT id, nickname, email, is_subscribed, steppay_order_code, steppay_subscription_id, steppay_customer_id, subscription_expires_at
     FROM users WHERE steppay_order_code IS NOT NULL AND steppay_order_code != '' ORDER BY id"
);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];

foreach ($users as $u) {
    $uid = (int) $u['id'];
    $orderCode = $u['steppay_order_code'];
    $entry = [
        'userId' => $uid,
        'nickname' => $u['nickname'],
        'orderCode' => $orderCode,
        'before' => [
            'is_subscribed' => (bool) $u['is_subscribed'],
            'subscription_expires_at' => $u['subscription_expires_at'],
            'steppay_subscription_id' => $u['steppay_subscription_id'],
        ],
        'action' => 'none',
    ];

    $orderResult = steppayGetOrder($orderCode);
    if (!$orderResult['success']) {
        $entry['action'] = 'api_error';
        $entry['error'] = $orderResult['error'] ?? 'HTTP ' . ($orderResult['http_code'] ?? '?');
        $results[] = $entry;
        continue;
    }

    $order = $orderResult['data'];
    $paymentDate = $order['paymentDate'] ?? null;

    if (empty($paymentDate)) {
        $itemStatus = $order['items'][0]['status'] ?? '';
        $entry['action'] = 'unpaid';
        $entry['itemStatus'] = $itemStatus;
        $results[] = $entry;
        continue;
    }

    applyVerifiedOrderToUserDb($pdo, $uid, $orderCode, $order);

    $after = $pdo->prepare("SELECT is_subscribed, subscription_expires_at, steppay_subscription_id, subscription_plan FROM users WHERE id = ?");
    $after->execute([$uid]);
    $snap = $after->fetch(PDO::FETCH_ASSOC);

    $entry['action'] = 'synced';
    $entry['after'] = [
        'is_subscribed' => (bool) $snap['is_subscribed'],
        'subscription_expires_at' => $snap['subscription_expires_at'],
        'steppay_subscription_id' => $snap['steppay_subscription_id'],
        'subscription_plan' => $snap['subscription_plan'],
    ];
    $results[] = $entry;
}

payment_log('admin sync-subscriptions 실행', ['count' => count($users), 'results' => $results], $userId);

echo json_encode([
    'success' => true,
    'total' => count($users),
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
