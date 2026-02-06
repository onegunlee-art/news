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

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/log.php';

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
    
    // narration 컬럼 존재 여부 확인
    $hasNarration = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'narration'");
        $hasNarration = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // source_url 컬럼 존재 여부 확인
    $hasSourceUrl = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'source_url'");
        $hasSourceUrl = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // published_at 컬럼 존재 여부 확인 (기사 등록일 = URL에서 추출한 날짜)
    $hasPublishedAt = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'published_at'");
        $hasPublishedAt = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // original_source 컬럼 존재 여부 확인 (원본 출처, 예: Foreign Affairs)
    $hasOriginalSource = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'original_source'");
        $hasOriginalSource = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // 기본 컬럼
    $columns = 'id, category, title, description, content, source, image_url, created_at';
    
    // why_important 추가
    if ($hasWhyImportant) {
        $columns = 'id, category, title, description, content, why_important, source, image_url, created_at';
    }
    
    // narration 추가
    if ($hasNarration) {
        $columns = str_replace('why_important,', 'why_important, narration,', $columns);
        if (!$hasWhyImportant) {
            $columns = str_replace('content,', 'content, narration,', $columns);
        }
    }
    
    // source_url 추가
    if ($hasSourceUrl) {
        $columns .= ', source_url';
    }
    
    // published_at 추가 (기사 등록일)
    if ($hasPublishedAt) {
        $columns .= ', published_at';
    }
    if ($hasOriginalSource) {
        $columns .= ', original_source';
    }
    
    // 뉴스 조회
    $stmt = $db->prepare("SELECT $columns FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $news = $stmt->fetch();
    
    if (!$news) {
        api_log('news/detail', 'GET', 404);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
        exit;
    }
    
    // 표시용 날짜: URL/매체에서 올린 원문 게재일(published_at) 우선, 없으면 우리 포스팅일(created_at)
    $dateForDisplay = $news['created_at'];
    if ($hasPublishedAt && !empty($news['published_at'])) {
        $dateForDisplay = $news['published_at'];
    }
    
    // time_ago 계산 (표시용 날짜 기준)
    $refDate = new DateTime($dateForDisplay);
    $now = new DateTime();
    $diff = $now->diff($refDate);
    
    if ($diff->days > 0) {
        $timeAgo = $diff->days . '일 전';
    } elseif ($diff->h > 0) {
        $timeAgo = $diff->h . '시간 전';
    } elseif ($diff->i > 0) {
        $timeAgo = $diff->i . '분 전';
    } else {
        $timeAgo = '방금 전';
    }
    
    $isBookmarked = false;
    try {
        $authUserId = getAuthUserId($db);
        if ($authUserId !== null) {
            $chk = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND news_id = ?");
            $chk->execute([$authUserId, $news['id']]);
            $isBookmarked = (bool) $chk->fetch();
        }
    } catch (Exception $e) { /* bookmarks 테이블 없을 수 있음 */ }

    // 응답 데이터 구성 (published_at = 포스팅 날짜로 표시)
    $responseData = [
        'id' => (int)$news['id'],
        'title' => $news['title'],
        'description' => $news['description'],
        'content' => $news['content'],
        'why_important' => $hasWhyImportant ? ($news['why_important'] ?? null) : null,
        'narration' => $hasNarration ? ($news['narration'] ?? null) : null,
        'source' => $news['source'],
        'original_source' => $hasOriginalSource ? ($news['original_source'] ?? null) : null,
        'url' => $hasSourceUrl ? ($news['source_url'] ?? '') : '',
        'image_url' => $news['image_url'],
        'published_at' => $dateForDisplay,
        'created_at' => $news['created_at'],
        'time_ago' => $timeAgo,
        'is_bookmarked' => $isBookmarked,
    ];
    api_log('news/detail', 'GET', 200);
    echo json_encode([
        'success' => true,
        'message' => '뉴스 조회 성공',
        'data' => $responseData
    ]);
    
} catch (PDOException $e) {
    api_log('news/detail', 'GET', 500, $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '데이터베이스 오류: ' . $e->getMessage()]);
}
