<?php
/**
 * POST /api/subscription/verify-promo
 * 프로모션 코드 + 플랜 조합 검증 및 할인 금액 표시 (로그인 불필요)
 *
 * Body: { "code": "OPEN50", "planId": "1m" }
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
require_once __DIR__ . '/../lib/promotion_codes.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$code = $input['code'] ?? '';
$planId = $input['planId'] ?? '';

$pdo = getDb();
$cfg = getSteppayConfig();
$result = promotionValidateForPlan($pdo, (string) $code, (string) $planId, $cfg);

if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $result['message'] ?? '유효하지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = $result['promotion'];
echo json_encode([
    'success' => true,
    'data' => [
        'code' => $row['code'],
        'description' => $row['description'],
        'discount_percent' => $result['discount_percent'],
        'original_amount' => $result['original_amount'],
        'discounted_amount' => $result['discounted_amount'],
        'plan_label' => $result['plan_label'],
        'plan_id' => $planId,
    ],
], JSON_UNESCAPED_UNICODE);
