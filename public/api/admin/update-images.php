<?php
/**
 * 뉴스 이미지 자동 매칭 API
 * 저작권 무료 이미지 - 고정 Unsplash URL 사용 (API 불필요)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 간단한 PDO 연결
$host = 'localhost';
$dbname = 'ailand';
$username = 'ailand';
$password = 'romi4120!';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']);
    exit;
}

// 이미지 매핑 통합 파일 + API 검색 로직 로드
require_once __DIR__ . '/../lib/imageSearch.php';

function extractMatchedKeyword($title, $imageMap) {
    $titleLower = mb_strtolower($title);
    $foundKeywords = [];
    
    foreach ($imageMap as $keyword => $urls) {
        if (mb_strpos($titleLower, mb_strtolower($keyword)) !== false) {
            $foundKeywords[] = $keyword;
        }
    }
    
    return $foundKeywords;
}

// API 동작
$action = $_GET['action'] ?? $_POST['action'] ?? 'update_all';

if ($action === 'update_all') {
    // 모든 뉴스 이미지 업데이트
    $stmt = $pdo->query("SELECT id, title, category, image_url FROM news ORDER BY id DESC");
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = [];
    
    foreach ($newsList as $news) {
        $newImageUrl = smartImageUrl($news['title'], $news['category'] ?? '', $pdo);
        
        $updateStmt = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
        $updateStmt->execute([$newImageUrl, $news['id']]);
        
        $matchedKeywords = extractMatchedKeyword($news['title'], $imageMap);
        
        $updated[] = [
            'id' => $news['id'],
            'title' => substr($news['title'], 0, 50) . '...',
            'matched_keywords' => $matchedKeywords,
            'new_image' => $newImageUrl
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '모든 뉴스 이미지가 업데이트되었습니다.',
        'total' => count($newsList),
        'updated' => $updated
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} elseif ($action === 'update_one') {
    // 특정 뉴스 이미지 업데이트
    $newsId = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$newsId) {
        echo json_encode(['success' => false, 'error' => 'News ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, title, category FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$news) {
        echo json_encode(['success' => false, 'error' => 'News not found']);
        exit;
    }
    
    $newImageUrl = smartImageUrl($news['title'], $news['category'] ?? '', $pdo);
    
    $updateStmt = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
    $updateStmt->execute([$newImageUrl, $newsId]);
    
    $matchedKeywords = extractMatchedKeyword($news['title'], $imageMap);
    
    echo json_encode([
        'success' => true,
        'id' => $newsId,
        'title' => $news['title'],
        'matched_keywords' => $matchedKeywords,
        'new_image' => $newImageUrl
    ], JSON_UNESCAPED_UNICODE);
    
} elseif ($action === 'preview') {
    // 이미지 미리보기 (업데이트 없이 URL만 생성)
    $title = $_GET['title'] ?? $_POST['title'] ?? '';
    $category = $_GET['category'] ?? $_POST['category'] ?? '';
    
    if (!$title) {
        echo json_encode(['success' => false, 'error' => 'Title required']);
        exit;
    }
    
    $matchedKeywords = extractMatchedKeyword($title, $imageMap);
    $imageUrl = smartImageUrl($title, $category, $pdo);
    
    echo json_encode([
        'success' => true,
        'title' => $title,
        'category' => $category,
        'matched_keywords' => $matchedKeywords,
        'image_url' => $imageUrl
    ], JSON_UNESCAPED_UNICODE);
}
