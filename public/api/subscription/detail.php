<?php
/**
 * GET /api/subscription/detail
 * 현재 사용자의 StepPay 구독 상세 조회 (플랜명, 상태, 기간, 금액, 자동갱신 여부)
 */

require_once __DIR__ . '/../lib/cors.php';
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$stmt = $pdo->prepare("SELECT is_subscribed, steppay_subscription_id, subscription_plan, subscription_start_date, subscription_expires_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$subscriptionId = $user['steppay_subscription_id'] ?? null;
if (!$user['is_subscribed']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '구독 정보가 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dbPlan = $user['subscription_plan'] ?? null;
$dbStartDate = $user['subscription_start_date'] ?? null;
$dbExpiresAt = $user['subscription_expires_at'] ?? null;

$planLabels = [
    '1m'  => 'the gist 1개월 구독권',
    '3m'  => 'the gist 3개월 구독권',
    '6m'  => 'the gist 6개월 구독권',
    '12m' => 'the gist 12개월 구독권',
];

$planName = $dbPlan ? ($planLabels[$dbPlan] ?? null) : null;
$status = 'ACTIVE';
$startDate = $dbStartDate;
$nextPaymentDate = $dbExpiresAt;
$amountFormatted = '';
$autoRenew = true;

if (!empty($subscriptionId)) {
    $subscriptionId = (int) $subscriptionId;
    $result = steppayGetSubscription($subscriptionId);
    if ($result['success']) {
        $data = $result['data'];
        $status = $data['status'] ?? 'UNKNOWN';
        $currentPeriod = $data['currentPeriod'] ?? $data['current_period'] ?? [];
        $items = $data['items'] ?? [];
        $firstItem = $items[0] ?? [];
        if (!$planName) $planName = $firstItem['productName'] ?? $firstItem['product_name'] ?? null;
        $price = $firstItem['price'] ?? 0;
        $amountFormatted = '₩' . number_format((float) $price);
        if (!$startDate) $startDate = $currentPeriod['startDateTime'] ?? $currentPeriod['start_date_time'] ?? $data['createdAt'] ?? $data['created_at'] ?? null;
        if (!$nextPaymentDate) $nextPaymentDate = $data['nextPaymentDate'] ?? $data['next_payment_date'] ?? $currentPeriod['endDateTime'] ?? $currentPeriod['end_date_time'] ?? $data['endDate'] ?? $data['end_date'] ?? null;
        $autoRenew = !in_array($status, ['CANCELED', 'EXPIRED', 'PENDING_CANCEL'], true);
    }
}

if (!$planName) {
    $planName = 'the gist 구독권';
}

$statusLabels = [
    'ACTIVE' => '활성화',
    'PAUSED' => '일시정지',
    'PAUSE' => '일시정지',
    'CANCELED' => '취소됨',
    'EXPIRED' => '만료',
    'PENDING_CANCEL' => '취소 예정',
    'PENDING_PAUSE' => '일시정지 예정',
    'PAYMENT_FAILED' => '결제 실패',
    'UNPAID' => '결제 실패',
];
$statusLabel = $statusLabels[$status] ?? $status;

echo json_encode([
    'success' => true,
    'data' => [
        'plan_name' => $planName,
        'status' => $status,
        'status_label' => $statusLabel,
        'start_date' => $startDate,
        'next_payment_date' => $nextPaymentDate,
        'amount_formatted' => $amountFormatted,
        'auto_renew' => $autoRenew,
        'subscription_id' => $subscriptionId,
    ],
], JSON_UNESCAPED_UNICODE);
