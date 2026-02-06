<?php
/**
 * Admin News API
 * GET: 뉴스 목록 조회
 * POST: 뉴스 저장
 * PUT: 뉴스 수정
 * DELETE: 뉴스 삭제
 */

require __DIR__ . '/../lib/log.php';

// 에러 리포팅 설정 - JSON 응답만 출력하도록
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 출력 버퍼링 시작 - PHP 경고 메시지가 JSON을 오염시키지 않도록
ob_start();

// 종료 시 에러 체크
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal Error: ' . $error['message']
        ]);
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 에러 로깅 함수
function logError($message, $data = null) {
    $logFile = __DIR__ . '/news_error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data) {
        $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $logMessage .= "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// JSON 입력 안전하게 읽기
function getJsonInput() {
    $rawInput = file_get_contents('php://input');
    
    // 빈 입력 체크
    if (empty($rawInput)) {
        return ['error' => 'Empty request body'];
    }
    
    // JSON 디코딩
    $input = json_decode($rawInput, true);
    
    // JSON 디코딩 에러 체크
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError('JSON decode error: ' . json_last_error_msg(), ['raw_length' => strlen($rawInput)]);
        return ['error' => 'Invalid JSON: ' . json_last_error_msg()];
    }
    
    return $input;
}

// 데이터베이스 설정
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'romi4120!',
    'charset' => 'utf8mb4'
];

// 고정 이미지 URL 매핑 (Wikimedia Commons / Public Domain / Unsplash)
$imageMap = [
    // 실제 정치인 사진 (Wikimedia Commons - Public Domain / CC)
    'trump' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/5/56/Donald_Trump_official_portrait.jpg/800px-Donald_Trump_official_portrait.jpg'],
    '트럼프' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/5/56/Donald_Trump_official_portrait.jpg/800px-Donald_Trump_official_portrait.jpg'],
    'biden' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Joe_Biden_presidential_portrait.jpg/800px-Joe_Biden_presidential_portrait.jpg'],
    '바이든' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Joe_Biden_presidential_portrait.jpg/800px-Joe_Biden_presidential_portrait.jpg'],
    'putin' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/Vladimir_Putin_%282020-02-20%29.jpg/800px-Vladimir_Putin_%282020-02-20%29.jpg'],
    '푸틴' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/Vladimir_Putin_%282020-02-20%29.jpg/800px-Vladimir_Putin_%282020-02-20%29.jpg'],
    '시진핑' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/3/32/Xi_Jinping_2019.jpg/800px-Xi_Jinping_2019.jpg'],
    'xi' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/3/32/Xi_Jinping_2019.jpg/800px-Xi_Jinping_2019.jpg'],
    '윤석열' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/1/12/Yoon_Suk-yeol_May_2022.jpg/800px-Yoon_Suk-yeol_May_2022.jpg'],
    '김정은' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/2/21/Kim_Jong-un_at_the_2019_Russia%E2%80%93North_Korea_summit_%28cropped%29.jpg/800px-Kim_Jong-un_at_the_2019_Russia%E2%80%93North_Korea_summit_%28cropped%29.jpg'],
    '머스크' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Elon_Musk_Royal_Society_%28crop2%29.jpg/800px-Elon_Musk_Royal_Society_%28crop2%29.jpg'],
    'musk' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Elon_Musk_Royal_Society_%28crop2%29.jpg/800px-Elon_Musk_Royal_Society_%28crop2%29.jpg'],
    'elon' => ['https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Elon_Musk_Royal_Society_%28crop2%29.jpg/800px-Elon_Musk_Royal_Society_%28crop2%29.jpg'],
    // 지역/장소
    'greenland' => ['https://images.unsplash.com/photo-1517783999520-f068f9e28a51?w=800&h=500&fit=crop'],
    '그린란드' => ['https://images.unsplash.com/photo-1517783999520-f068f9e28a51?w=800&h=500&fit=crop'],
    // 기술/AI
    'openai' => ['https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop'],
    'ai' => ['https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop'],
    '인공지능' => ['https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop'],
    // 엔터테인먼트
    'k-pop' => ['https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop'],
    'kpop' => ['https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop'],
    '케이팝' => ['https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop'],
    // 경제
    '경제' => ['https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop'],
    '주식' => ['https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop'],
    '비트코인' => ['https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800&h=500&fit=crop'],
    '반도체' => ['https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop'],
    // 외교
    '외교' => ['https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&h=500&fit=crop'],
    '한미' => ['https://images.unsplash.com/photo-1508433957232-3107f5fd5995?w=800&h=500&fit=crop'],
    // 기타
    '구독자' => ['https://images.unsplash.com/photo-1533227268428-f9ed0900fb3b?w=800&h=500&fit=crop'],
    '축하' => ['https://images.unsplash.com/photo-1533227268428-f9ed0900fb3b?w=800&h=500&fit=crop'],
    '테스트' => ['https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&h=500&fit=crop'],
    '광고' => ['https://images.unsplash.com/photo-1557804506-669a67965ba0?w=800&h=500&fit=crop'],
];

$categoryDefaults = [
    'diplomacy' => ['https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=800&h=500&fit=crop'],
    'economy' => ['https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop'],
    'technology' => ['https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop'],
    'entertainment' => ['https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop'],
];

$defaultImages = [
    'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1495020689067-958852a7765e?w=800&h=500&fit=crop',
];

// 고정 이미지 URL 생성 함수
function generateImageUrl($title, $category, $imageMap, $categoryDefaults, $defaultImages) {
    $titleLower = strtolower($title);
    
    // 1. 제목에서 키워드 매칭
    foreach ($imageMap as $keyword => $urls) {
        if (strpos($titleLower, strtolower($keyword)) !== false) {
            $index = abs(crc32($title)) % count($urls);
            return $urls[$index];
        }
    }
    
    // 2. 카테고리 기반
    $cat = strtolower($category ?? '');
    if (isset($categoryDefaults[$cat])) {
        $urls = $categoryDefaults[$cat];
        return $urls[0];
    }
    
    // 3. 기본 이미지
    $index = abs(crc32($title)) % count($defaultImages);
    return $defaultImages[$index];
}

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ── 컬럼 존재 여부를 한 번만 확인 (SHOW COLUMNS x6 → DESCRIBE x1) ──
$newsColumns = [];
try {
    $colStmt = $db->query("DESCRIBE news");
    while ($row = $colStmt->fetch()) {
        $newsColumns[$row['Field']] = true;
    }
} catch (Exception $e) {
    logError('DESCRIBE news failed: ' . $e->getMessage());
}
$hasSourceUrl      = isset($newsColumns['source_url']);
$hasWhyImportant   = isset($newsColumns['why_important']);
$hasNarration      = isset($newsColumns['narration']);
$hasOriginalSource = isset($newsColumns['original_source']);
$hasAuthor         = isset($newsColumns['author']);
$hasPublishedAt    = isset($newsColumns['published_at']);

$method = $_SERVER['REQUEST_METHOD'];

// POST: 뉴스 저장
if ($method === 'POST') {
    // 버퍼 클리어
    ob_clean();
    
    // 요청 크기 체크
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    logError('POST content length: ' . $contentLength);
    
    // 최대 요청 크기 체크 (10MB)
    if ($contentLength > 10 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['success' => false, 'message' => '요청이 너무 큽니다. (최대 10MB)']);
        exit;
    }
    
    $input = getJsonInput();
    
    // JSON 파싱 에러 체크
    if (isset($input['error'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON 파싱 실패: ' . $input['error']]);
        exit;
    }
    
    $category = $input['category'] ?? '';
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $whyImportant = $input['why_important'] ?? null;
    $narration = $input['narration'] ?? null;
    $sourceUrl = $input['source_url'] ?? null;
    
    // 추가 메타데이터 필드
    $originalSource = $input['source'] ?? null;  // 원본 출처 (예: Financial Times)
    $author = $input['author'] ?? null;  // 원본 작성자
    $customImageUrl = $input['image_url'] ?? null;  // 사용자 지정 이미지 URL
    
    // published_at 처리: 빈 문자열이면 null, 그렇지 않으면 날짜 형식 변환 시도
    $publishedAtRaw = $input['published_at'] ?? null;
    $publishedAt = null;
    if (!empty($publishedAtRaw)) {
        try {
            // 다양한 날짜 형식 지원
            $date = new DateTime($publishedAtRaw);
            $publishedAt = $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // 날짜 파싱 실패시 null
            $publishedAt = null;
            logError('Failed to parse published_at: ' . $publishedAtRaw);
        }
    }
    
    // 디버그 로깅
    logError('POST request received', [
        'category' => $category,
        'title_length' => strlen($title),
        'content_length' => strlen($content),
        'original_source' => $originalSource,
        'author' => $author
    ]);
    
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
        logError('Starting database insert process');
        
        // source_url이 있으면 그것을 사용, 없으면 admin:// URL 생성
        // ★ url 컬럼에 UNIQUE(255) 제약이 있으므로 항상 고유하게 생성
        $url = $sourceUrl ? $sourceUrl : 'admin://news/' . uniqid('', true) . '-' . time();
        
        // ★ 같은 source_url로 이미 저장된 뉴스가 있는지 확인 → 있으면 고유 접미사 추가
        if ($sourceUrl) {
            $chk = $db->prepare("SELECT id FROM news WHERE url = ? LIMIT 1");
            $chk->execute([$url]);
            if ($chk->fetch()) {
                // 이미 동일 URL 존재 → 접미사를 붙여 유니크하게
                $url = $sourceUrl . '#dup-' . uniqid('', true);
            }
        }
        
        // UTF-8 안전한 description 생성 (300자)
        $description = mb_substr(strip_tags($content), 0, 300, 'UTF-8');
        
        // 이미지 URL: 사용자 지정 URL이 있으면 그것을 사용, 없으면 자동 생성
        $imageUrl = !empty($customImageUrl) ? $customImageUrl : generateImageUrl($title, $category, $imageMap, $categoryDefaults, $defaultImages);
        
        // source 값: 원본 출처가 있으면 그것을 사용, 없으면 'Admin'
        $sourceValue = !empty($originalSource) ? $originalSource : 'Admin';
        
        // ★ 컬럼 존재 여부는 이미 상단 DESCRIBE에서 한 번만 조회 → SHOW COLUMNS 제거
        
        // 동적 INSERT 쿼리 생성
        $columns = ['category', 'title', 'description', 'content', 'source', 'url', 'image_url', 'created_at'];
        $values = [$category, $title, $description, $content, $sourceValue, $url, $imageUrl];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', 'NOW()'];
        
        if ($hasWhyImportant) {
            $columns[] = 'why_important';
            $values[] = $whyImportant;
            $placeholders[] = '?';
        }
        
        if ($hasNarration) {
            $columns[] = 'narration';
            $values[] = $narration;
            $placeholders[] = '?';
        }
        
        if ($hasSourceUrl) {
            $columns[] = 'source_url';
            $values[] = $sourceUrl;
            $placeholders[] = '?';
        }
        
        if ($hasOriginalSource) {
            $columns[] = 'original_source';
            $values[] = $originalSource;
            $placeholders[] = '?';
        }
        
        if ($hasAuthor) {
            $columns[] = 'author';
            $values[] = $author;
            $placeholders[] = '?';
        }
        
        if ($hasPublishedAt) {
            $columns[] = 'published_at';
            $values[] = $publishedAt;
            $placeholders[] = '?';
        }
        
        $columnStr = implode(', ', $columns);
        $placeholderStr = implode(', ', $placeholders);
        
        logError('Dynamic INSERT', [
            'columns' => $columnStr,
            'url' => mb_substr($url, 0, 80),
        ]);
        
        $stmt = $db->prepare("INSERT INTO news ($columnStr) VALUES ($placeholderStr)");
        $stmt->execute($values);
        
        $newsId = $db->lastInsertId();
        
        logError('Insert successful', ['news_id' => $newsId]);
        
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
        $code = $e->getCode();
        $msg = $e->getMessage();
        logError('Database error during insert', ['error' => $msg, 'code' => $code]);

        // ★ UNIQUE 제약 위반(23000)이면 재시도 1회
        if ($code == 23000 || stripos($msg, 'Duplicate') !== false) {
            try {
                // url에 타임스탬프 접미사 추가해서 재시도
                $url .= '#retry-' . uniqid('', true);
                // values 배열에서 url 위치(인덱스 5) 갱신
                $values[5] = $url;
                logError('Retrying INSERT with unique url', ['url' => mb_substr($url, 0, 80)]);
                $stmt = $db->prepare("INSERT INTO news ($columnStr) VALUES ($placeholderStr)");
                $stmt->execute($values);
                $newsId = $db->lastInsertId();
                logError('Retry insert successful', ['news_id' => $newsId]);
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => '뉴스가 저장되었습니다.',
                    'data' => ['id' => (int)$newsId, 'category' => $category, 'title' => $title, 'source_url' => $sourceUrl]
                ]);
                exit;
            } catch (PDOException $e2) {
                logError('Retry also failed', ['error' => $e2->getMessage()]);
            }
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '뉴스 저장 실패: ' . $msg]);
    } catch (Exception $e) {
        logError('General error during insert', ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '오류 발생: ' . $e->getMessage()]);
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
        
        // ★ 컬럼 존재 여부는 상단 DESCRIBE에서 이미 조회됨 (SHOW COLUMNS 제거)
        $selectColumns = 'id, category, title, description, content, source, image_url, created_at';
        if ($hasSourceUrl) {
            $selectColumns = 'id, category, title, description, content, source, source_url, image_url, created_at';
        }
        if ($hasWhyImportant) {
            $selectColumns = str_replace('content,', 'content, why_important,', $selectColumns);
        }
        if ($hasNarration) {
            $selectColumns = str_replace('why_important,', 'why_important, narration,', $selectColumns);
            if (!$hasWhyImportant) {
                $selectColumns = str_replace('content,', 'content, narration,', $selectColumns);
            }
        }
        if ($hasOriginalSource) {
            $selectColumns = str_replace('source,', 'source, original_source,', $selectColumns);
        }
        if ($hasAuthor) {
            $selectColumns = str_replace('original_source,', 'original_source, author,', $selectColumns);
            if (!$hasOriginalSource) {
                $selectColumns = str_replace('source,', 'source, author,', $selectColumns);
            }
        }
        if ($hasPublishedAt) {
            $selectColumns .= ', published_at';
        }
        
        $stmt = $db->prepare("
            SELECT $selectColumns
            FROM news 
            $where
            ORDER BY created_at DESC 
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        api_log('admin/news', 'GET', 200);
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
        api_log('admin/news', 'GET', 500, $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '뉴스 조회 실패: ' . $e->getMessage()]);
    }
    exit;
}

// PUT: 뉴스 수정
if ($method === 'PUT') {
    // 버퍼 클리어
    ob_clean();
    
    $input = getJsonInput();
    
    // JSON 파싱 에러 체크
    if (isset($input['error'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON 파싱 실패: ' . $input['error']]);
        exit;
    }
    
    $id = $input['id'] ?? 0;
    $category = $input['category'] ?? '';
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $whyImportant = $input['why_important'] ?? null;
    $narration = $input['narration'] ?? null;
    $sourceUrl = $input['source_url'] ?? null;
    
    // 추가 메타데이터 필드
    $originalSource = $input['source'] ?? null;
    $author = $input['author'] ?? null;
    $customImageUrl = $input['image_url'] ?? null;
    
    // published_at 처리: 빈 문자열이면 null, 그렇지 않으면 날짜 형식 변환 시도
    $publishedAtRaw = $input['published_at'] ?? null;
    $publishedAt = null;
    if (!empty($publishedAtRaw)) {
        try {
            $date = new DateTime($publishedAtRaw);
            $publishedAt = $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $publishedAt = null;
        }
    }
    
    // 디버그 로깅
    logError('PUT request received', [
        'id' => $id,
        'category' => $category,
        'title_length' => strlen($title),
        'content_length' => strlen($content),
        'original_source' => $originalSource,
        'author' => $author
    ]);
    
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
        
        // UTF-8 안전한 description 생성 (300자 제한, 문자 기반)
        $cleanContent = strip_tags($content);
        $description = '';
        $charCount = 0;
        $len = strlen($cleanContent);
        for ($i = 0; $i < $len && $charCount < 300; ) {
            $byte = ord($cleanContent[$i]);
            if ($byte < 128) {
                $description .= $cleanContent[$i];
                $i++;
            } elseif (($byte & 0xE0) == 0xC0) {
                $description .= substr($cleanContent, $i, 2);
                $i += 2;
            } elseif (($byte & 0xF0) == 0xE0) {
                $description .= substr($cleanContent, $i, 3);
                $i += 3;
            } elseif (($byte & 0xF8) == 0xF0) {
                $description .= substr($cleanContent, $i, 4);
                $i += 4;
            } else {
                $i++;
            }
            $charCount++;
        }
        
        // 이미지 URL: 사용자 지정 URL이 있으면 그것을 사용, 없으면 자동 생성
        $imageUrl = !empty($customImageUrl) ? $customImageUrl : generateImageUrl($title, $category, $imageMap, $categoryDefaults, $defaultImages);
        
        // source 값: 원본 출처가 있으면 그것을 사용
        $sourceValue = !empty($originalSource) ? $originalSource : null;
        
        // ★ 컬럼 존재 여부는 상단 DESCRIBE에서 이미 조회됨 (SHOW COLUMNS 제거)
        
        // 동적 UPDATE 쿼리 생성
        $setClauses = ['category = ?', 'title = ?', 'description = ?', 'content = ?', 'image_url = ?'];
        $values = [$category, $title, $description, $content, $imageUrl];
        
        // source 값 업데이트 (원본 출처가 있으면)
        if ($sourceValue !== null) {
            $setClauses[] = 'source = ?';
            $values[] = $sourceValue;
        }
        
        if ($hasWhyImportant) {
            $setClauses[] = 'why_important = ?';
            $values[] = $whyImportant;
        }
        
        if ($hasNarration) {
            $setClauses[] = 'narration = ?';
            $values[] = $narration;
        }
        
        if ($hasSourceUrl) {
            $setClauses[] = 'source_url = ?';
            $values[] = $sourceUrl;
        }
        
        if ($hasOriginalSource) {
            $setClauses[] = 'original_source = ?';
            $values[] = $originalSource;
        }
        
        if ($hasAuthor) {
            $setClauses[] = 'author = ?';
            $values[] = $author;
        }
        
        if ($hasPublishedAt) {
            $setClauses[] = 'published_at = ?';
            $values[] = $publishedAt;
        }
        
        $values[] = $id;  // WHERE 절용
        $setStr = implode(', ', $setClauses);
        
        $stmt = $db->prepare("UPDATE news SET $setStr WHERE id = ?");
        $stmt->execute($values);
        
        echo json_encode([
            'success' => true,
            'message' => '뉴스가 수정되었습니다.',
            'data' => [
                'id' => (int)$id,
                'category' => $category,
                'title' => $title,
                'why_important' => $whyImportant,
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
