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

// 고정 이미지 URL 매핑 (Unsplash 직접 링크 - 저작권 무료)
// 형식: 키워드 => [이미지 URL 배열]
$imageMap = [
    // 인물/정치 - 트럼프 (백악관/미국 정치 이미지)
    'trump' => [
        'https://images.unsplash.com/photo-1585581934192-d2e99e5f0cc0?w=800&h=500&fit=crop', // 백악관
        'https://images.unsplash.com/photo-1540910419892-4a36d2c3266c?w=800&h=500&fit=crop', // 미국 국기
        'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800&h=500&fit=crop', // 워싱턴DC
    ],
    '트럼프' => [
        'https://images.unsplash.com/photo-1585581934192-d2e99e5f0cc0?w=800&h=500&fit=crop', // 백악관
        'https://images.unsplash.com/photo-1540910419892-4a36d2c3266c?w=800&h=500&fit=crop', // 미국 국기
    ],
    // 그린란드 (북극/얼음/빙하 이미지)
    'greenland' => [
        'https://images.unsplash.com/photo-1517783999520-f068f9e28a51?w=800&h=500&fit=crop', // 북극 빙하
        'https://images.unsplash.com/photo-1489549132488-d00b7eee80f1?w=800&h=500&fit=crop', // 빙산
    ],
    '그린란드' => [
        'https://images.unsplash.com/photo-1517783999520-f068f9e28a51?w=800&h=500&fit=crop', // 북극 빙하
        'https://images.unsplash.com/photo-1489549132488-d00b7eee80f1?w=800&h=500&fit=crop', // 빙산
    ],
    // 바이든
    'biden' => [
        'https://images.unsplash.com/photo-1604859628564-26f78352400d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1612831197310-ff5cf7a211b6?w=800&h=500&fit=crop',
    ],
    '바이든' => [
        'https://images.unsplash.com/photo-1604859628564-26f78352400d?w=800&h=500&fit=crop',
    ],
    // AI/OpenAI
    'openai' => [
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1684163362235-0d2e2b2c7c64?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1676573409967-986dcf64d35a?w=800&h=500&fit=crop',
    ],
    'ai' => [
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1555255707-c07966088b7b?w=800&h=500&fit=crop',
    ],
    '인공지능' => [
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop',
    ],
    'chatgpt' => [
        'https://images.unsplash.com/photo-1684163362235-0d2e2b2c7c64?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1676573409967-986dcf64d35a?w=800&h=500&fit=crop',
    ],
    // K-POP/엔터테인먼트
    'k-pop' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=800&h=500&fit=crop',
    ],
    'kpop' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
    ],
    '케이팝' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
    ],
    // 경제/금융
    '경제' => [
        'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=800&h=500&fit=crop',
    ],
    '주식' => [
        'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&h=500&fit=crop',
    ],
    '비트코인' => [
        'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1622630998477-20aa696ecb05?w=800&h=500&fit=crop',
    ],
    'bitcoin' => [
        'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1622630998477-20aa696ecb05?w=800&h=500&fit=crop',
    ],
    // 반도체/기술
    '반도체' => [
        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=800&h=500&fit=crop',
    ],
    'semiconductor' => [
        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop',
    ],
    // 외교
    '외교' => [
        'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1577415124269-fc1140815970?w=800&h=500&fit=crop',
    ],
    '한미' => [
        'https://images.unsplash.com/photo-1508433957232-3107f5fd5995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1569863959165-56dae551d4fc?w=800&h=500&fit=crop',
    ],
    // 구독자/축하
    '구독자' => [
        'https://images.unsplash.com/photo-1533227268428-f9ed0900fb3b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1504805572947-34fad45aed93?w=800&h=500&fit=crop',
    ],
    '축하' => [
        'https://images.unsplash.com/photo-1533227268428-f9ed0900fb3b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1513151233558-d860c5398176?w=800&h=500&fit=crop',
    ],
    // 테스트/기타
    '테스트' => [
        'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&h=500&fit=crop',
    ],
    // 일론 머스크/테슬라
    '머스크' => [
        'https://images.unsplash.com/photo-1620891499292-74ecc6905d3b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1560958089-b8a1929cea89?w=800&h=500&fit=crop',
    ],
    'tesla' => [
        'https://images.unsplash.com/photo-1620891499292-74ecc6905d3b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1560958089-b8a1929cea89?w=800&h=500&fit=crop',
    ],
    // 광고
    '광고' => [
        'https://images.unsplash.com/photo-1557804506-669a67965ba0?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&h=500&fit=crop',
    ],
];

// 카테고리별 기본 이미지
$categoryDefaults = [
    'diplomacy' => [
        'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1577415124269-fc1140815970?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=800&h=500&fit=crop',
    ],
    'economy' => [
        'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&h=500&fit=crop',
    ],
    'technology' => [
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
    ],
    'entertainment' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=800&h=500&fit=crop',
    ],
];

// 범용 뉴스 이미지
$defaultImages = [
    'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1495020689067-958852a7765e?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=800&h=500&fit=crop',
];

function extractMatchedKeyword($title, $imageMap) {
    $titleLower = strtolower($title);
    $foundKeywords = [];
    
    foreach ($imageMap as $keyword => $urls) {
        if (strpos($titleLower, strtolower($keyword)) !== false) {
            $foundKeywords[] = $keyword;
        }
    }
    
    return $foundKeywords;
}

function getImageUrl($title, $category, $imageMap, $categoryDefaults, $defaultImages) {
    $titleLower = strtolower($title);
    
    // 1. 제목에서 키워드 매칭 시도
    foreach ($imageMap as $keyword => $urls) {
        if (strpos($titleLower, strtolower($keyword)) !== false) {
            // 제목 해시 기반으로 일관된 이미지 선택
            $index = abs(crc32($title)) % count($urls);
            return $urls[$index];
        }
    }
    
    // 2. 카테고리 기반 이미지 선택
    $cat = strtolower($category ?? '');
    if (isset($categoryDefaults[$cat])) {
        $urls = $categoryDefaults[$cat];
        $index = abs(crc32($title)) % count($urls);
        return $urls[$index];
    }
    
    // 3. 기본 뉴스 이미지
    $index = abs(crc32($title)) % count($defaultImages);
    return $defaultImages[$index];
}

// API 동작
$action = $_GET['action'] ?? $_POST['action'] ?? 'update_all';

if ($action === 'update_all') {
    // 모든 뉴스 이미지 업데이트
    $stmt = $pdo->query("SELECT id, title, category, image_url FROM news ORDER BY id DESC");
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = [];
    
    foreach ($newsList as $news) {
        $newImageUrl = getImageUrl($news['title'], $news['category'] ?? '', $imageMap, $categoryDefaults, $defaultImages);
        
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
    
    $newImageUrl = getImageUrl($news['title'], $news['category'] ?? '', $imageMap, $categoryDefaults, $defaultImages);
    
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
    $imageUrl = getImageUrl($title, $category, $imageMap, $categoryDefaults, $defaultImages);
    
    echo json_encode([
        'success' => true,
        'title' => $title,
        'category' => $category,
        'matched_keywords' => $matchedKeywords,
        'image_url' => $imageUrl
    ], JSON_UNESCAPED_UNICODE);
}
