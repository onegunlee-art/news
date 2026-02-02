<?php
/**
 * 뉴스 상세 조회 API
 * GET: /api/news/detail.php?id=123
 * 
 * why_important 필드를 포함한 뉴스 상세 정보 반환
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// GET 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 뉴스 ID 확인
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
    exit;
}

// 데이터베이스 설정
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'romi4120!',
    'charset' => 'utf8mb4'
];

try {
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // why_important 컬럼 존재 여부 확인
    $hasWhyImportant = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'why_important'");
        $hasWhyImportant = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // source_url 컬럼 존재 여부 확인
    $hasSourceUrl = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'source_url'");
        $hasSourceUrl = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // 기본 컬럼
    $columns = 'id, category, title, description, content, source, image_url, created_at';
    
    // why_important 추가
    if ($hasWhyImportant) {
        $columns = 'id, category, title, description, content, why_important, source, image_url, created_at';
    }
    
    // source_url 추가
    if ($hasSourceUrl) {
        $columns .= ', source_url';
    }
    
    // 뉴스 조회
    $stmt = $db->prepare("SELECT $columns FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $news = $stmt->fetch();
    
    if (!$news) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
        exit;
    }
    
    // 시간 계산
    $createdAt = new DateTime($news['created_at']);
    $now = new DateTime();
    $diff = $now->diff($createdAt);
    
    if ($diff->days > 0) {
        $timeAgo = $diff->days . '일 전';
    } elseif ($diff->h > 0) {
        $timeAgo = $diff->h . '시간 전';
    } elseif ($diff->i > 0) {
        $timeAgo = $diff->i . '분 전';
    } else {
        $timeAgo = '방금 전';
    }
    
    // 응답 데이터 구성
    $responseData = [
        'id' => (int)$news['id'],
        'title' => $news['title'],
        'description' => $news['description'],
        'content' => $news['content'],
        'why_important' => $hasWhyImportant ? ($news['why_important'] ?? null) : null,
        'source' => $news['source'],
        'url' => $hasSourceUrl ? ($news['source_url'] ?? '') : '',
        'image_url' => $news['image_url'],
        'published_at' => $news['created_at'],
        'time_ago' => $timeAgo,
        'is_bookmarked' => false, // 추후 로그인 사용자용 구현
    ];
    
    echo json_encode([
        'success' => true,
        'message' => '뉴스 조회 성공',
        'data' => $responseData
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '데이터베이스 오류: ' . $e->getMessage()]);
}
