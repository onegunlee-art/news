<?php
/**
 * Admin News API
 * GET: 뉴스 목록 조회
 * POST: 뉴스 저장
 * PUT: 뉴스 수정
 * DELETE: 뉴스 삭제
 */

require_once __DIR__ . '/../lib/log.php';

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

require_once __DIR__ . '/../lib/cors.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
setCorsHeaders();

// OPTIONS 요청 처리
handleOptionsRequest();

require_once __DIR__ . '/../lib/log.php';

if (!function_exists('logError')) {
function logError($message, $data = null) {
    app_error('news', $message, $data);
}
}

// JSON 입력 안전하게 읽기 (Router include 시 중복 정의 방지)
if (!function_exists('getJsonInput')) {
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
}

// 데이터베이스 설정 (config/database.php 사용)
$dbConfigPath = __DIR__ . '/../../../config/database.php';
$dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'ailand',
    'username' => getenv('DB_USERNAME') ?: 'ailand',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
$dbConfig['dbname'] = $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'ailand';

// 이미지 매핑 통합 파일 + API 검색 로직 로드
require_once __DIR__ . '/../lib/imageSearch.php';

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? [
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
$hasFuturePrediction = isset($newsColumns['future_prediction']);
$hasOriginalSource = isset($newsColumns['original_source']);
$hasOriginalTitle  = isset($newsColumns['original_title']);
$hasAuthor         = isset($newsColumns['author']);
$hasPublishedAt    = isset($newsColumns['published_at']);
$hasStatus         = isset($newsColumns['status']);
$hasUpdatedAt      = isset($newsColumns['updated_at']);
$hasCategoryParent = isset($newsColumns['category_parent']);
$hasAlsoSpecial    = isset($newsColumns['also_special']);
$hasViewCount      = isset($newsColumns['view_count']);

$method = $_SERVER['REQUEST_METHOD'];

// PATCH: also_special 토글 (뉴스 목록에서 체크박스 클릭)
if ($method === 'PATCH') {
    ob_clean();
    $input = getJsonInput();
    if (isset($input['error'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON 파싱 실패: ' . $input['error']]);
        exit;
    }
    $id = (int) ($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
        exit;
    }
    if (!$hasAlsoSpecial) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'also_special 컬럼이 존재하지 않습니다. 마이그레이션을 실행해주세요.']);
        exit;
    }
    $alsoSpecial = !empty($input['also_special']) ? 1 : 0;
    try {
        $stmt = $db->prepare("UPDATE news SET also_special = ? WHERE id = ?");
        $stmt->execute([$alsoSpecial, $id]);
        echo json_encode(['success' => true, 'message' => '특집 동시 노출 설정이 변경되었습니다.', 'data' => ['id' => $id, 'also_special' => $alsoSpecial]]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '업데이트 실패: ' . $e->getMessage()]);
    }
    exit;
}

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
    
    $categoryParent = $input['category_parent'] ?? '';
    $category = $input['category'] ?? '';  // 하위 카테고리 (slug 또는 직접 입력)
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $whyImportant = $input['why_important'] ?? null;
    $narration = $input['narration'] ?? null;
    if ($narration !== null && is_string($narration)) {
        $narration = trim(preg_replace('/^(여러분|시청자\s+여러분|청취자\s+여러분)[,.\s]*/u', '', trim($narration)));
        $narration = $narration !== '' ? $narration : null;
    }
    $futurePrediction = $input['future_prediction'] ?? null;
    $sourceUrl = $input['source_url'] ?? null;
    $originalTitle = $input['original_title'] ?? null;
    
    // 추가 메타데이터 필드
    $originalSource = $input['source'] ?? null;  // 원본 출처 (예: Financial Times)
    $author = $input['author'] ?? null;  // 원본 작성자
    $customImageUrl = $input['image_url'] ?? null;  // 사용자 지정 이미지 URL
    
    // published_at 처리: 빈 문자열이면 null, 그렇지 않으면 날짜 형식 변환 시도. 비어있으면 현재 날짜(우리 게시일)로 설정
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
    // status: draft(임시저장) | published(공개). hasStatus 없으면 published로 저장
    $status = $hasStatus ? ($input['status'] ?? 'published') : 'published';
    if ($hasStatus && !in_array($status, ['draft', 'published'])) {
        $status = 'published';
    }
    // 게시 정책: published 상태일 때만 현재 시각으로 published_at 설정 (draft는 null 유지)
    if ($status === 'published') {
        $publishedAt = date('Y-m-d H:i:s');
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
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '제목을 입력해주세요.']);
        exit;
    }
    if ($status !== 'draft' && empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '게시하려면 내용을 입력해주세요.']);
        exit;
    }
    
    $validParents = ['diplomacy', 'economy', 'special'];
    if (!in_array($categoryParent, $validParents)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '상위 카테고리(외교/경제/특집)를 선택해주세요.']);
        exit;
    }
    $category = mb_substr(trim($category), 0, 100) ?: $categoryParent;  // 하위 없으면 상위와 동일
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
        
        // UTF-8 안전한 description: 사용자 지정 요약이 있으면 사용, 없으면 content에서 생성 (300자)
        $customDescription = $input['description'] ?? null;
        $description = !empty($customDescription)
            ? mb_substr(strip_tags($customDescription), 0, 300, 'UTF-8')
            : mb_substr(strip_tags($content), 0, 300, 'UTF-8');
        
        // 이미지 URL: 사용자 지정 URL이 있으면 그것을 사용, 없으면 자동 생성
        $imageUrl = !empty($customImageUrl) ? $customImageUrl : smartImageUrl($title, $category, $db);
        
        // source 값: 원본 출처가 있으면 그것을 사용, 없으면 'Admin'
        $sourceValue = !empty($originalSource) ? $originalSource : 'Admin';
        
        // ★ 컬럼 존재 여부는 이미 상단 DESCRIBE에서 한 번만 조회 → SHOW COLUMNS 제거
        
        // 동적 INSERT 쿼리 생성 (category_parent + category; 없으면 category만)
        if ($hasCategoryParent) {
            $columns = ['category_parent', 'category', 'title', 'description', 'content', 'source', 'url', 'image_url', 'created_at'];
            $values = [$categoryParent, $category, $title, $description, $content, $sourceValue, $url, $imageUrl];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', 'NOW()'];
        } else {
            $columns = ['category', 'title', 'description', 'content', 'source', 'url', 'image_url', 'created_at'];
            $values = [$categoryParent, $title, $description, $content, $sourceValue, $url, $imageUrl];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?', 'NOW()'];
        }
        
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
        
        if ($hasFuturePrediction) {
            $columns[] = 'future_prediction';
            $values[] = $futurePrediction;
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
        
        if ($hasOriginalTitle) {
            $columns[] = 'original_title';
            $values[] = $originalTitle;
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
        if ($hasStatus) {
            $columns[] = 'status';
            $values[] = $status;
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
        
        // 기사 게시 시 최종 output RAG 임베딩 저장
        if ($status === 'published') {
            require_once __DIR__ . '/../lib/storePublishedNewsEmbedding.php';
            storePublishedNewsEmbedding($db, (int) $newsId);
        }

        // published 기사 TTS 선생성 (실패해도 게시 성공 유지)
        if ($status === 'published') {
            require_once __DIR__ . '/../lib/generateTtsForNews.php';
            generateTtsForNews([
                'id' => $newsId,
                'title' => $title,
                'narration' => $narration,
                'why_important' => $whyImportant,
                'content' => $content,
                'description' => $description,
                'original_title' => $originalTitle,
                'original_source' => $originalSourceField ?? $originalSource ?? null,
                'source_url' => $sourceUrl,
                'source' => $source,
                'published_at' => $publishedAt,
            ]);
        }
        
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
                if ($status === 'published') {
                    require_once __DIR__ . '/../lib/storePublishedNewsEmbedding.php';
                    storePublishedNewsEmbedding($db, (int) $newsId);
                    require_once __DIR__ . '/../lib/generateTtsForNews.php';
                    generateTtsForNews([
                        'id' => $newsId, 'title' => $title, 'narration' => $narration,
                        'why_important' => $whyImportant, 'content' => $content, 'description' => $description,
                        'original_title' => $originalTitle, 'original_source' => $originalSourceField ?? $originalSource ?? null,
                        'source_url' => $sourceUrl, 'source' => $source, 'published_at' => $publishedAt,
                    ]);
                }
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

// GET: 뉴스 단건 조회 (id 있을 때) 또는 목록 조회
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    // 단건 조회: Admin용 (draft 포함, status 무관)
    if ($id > 0) {
        try {
            $selCols = $hasCategoryParent
                ? 'id, category_parent, category, title, description, content, source, url, image_url, created_at'
                : 'id, category, title, description, content, source, url, image_url, created_at';
            if ($hasUpdatedAt) $selCols .= ', updated_at';
            if ($hasWhyImportant) $selCols .= ', why_important';
            if ($hasNarration) $selCols .= ', narration';
            if ($hasFuturePrediction) $selCols .= ', future_prediction';
            if ($hasSourceUrl) $selCols .= ', source_url';
            if ($hasOriginalSource) $selCols .= ', original_source';
            if ($hasOriginalTitle) $selCols .= ', original_title';
            if ($hasAuthor) $selCols .= ', author';
            if ($hasPublishedAt) $selCols .= ', published_at';
            if ($hasStatus) $selCols .= ', status';
            if ($hasAlsoSpecial) $selCols .= ', also_special';
            $stmt = $db->prepare("SELECT $selCols FROM news WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
                exit;
            }
            api_log('admin/news', 'GET', 200);
            $json = json_encode(['success' => true, 'data' => ['article' => $row]], JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'JSON 인코딩 실패: ' . json_last_error_msg()]);
                exit;
            }
            echo $json;
        } catch (PDOException $e) {
            api_log('admin/news', 'GET', 500, $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '뉴스 조회 실패: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // 목록 조회
    $category = $_GET['category'] ?? '';
    $query = $_GET['query'] ?? '';  // 검색어
    $statusFilter = $_GET['status_filter'] ?? '';  // draft | published
    $publishedOnly = isset($_GET['published_only']) && ($_GET['published_only'] === '1' || $_GET['published_only'] === 'true');
    $popular = isset($_GET['popular']) && ($_GET['popular'] === '1' || $_GET['popular'] === 'true');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, (int)($_GET['per_page'] ?? 20));
    if ($popular) {
        $perPage = 20;
        $page = 1;
        $offset = 0;
    } else {
        $offset = ($page - 1) * $perPage;
    }
    
    try {
        $conditions = [];
        $params = [];
        
        // 인기 탭: published만, 카테고리/검색 무시
        if ($popular && $hasStatus) {
            $conditions[] = "status = 'published'";
        }
        
        // 카테고리 필터 (상위: 외교/경제/특집) — popular일 때는 적용 안 함
        if ($category && !$popular) {
            if ($hasCategoryParent) {
                if ($category === 'special' && $hasAlsoSpecial) {
                    $conditions[] = '(category_parent = ? OR also_special = 1)';
                    $params[] = 'special';
                } else {
                    $conditions[] = 'category_parent = ?';
                    $params[] = $category;
                }
            } else {
                if ($category === 'special') {
                    $conditions[] = '(category = ? OR category = ?)';
                    $params[] = 'special';
                    $params[] = 'entertainment';
                } else {
                    $conditions[] = 'category = ?';
                    $params[] = $category;
                }
            }
        }
        
        // 키워드 검색 (제목, 내용, 설명에서 검색) — popular일 때는 적용 안 함
        if ($query && !$popular) {
            $searchTerm = '%' . $query . '%';
            $conditions[] = '(title LIKE ? OR content LIKE ? OR description LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // status 필터: published_only(유저용) 또는 status_filter(Admin용)
        if ($hasStatus) {
            if ($publishedOnly) {
                $conditions[] = "status = 'published'";
            } elseif ($statusFilter === 'draft' || $statusFilter === 'published') {
                $conditions[] = 'status = ?';
                $params[] = $statusFilter;
            }
        }
        
        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // 전체 수
        $stmt = $db->prepare("SELECT COUNT(*) FROM news $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        
        // ★ 컬럼 존재 여부는 상단 DESCRIBE에서 이미 조회됨 (SHOW COLUMNS 제거)
        $selectColumns = $hasCategoryParent
            ? 'id, category_parent, category, title, description, content, source, url, image_url, created_at'
            : 'id, category, title, description, content, source, url, image_url, created_at';
        if ($hasSourceUrl) {
            $selectColumns = $hasCategoryParent
                ? 'id, category_parent, category, title, description, content, source, url, source_url, image_url, created_at'
                : 'id, category, title, description, content, source, url, source_url, image_url, created_at';
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
        if ($hasFuturePrediction) {
            if ($hasNarration) {
                $selectColumns = str_replace('narration,', 'narration, future_prediction,', $selectColumns);
            } elseif ($hasWhyImportant) {
                $selectColumns = str_replace('why_important,', 'why_important, future_prediction,', $selectColumns);
            } else {
                $selectColumns = str_replace('content,', 'content, future_prediction,', $selectColumns);
            }
        }
        if ($hasOriginalSource) {
            $selectColumns = str_replace('source,', 'source, original_source,', $selectColumns);
        }
        if ($hasOriginalTitle) {
            $selectColumns = str_replace('title,', 'title, original_title,', $selectColumns);
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
        if ($hasStatus) {
            $selectColumns .= ', status';
        }
        if ($hasViewCount) {
            $selectColumns .= ', view_count';
        }
        if ($hasAlsoSpecial) {
            $selectColumns .= ', also_special';
        }
        
        $orderBy = 'ORDER BY COALESCE(published_at, created_at) DESC';
        if ($popular && $hasViewCount) {
            $orderBy = 'ORDER BY view_count DESC';
        }
        
        $stmt = $db->prepare("
            SELECT $selectColumns
            FROM news 
            $where
            $orderBy
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // 표시용 날짜 정책: display_date = published_at 우선 (게시 시점). 없으면 created_at (docs/DATE_POLICY.md)
        foreach ($news as &$item) {
            $item['display_date'] = $item['published_at'] ?? $item['created_at'] ?? null;
        }
        unset($item);
        api_log('admin/news', 'GET', 200);
        $totalPages = $popular ? 1 : (int) ceil($total / $perPage);
        echo json_encode([
            'success' => true,
            'message' => $popular ? '인기 기사' : ($query ? '검색 결과' : '뉴스 목록 조회 성공'),
            'data' => [
                'items' => $news,
                'query' => $query,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $popular ? count($news) : $total,
                    'total_pages' => $totalPages,
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
    $categoryParent = $input['category_parent'] ?? '';
    $category = $input['category'] ?? '';
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $whyImportant = $input['why_important'] ?? null;
    $narration = $input['narration'] ?? null;
    if ($narration !== null && is_string($narration)) {
        $narration = trim(preg_replace('/^(여러분|시청자\s+여러분|청취자\s+여러분)[,.\s]*/u', '', trim($narration)));
        $narration = $narration !== '' ? $narration : null;
    }
    $futurePrediction = $input['future_prediction'] ?? null;
    $sourceUrl = $input['source_url'] ?? null;
    
    // 추가 메타데이터 필드
    $sourceField = $input['source'] ?? null;
    $originalSourceField = $input['original_source'] ?? null;
    $originalTitle = $input['original_title'] ?? null;
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
    
    // status: draft | published (게시하기 시 published로 변경)
    $status = null;
    if ($hasStatus && isset($input['status'])) {
        $status = in_array($input['status'], ['draft', 'published']) ? $input['status'] : null;
    }
    // 게시 정책: published 상태이면 항상 현재 시각으로 published_at 갱신
    // (임시저장→게시, 수정 후 재게시 모두 게시/수정 시점이 기준)
    if ($status === 'published') {
        $publishedAt = date('Y-m-d H:i:s');
    }
    
    // 디버그 로깅
    logError('PUT request received', [
        'id' => $id,
        'category' => $category,
        'title_length' => strlen($title),
        'content_length' => strlen($content),
        'original_source' => $originalSourceField,
        'source' => $sourceField,
        'author' => $author
    ]);
    
    // 유효성 검사
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '뉴스 ID가 필요합니다.']);
        exit;
    }
    
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '제목을 입력해주세요.']);
        exit;
    }
    if ($status !== 'draft' && empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '게시하려면 내용을 입력해주세요.']);
        exit;
    }
    
    $validParents = ['diplomacy', 'economy', 'special'];
    if (!in_array($categoryParent, $validParents)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '상위 카테고리(외교/경제/특집)를 선택해주세요.']);
        exit;
    }
    $category = mb_substr(trim($category), 0, 100) ?: $categoryParent;
    try {
        // 뉴스 존재 여부 확인
        $stmt = $db->prepare("SELECT id FROM news WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '뉴스를 찾을 수 없습니다.']);
            exit;
        }
        
        // UTF-8 안전한 description: 사용자 지정 요약이 있으면 사용, 없으면 content에서 생성 (300자)
        $customDescription = $input['description'] ?? null;
        if (!empty($customDescription)) {
            $description = mb_substr(strip_tags($customDescription), 0, 300, 'UTF-8');
        } else {
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
        }
        
        // 이미지 URL: 사용자 지정 URL이 있으면 그것을 사용, 없으면 자동 생성
        $imageUrl = !empty($customImageUrl) ? $customImageUrl : smartImageUrl($title, $category, $db);
        
        // source 값: source 필드가 있으면 사용
        $sourceValue = !empty($sourceField) ? $sourceField : null;
        
        // ★ 컬럼 존재 여부는 상단 DESCRIBE에서 이미 조회됨 (SHOW COLUMNS 제거)
        
        // 동적 UPDATE 쿼리 생성 (category_parent + category; 없으면 category만)
        if ($hasCategoryParent) {
            $setClauses = ['category_parent = ?', 'category = ?', 'title = ?', 'description = ?', 'content = ?', 'image_url = ?'];
            $values = [$categoryParent, $category, $title, $description, $content, $imageUrl];
        } else {
            $setClauses = ['category = ?', 'title = ?', 'description = ?', 'content = ?', 'image_url = ?'];
            $values = [$categoryParent, $title, $description, $content, $imageUrl];
        }
        if ($hasUpdatedAt) {
            $setClauses[] = 'updated_at = NOW()';
        }
        
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
        
        if ($hasFuturePrediction) {
            $setClauses[] = 'future_prediction = ?';
            $values[] = $futurePrediction;
        }
        
        if ($hasSourceUrl) {
            $setClauses[] = 'source_url = ?';
            $values[] = $sourceUrl;
        }
        
        if ($hasOriginalSource) {
            $setClauses[] = 'original_source = ?';
            $values[] = $originalSourceField;
        }
        
        if ($hasOriginalTitle) {
            $setClauses[] = 'original_title = ?';
            $values[] = $originalTitle;
        }
        
        if ($hasAuthor) {
            $setClauses[] = 'author = ?';
            $values[] = $author;
        }
        
        if ($hasPublishedAt) {
            $setClauses[] = 'published_at = ?';
            $values[] = $publishedAt;
        }
        
        if ($hasStatus && $status !== null) {
            $setClauses[] = 'status = ?';
            $values[] = $status;
        }

        if ($hasAlsoSpecial && array_key_exists('also_special', $input)) {
            $setClauses[] = 'also_special = ?';
            $values[] = !empty($input['also_special']) ? 1 : 0;
        }
        
        $values[] = $id;  // WHERE 절용
        $setStr = implode(', ', $setClauses);
        
        $stmt = $db->prepare("UPDATE news SET $setStr WHERE id = ?");
        $stmt->execute($values);

        // TTS 캐시 무효화 (기사 수정 시 Supabase media_cache 삭제 + 로컬 wav 삭제)
        require_once __DIR__ . '/../lib/invalidateTtsCache.php';
        invalidateTtsCacheForNews((int) $id);

        // 기사 게시 시 최종 output RAG 임베딩 저장
        if ($status === 'published') {
            require_once __DIR__ . '/../lib/storePublishedNewsEmbedding.php';
            storePublishedNewsEmbedding($db, (int) $id);
        }

        // published 기사 TTS 선생성 (무효화 후 최신 내용으로 재생성, 실패해도 수정 성공 유지)
        if ($status === 'published') {
            require_once __DIR__ . '/../lib/generateTtsForNews.php';
            generateTtsForNews([
                'id' => $id,
                'title' => $title,
                'narration' => $narration,
                'why_important' => $whyImportant,
                'content' => $content,
                'description' => $description,
                'original_title' => $originalTitle,
                'original_source' => $originalSourceField ?? $originalSource ?? null,
                'source_url' => $sourceUrl,
                'source' => $source,
                'published_at' => $publishedAt,
            ]);
        }

        $selCols = $hasCategoryParent
            ? 'id, category_parent, category, title, description, content, source, url, image_url, created_at'
            : 'id, category, title, description, content, source, url, image_url, created_at';
        if ($hasUpdatedAt) $selCols .= ', updated_at';
        if ($hasWhyImportant) $selCols .= ', why_important';
        if ($hasNarration) $selCols .= ', narration';
        if ($hasFuturePrediction) $selCols .= ', future_prediction';
        if ($hasSourceUrl) $selCols .= ', source_url';
        if ($hasOriginalSource) $selCols .= ', original_source';
        if ($hasOriginalTitle) $selCols .= ', original_title';
        if ($hasAuthor) $selCols .= ', author';
        if ($hasPublishedAt) $selCols .= ', published_at';
        if ($hasStatus) $selCols .= ', status';
        if ($hasAlsoSpecial) $selCols .= ', also_special';

        $freshStmt = $db->prepare("SELECT $selCols FROM news WHERE id = ?");
        $freshStmt->execute([$id]);
        $freshRow = $freshStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => '뉴스가 수정되었습니다.',
            'data' => [
                'article' => $freshRow ?: [
                    'id' => (int)$id,
                    'category' => $category,
                    'title' => $title,
                    'description' => $description,
                    'content' => $content,
                    'why_important' => $whyImportant,
                    'narration' => $narration,
                    'source_url' => $sourceUrl,
                    'original_source' => $originalSourceField,
                    'original_title' => $originalTitle,
                    'author' => $author,
                    'image_url' => $imageUrl,
                    'published_at' => $publishedAt,
                    'status' => $status,
                ]
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

        // TTS 캐시 무효화 (기사 삭제 시)
        require_once __DIR__ . '/../lib/invalidateTtsCache.php';
        invalidateTtsCacheForNews((int) $id);
        
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
