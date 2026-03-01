<?php
/**
 * Web Push 구독 API
 * GET  ?vapid=1  → VAPID 공개키 반환
 * POST           → 구독 저장 (body: { endpoint, keys: { p256dh, auth } })
 * DELETE         → 구독 해제 (body: { endpoint })
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: VAPID 공개키 (인증 불필요)
if ($method === 'GET' && isset($_GET['vapid']) && $_GET['vapid'] === '1') {
    $projectRoot = dirname(__DIR__, 3); // public/api/user -> project root
    $vapidPath = $projectRoot . '/config/vapid.php';
    if (!file_exists($vapidPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Push 알림이 아직 설정되지 않았습니다.']);
        exit;
    }
    $vapid = require $vapidPath;
    if (empty($vapid['publicKey'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'VAPID 공개키가 설정되지 않았습니다.']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => ['vapidPublicKey' => $vapid['publicKey']]]);
    exit;
}

// POST, DELETE: 인증 필요
try {
    $pdo = getDb();
    $userId = getAuthUserId($pdo);
    if ($userId === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $checkTable = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($checkTable->rowCount() === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'push_subscriptions 테이블이 없습니다. 마이그레이션을 실행하세요.']);
        exit;
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || empty($input['endpoint']) || empty($input['keys']['p256dh']) || empty($input['keys']['auth'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'endpoint, keys.p256dh, keys.auth가 필요합니다.']);
            exit;
        }
        $endpoint = trim($input['endpoint']);
        $p256dh = trim($input['keys']['p256dh']);
        $auth = trim($input['keys']['auth']);
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), updated_at = NOW()
        ");
        $stmt->execute([$userId, $endpoint, $p256dh, $auth, $userAgent]);
        echo json_encode(['success' => true, 'message' => '푸시 알림이 활성화되었습니다.']);
        exit;
    }

    if ($method === 'DELETE') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || empty($input['endpoint'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'endpoint가 필요합니다.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
        $stmt->execute([$userId, trim($input['endpoint'])]);
        echo json_encode(['success' => true, 'message' => '푸시 알림이 비활성화되었습니다.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}
