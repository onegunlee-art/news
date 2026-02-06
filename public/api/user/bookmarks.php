<?php
/**
 * 사용자 즐겨찾기 목록 API
 * GET: 로그인 사용자의 북마크 목록 (bookmarks + news 조인)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/../lib/auth.php';

try {
    $pdo = getDb();
    $userId = getAuthUserId($pdo);
    if ($userId === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT b.id AS bookmark_id, b.news_id, b.memo, b.created_at,
            n.title, n.description, n.content, n.source, n.image_url, n.published_at
            FROM bookmarks b
            INNER JOIN news n ON n.id = b.news_id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int) $r['news_id'],
            'bookmark_id' => (int) $r['bookmark_id'],
            'title' => $r['title'],
            'description' => $r['description'],
            'content' => $r['content'],
            'source' => $r['source'],
            'image_url' => $r['image_url'],
            'published_at' => $r['published_at'],
            'memo' => $r['memo'],
            'created_at' => $r['created_at'],
            'bookmarked_at' => $r['created_at'],
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => '즐겨찾기 목록',
        'data' => [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}
