<?php
/**
 * 임시 사용자 구독 상태 조회 (원인 조사 후 삭제 필수)
 * 보안: 웹훅 시크릿으로 인증
 */
header('Content-Type: application/json; charset=utf-8');

$secret = $_GET['key'] ?? '';
if ($secret !== 'ws_nzwk99oqjj121exh') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';
$pdo = getDb();

$stmt = $pdo->prepare("
    SELECT id, nickname, email, is_subscribed, subscription_expires_at,
           steppay_customer_id, steppay_subscription_id, steppay_order_code,
           subscription_plan, subscription_start_date,
           pending_checkout_plan_id, pending_promotion_code_id,
           last_payment_error, last_payment_error_at, created_at
    FROM users WHERE id IN (29, 33)
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $users], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
