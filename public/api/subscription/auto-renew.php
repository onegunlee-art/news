<?php
/**
 * POST /api/subscription/auto-renew
 * Body: { "enabled": true | false }
 * enabled=false → StepPay 구독 취소(END_OF_PERIOD). enabled=true → StepPay 구독 활성화(재개).
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

$pdo = getDb();
$userId = getAuthUserId($pdo);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$enabled = isset($input['enabled']) ? (bool) $input['enabled'] : null;

if ($enabled === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'enabled 값이 필요합니다.'], JSON_UNESCAPED_UNICODE);
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

if ($enabled) {
    $result = steppayResumeSubscription($subscriptionId);
} else {
    $result = steppayCancelSubscription($subscriptionId, 'END_OF_PERIOD');
}

if (!$result['success']) {
    $message = $result['data']['errorMessage'] ?? $result['data']['message'] ?? $result['error'] ?? '처리에 실패했습니다.';
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// StepPay 구독 상태를 조회하여 DB에 즉시 반영 (웹훅에만 의존하지 않음)
$subResult = steppayGetSubscription($subscriptionId);
if ($subResult['success'] && !empty($subResult['data'])) {
    $spData = $subResult['data'];
    $spStatus = $spData['status'] ?? '';
    $spActive = in_array($spStatus, ['ACTIVE', 'PENDING_PAUSE', 'PENDING_CANCEL'], true);
    $spExpiresAt = $spData['currentPeriodEnd'] ?? $spData['endDate'] ?? null;

    $pdo->prepare("UPDATE users SET is_subscribed = ?, subscription_expires_at = ? WHERE id = ?")
        ->execute([$spActive ? 1 : 0, $spExpiresAt, $userId]);
}

echo json_encode(['success' => true, 'message' => $enabled ? '자동 갱신이 켜졌습니다.' : '다음 결제일 이후 취소 예정입니다.'], JSON_UNESCAPED_UNICODE);
