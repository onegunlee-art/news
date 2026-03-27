<?php
/**
 * 구독 결제 프로모션 코드 — StepPay 할인 price_code 매핑
 */

/**
 * @return array{ok:bool, message?:string, promotion?:array, price_code?:string, product_code?:string,
 *               original_amount?:int, discounted_amount?:int, discount_percent?:int, plan_label?:string}
 */
function promotionValidateForPlan(PDO $pdo, string $rawCode, string $planId, array $steppayCfg): array {
    $code = strtoupper(trim($rawCode));
    if ($code === '') {
        return ['ok' => false, 'message' => '프로모션 코드를 입력해 주세요.'];
    }

    $plans = $steppayCfg['plans'] ?? [];
    if (!isset($plans[$planId])) {
        return ['ok' => false, 'message' => '유효하지 않은 플랜입니다.'];
    }

    $base = $plans[$planId];
    $defaultProductCode = $steppayCfg['product_code'] ?? '';
    $originalAmount = (int) ($base['amount'] ?? 0);

    $stmt = $pdo->prepare(
        'SELECT id, code, description, discount_percent, plan_price_map, max_uses, used_count, starts_at, expires_at, is_active
         FROM promotion_codes WHERE UPPER(code) = ? LIMIT 1'
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => '존재하지 않는 프로모션 코드입니다.'];
    }
    if (!(int) $row['is_active']) {
        return ['ok' => false, 'message' => '비활성화된 프로모션 코드입니다.'];
    }

    $now = time();
    if (!empty($row['starts_at']) && strtotime($row['starts_at']) > $now) {
        return ['ok' => false, 'message' => '아직 사용할 수 없는 코드입니다.'];
    }
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < $now) {
        return ['ok' => false, 'message' => '만료된 프로모션 코드입니다.'];
    }

    if ($row['max_uses'] !== null && (int) $row['used_count'] >= (int) $row['max_uses']) {
        return ['ok' => false, 'message' => '사용 한도에 도달한 프로모션 코드입니다.'];
    }

    $map = json_decode($row['plan_price_map'] ?? '{}', true);
    if (!is_array($map) || !isset($map[$planId]) || !is_array($map[$planId])) {
        return ['ok' => false, 'message' => '이 플랜에 적용할 수 없는 코드입니다.'];
    }
    $entry = $map[$planId];
    $priceCode = $entry['price_code'] ?? '';
    $discounted = isset($entry['amount']) ? (int) $entry['amount'] : 0;
    $productCode = !empty($entry['product_code']) ? (string) $entry['product_code'] : $defaultProductCode;

    if ($priceCode === '' || $discounted <= 0) {
        return ['ok' => false, 'message' => '프로모션 설정이 올바르지 않습니다. 관리자에게 문의하세요.'];
    }

    return [
        'ok' => true,
        'promotion' => $row,
        'price_code' => $priceCode,
        'product_code' => $productCode,
        'original_amount' => $originalAmount,
        'discounted_amount' => $discounted,
        'discount_percent' => (int) $row['discount_percent'],
        'plan_label' => (string) ($base['label'] ?? $planId),
    ];
}

/**
 * 주문 전: 동일 사용자·코드로 이미 결제 완료된 적 있으면 거부
 */
function promotionAssertUserCanUse(PDO $pdo, int $promotionId, int $userId): ?string {
    $stmt = $pdo->prepare(
        'SELECT completed FROM promotion_code_usage WHERE promotion_code_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$promotionId, $userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && (int) $u['completed'] === 1) {
        return '이미 이 프로모션으로 결제를 완료하셨습니다.';
    }
    return null;
}

/**
 * 주문 생성 후: usage 행 upsert (completed=0)
 */
function promotionUpsertPendingUsage(
    PDO $pdo,
    int $promotionId,
    int $userId,
    string $planId,
    int $originalAmount,
    int $discountedAmount,
    string $orderCode
): void {
    $stmt = $pdo->prepare(
        'SELECT id, completed FROM promotion_code_usage WHERE promotion_code_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$promotionId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ((int) $row['completed'] === 1) {
            throw new RuntimeException('promo_already_completed');
        }
        $pdo->prepare(
            'UPDATE promotion_code_usage SET plan_id = ?, original_amount = ?, discounted_amount = ?, order_code =?, completed = 0 WHERE id = ?'
        )->execute([$planId, $originalAmount, $discountedAmount, $orderCode, $row['id']]);
        return;
    }

    $pdo->prepare(
        'INSERT INTO promotion_code_usage (promotion_code_id, user_id, plan_id, original_amount, discounted_amount, order_code, completed)
         VALUES (?,?,?,?,?,?,0)'
    )->execute([$promotionId, $userId, $planId, $originalAmount, $discountedAmount, $orderCode]);
}

/**
 * 결제 검증 성공 시: usage 완료 + used_count 증가
 */
function promotionMarkUsageCompleted(PDO $pdo, int $userId, string $orderCode): void {
    $stmt = $pdo->prepare(
        'SELECT id, promotion_code_id, completed FROM promotion_code_usage WHERE user_id = ? AND order_code = ? LIMIT 1'
    );
    $stmt->execute([$userId, $orderCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['completed'] === 1) {
        return;
    }
    $pdo->prepare('UPDATE promotion_code_usage SET completed = 1 WHERE id = ?')->execute([$row['id']]);
    $pdo->prepare('UPDATE promotion_codes SET used_count = used_count + 1 WHERE id = ?')
        ->execute([(int) $row['promotion_code_id']]);
}
