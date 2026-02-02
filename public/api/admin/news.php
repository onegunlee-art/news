<?php
/**
 * Admin News API
 * GET: 뉴스 목록 조회
 * POST: 뉴스 저장
 * PUT: 뉴스 수정
 * DELETE: 뉴스 삭제
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 데이터베이스 설정
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'ektlf1212',
    'charset' => 'utf8mb4'
];

// 이미지 자동 매칭용 키워드 맵
$keywordMap = [
    '트럼프' => 'trump,president,politics', 'trump' => 'trump,president,politics',
    '바이든' => 'biden,president,whitehouse', 'biden' => 'biden,president,whitehouse',
    '시진핑' => 'china,politics,beijing', '푸틴' => 'russia,kremlin,politics',
    '윤석열' => 'korea,seoul,politics', '김정은' => 'northkorea,politics',
    '일론' => 'tesla,spacex,technology', '머스크' => 'tesla,spacex,technology',
    'openai' => 'artificial-intelligence,robot,technology',
    'ai' => 'artificial-intelligence,robot,technology',
    '인공지능' => 'artificial-intelligence,robot,future',
    '반도체' => 'semiconductor,chip,technology', '배터리' => 'battery,electric,energy',
    '전기차' => 'electric-car,tesla,automotive', '비트코인' => 'bitcoin,cryptocurrency',
    '주식' => 'stock-market,trading,finance', '경제' => 'economy,business,finance',
    '외교' => 'diplomacy,handshake,politics', '전쟁' => 'war,military,conflict',
    '그린란드' => 'greenland,arctic,ice', 'k-pop' => 'kpop,concert,music',
    '케이팝' => 'kpop,concert,music', 'kpop' => 'kpop,concert,music',
];

$categoryDefaults = [
    'diplomacy' => 'diplomacy,politics,globe,summit',
    'economy' => 'economy,business,finance,stock-market',
    'technology' => 'technology,innovation,future,digital',
    'entertainment' => 'entertainment,music,movie,celebrity',
];

// 이미지 URL 생성 함수
function generateImageUrl($title, $category, $keywordMap, $categoryDefaults) {
    $title = mb_strtolower($title);
    $foundKeywords = [];
    
    foreach ($keywordMap as $keyword => $searchTerms) {
        if (mb_strpos($title, mb_strtolower($keyword)) !== false) {
            $foundKeywords[] = $searchTerms;
            if (count($foundKeywords) >= 2) break;
        }
    }
    
    if (empty($foundKeywords)) {
        $category = strtolower($category ?? '');
        $keywords = $categoryDefaults[$category] ?? 'news,newspaper,global';
    } else {
        $allKw = [];
        foreach ($foundKeywords as $kw) {
            $allKw = array_merge($allKw, explode(',', $kw));
        }
        $keywords = implode(',', array_unique($allKw));
    }
    
    $seed = substr(md5($title . time()), 0, 8);
    return "https://source.unsplash.com/800x500/?{$keywords}&sig={$seed}";
}

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// POST: 뉴스 저장
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $category = $input['category'] ?? '';
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $sourceUrl = $input['source_url'] ?? null;
    
    // 유효성 검사
    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '제목과 내용을 입력해주세요.']);
        exit;
    }
    
    $validCategories = ['diplomacy', 'economy', 'technology', 'entertainment'];
    if (!in_array($category, $validCategories)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '유효하지 않은 카테고리입니다.']);
        exit;
    }
    
    try {
        // source_url이 있으면 그것을 사용, 없으면 admin:// URL 생성
        $url = $sourceUrl ? $sourceUrl : 'admin://news/' . uniqid() . '-' . time();
        $description = mb_substr(strip_tags($content), 0, 300);
        
        // source_url 컬럼 존재 여부 확인
        $hasSourceUrl = false;
        try {
            $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'source_url'");
            $hasSourceUrl = $checkCol->rowCount() > 0;
        } catch (Exception $e) {}
        
        // 자동 이미지 URL 생성 (저작권 무료 - Unsplash)
        $imageUrl = generateImageUrl($title, $category, $keywordMap, $categoryDefaults);
        
        if ($hasSourceUrl) {
            $stmt = $db->prepare("
                INSERT INTO news (category, title, description, content, source, url, source_url, image_url, created_at)
                VALUES (?, ?, ?, ?, 'Admin', ?, ?, ?, NOW())
            ");
            $stmt->execute([$category, $title, $description, $content, $url, $sourceUrl, $imageUrl]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO news (category, title, description, content, source, url, image_url, created_at)
                VALUES (?, ?, ?, ?, 'Admin', ?, ?, NOW())
            ");
            $stmt->execute([$category, $title, $description, $content, $url, $imageUrl]);
        }
        
        $newsId = $db->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => '뉴스가 저장되었습니다.',
            'data' => [
                'id' => (int)$newsId,
                'category' => $category,
                'title' => $title,
                'source_url' => $sourceUrl
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '뉴스 저장 실패: ' . $e->getMessage()]);
    }
    exit;
}

// GET: 뉴스 목록 조회 (검색 기능 포함)
if ($method === 'GET') {
    $category = $_GET['category'] ?? '';
    $query = $_GET['query'] ?? '';  // 검색어
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min((int)($_GET['per_page'] ?? 20), 100);
    $offset = ($page - 1) * $perPage;
    
    try {
        $conditions = [];
        $params = [];
        
        // 카테고리 필터
        if ($category) {
            $conditions[] = 'category = ?';
            $params[] = $category;
        }
        
        // 키워드 검색 (제목, 내용, 설명에서 검색)
        if ($query) {
            $searchTerm = '%' . $query . '%';
            $conditions[] = '(title LIKE ? OR content LIKE ? OR description LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // 전체 수
        $stmt = $db->prepare("SELECT COUNT(*) FROM news $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        
        // 뉴스 목록 (LIMIT과 OFFSET은 직접 쿼리에 삽입)
        // source_url 컬럼 존재 여부 확인
        $hasSourceUrl = false;
        try {
            $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'source_url'");
            $hasSourceUrl = $checkCol->rowCount() > 0;
        } catch (Exception $e) {}
        
        $selectColumns = $hasSourceUrl 
            ? 'id, category, title, description, content, source, source_url, created_at'
            : 'id, category, title, description, content, source, NULL as source_url, created_at';
        
        $stmt = $db->prepare("
            SELECT $selectColumns
            FROM news 
            $where
            ORDER BY created_at DESC 
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => $query ? '검색 결과' : '뉴스 목록 조회 성공',
            'data' => [
                'items' => $news,
                'query' => $query,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                ]
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '뉴스 조회 실패: ' . $e->getMessage()]);
    }
    exit;
}

// PUT: 뉴스 수정
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? 0;
    $category = $input['category'] ?? '';
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $sourceUrl = $input['source_url'] ?? null;
    
    // 유효성 검사
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
        exit;
    }
    
    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '제목과 내용을 입력해주세요.']);
        exit;
    }
    
    $validCategories = ['diplomacy', 'economy', 'technology', 'entertainment'];
    if (!in_array($category, $validCategories)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '유효하지 않은 카테고리입니다.']);
        exit;
    }
    
    try {
        // 뉴스 존재 여부 확인
        $stmt = $db->prepare("SELECT id FROM news WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
            exit;
        }
        
        $description = mb_substr(strip_tags($content), 0, 300);
        
        // 자동 이미지 URL 생성 (저작권 무료 - Unsplash)
        $imageUrl = generateImageUrl($title, $category, $keywordMap, $categoryDefaults);
        
        // source_url 컬럼 존재 여부 확인
        $hasSourceUrl = false;
        try {
            $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'source_url'");
            $hasSourceUrl = $checkCol->rowCount() > 0;
        } catch (Exception $e) {}
        
        if ($hasSourceUrl) {
            $stmt = $db->prepare("
                UPDATE news 
                SET category = ?, title = ?, description = ?, content = ?, source_url = ?, image_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$category, $title, $description, $content, $sourceUrl, $imageUrl, $id]);
        } else {
            $stmt = $db->prepare("
                UPDATE news 
                SET category = ?, title = ?, description = ?, content = ?, image_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$category, $title, $description, $content, $imageUrl, $id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '뉴스가 수정되었습니다.',
            'data' => [
                'id' => (int)$id,
                'category' => $category,
                'title' => $title,
                'source_url' => $sourceUrl
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '뉴스 수정 실패: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE: 뉴스 삭제
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
        exit;
    }
    
    try {
        // 뉴스 존재 여부 확인
        $stmt = $db->prepare("SELECT id, title FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => '뉴스가 삭제되었습니다.',
            'data' => [
                'id' => (int)$id,
                'title' => $news['title']
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '뉴스 삭제 실패: ' . $e->getMessage()]);
    }
    exit;
}

// 지원하지 않는 메서드
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
