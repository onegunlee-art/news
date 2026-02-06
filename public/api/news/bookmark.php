<?php
/**
 * 즐겨찾기 API
 * POST: 추가, DELETE: 제거 (로그인 필요, bookmarks 테이블 연동)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/../lib/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST' && $method !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDb();
    $userId = getAuthUserId($pdo);
    if ($userId === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $newsId = isset($input['id']) ? (int) $input['id'] : 0;
        $memo = isset($input['memo']) ? trim((string) $input['memo']) : null;
        if ($newsId < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM news WHERE id = ?");
        $stmt->execute([$newsId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '해당 뉴스를 찾을 수 없습니다.']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO bookmarks (user_id, news_id, memo) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $newsId, $memo]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => true, 'message' => '이미 즐겨찾기에 있습니다.']);
        } else {
            echo json_encode(['success' => true, 'message' => '즐겨찾기에 추가되었습니다.']);
        }
        exit;
    }

    if ($method === 'DELETE') {
        $newsId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($newsId < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE user_id = ? AND news_id = ?");
        $stmt->execute([$userId, $newsId]);
        echo json_encode(['success' => true, 'message' => '즐겨찾기가 해제되었습니다.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}
