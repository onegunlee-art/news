<?php
/**
 * POST /api/subscription/cancel
 * StepPay 구독 즉시 취소 (whenToCancel = NOW)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$stmt = $pdo->prepare("SELECT steppay_subscription_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$subscriptionId = $user['steppay_subscription_id'] ?? null;
if (empty($subscriptionId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '구독 정보가 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subscriptionId = (int) $subscriptionId;
$result = steppayCancelSubscription($subscriptionId, 'NOW');

if (!$result['success']) {
    $message = $result['data']['errorMessage'] ?? $result['data']['message'] ?? $result['error'] ?? '구독 취소에 실패했습니다.';
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => '구독이 취소되었습니다.'], JSON_UNESCAPED_UNICODE);
