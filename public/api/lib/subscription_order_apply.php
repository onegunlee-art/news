<?php
/**
 * StepPay 주문이 결제 완료로 검증된 뒤 users 테이블 구독 필드를 verify.php와 동일 규칙으로 갱신한다.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param string $orderCode
 * @param array $order steppayGetOrder() 응답의 data
 */
function applyVerifiedOrderToUserDb(PDO $pdo, int $userId, string $orderCode, array $order): void
{
    require_once __DIR__ . '/steppay.php';

    $subscriptions = $order['subscriptions'] ?? [];
    $subscriptionId = null;
    $expiresAt = null;

    if (!empty($subscriptions)) {
        $sub = $subscriptions[0];
        $subscriptionId = $sub['id'] ?? null;
        $expiresAt = $sub['endDate'] ?? $sub['currentPeriodEnd'] ?? null;
    }

    $cfg = getSteppayConfig();
    $plans = $cfg['plans'] ?? [];
    $matchedPlanId = null;
    $months = 1;
    $itemPriceCode = $order['items'][0]['price']['code'] ?? '';

    foreach ($plans as $pid => $pl) {
        if (($pl['price_code'] ?? '') === $itemPriceCode) {
            $matchedPlanId = $pid;
            $months = (int) ($pl['months'] ?? 1);
            break;
        }
    }

    $pendStmt = $pdo->prepare('SELECT pending_checkout_plan_id FROM users WHERE id = ? LIMIT 1');
    $pendStmt->execute([$userId]);
    $pendRow = $pendStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $pendingPlanId = $pendRow['pending_checkout_plan_id'] ?? null;

    if (!$matchedPlanId && $pendingPlanId && isset($plans[$pendingPlanId])) {
        $matchedPlanId = $pendingPlanId;
        $months = (int) ($plans[$pendingPlanId]['months'] ?? 1);
    }

    if (!$expiresAt) {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$months} months"));
    }

    $startDate = date('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE users SET is_subscribed = 1, subscription_expires_at = ?, steppay_subscription_id = ?, '
        . 'steppay_order_code = ?, subscription_plan = ?, subscription_start_date = ?, '
        . 'last_payment_error = NULL, last_payment_error_at = NULL, pending_checkout_plan_id = NULL, pending_promotion_code_id = NULL '
        . 'WHERE id = ?'
    )->execute([$expiresAt, $subscriptionId, $orderCode, $matchedPlanId, $startDate, $userId]);
}
