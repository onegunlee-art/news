<?php
/**
 * GET /api/subscription/settings
 * 구독 관리 페이지용 공지사항 등 공개 설정 조회 (인증 불필요)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';

$pdo = getDb();
$notice = '';
try {
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'subscription_manage_notice' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row && isset($row['value'])) {
        $notice = (string) $row['value'];
    }
} catch (Throwable $e) {
    // ignore
}

echo json_encode([
    'success' => true,
    'data' => [
        'notice' => $notice,
    ],
], JSON_UNESCAPED_UNICODE);
