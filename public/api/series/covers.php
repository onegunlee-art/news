<?php
/**
 * Series Covers API
 * GET: 시리즈 표지 조회 (featured=1이면 과거 특집용, series_id=xxx면 단건)
 * POST: 시리즈 표지 생성 (admin only)
 * PUT: 시리즈 표지 수정 (admin only)
 */

require_once __DIR__ . '/../lib/log.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

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
require_once __DIR__ . '/../lib/env_bootstrap.php';
require_once __DIR__ . '/../lib/admin_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
setCorsHeaders();
handleOptionsRequest();

function getJsonInput() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        return [];
    }
    $decoded = json_decode($rawInput, true);
    return is_array($decoded) ? $decoded : [];
}

$dbConfigPath = __DIR__ . '/../../../config/database.php';
$dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'ailand',
    'username' => getenv('DB_USERNAME') ?: 'ailand',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
$dbConfig['dbname'] = $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'ailand';

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

// 테이블 존재 여부 확인
try {
    $db->query("SELECT 1 FROM series_covers LIMIT 1");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'series_covers 테이블이 없습니다. 마이그레이션을 실행하세요.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: 시리즈 표지 조회
if ($method === 'GET') {
    $featured = isset($_GET['featured']) && $_GET['featured'] === '1';
    $seriesId = $_GET['series_id'] ?? null;
    $all = isset($_GET['all']) && $_GET['all'] === '1';
    
    try {
        if ($seriesId) {
            // 특정 시리즈 표지 조회
            $stmt = $db->prepare("SELECT * FROM series_covers WHERE series_id = ?");
            $stmt->execute([$seriesId]);
            $cover = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $cover ?: null], JSON_UNESCAPED_UNICODE);
        } elseif ($featured) {
            // 과거 특집용: is_featured=1인 것들 + 첫 기사 정보
            $stmt = $db->query("
                SELECT 
                    sc.*,
                    n.id as first_article_id,
                    n.title as first_article_title,
                    n.image_url as first_article_image,
                    n.series_title
                FROM series_covers sc
                LEFT JOIN (
                    SELECT series_id, MIN(id) as min_id
                    FROM news
                    WHERE series_id IS NOT NULL AND (status IS NULL OR status = 'published')
                    GROUP BY series_id
                ) first_articles ON sc.series_id = first_articles.series_id
                LEFT JOIN news n ON n.id = first_articles.min_id
                WHERE sc.is_featured = 1
                ORDER BY sc.display_order ASC, sc.created_at DESC
            ");
            $covers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => ['covers' => $covers]], JSON_UNESCAPED_UNICODE);
        } elseif ($all) {
            // 모든 시리즈 표지 조회 (admin용)
            $stmt = $db->query("
                SELECT 
                    sc.*,
                    n.id as first_article_id,
                    n.title as first_article_title,
                    n.image_url as first_article_image,
                    n.series_title,
                    (SELECT COUNT(*) FROM news WHERE news.series_id = sc.series_id) as article_count
                FROM series_covers sc
                LEFT JOIN (
                    SELECT series_id, MIN(id) as min_id
                    FROM news
                    WHERE series_id IS NOT NULL
                    GROUP BY series_id
                ) first_articles ON sc.series_id = first_articles.series_id
                LEFT JOIN news n ON n.id = first_articles.min_id
                ORDER BY sc.display_order ASC, sc.created_at DESC
            ");
            $covers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => ['covers' => $covers]], JSON_UNESCAPED_UNICODE);
        } else {
            // 기본: 시리즈 목록 (표지 정보 없는 것 포함)
            $stmt = $db->query("
                SELECT 
                    n.series_id,
                    n.series_title,
                    COUNT(*) as article_count,
                    first_article.id as first_article_id,
                    first_article.image_url as first_article_image,
                    sc.id as cover_id,
                    sc.cover_text,
                    sc.text_color,
                    sc.text_size,
                    sc.text_x,
                    sc.text_y,
                    sc.is_featured,
                    sc.display_order
                FROM news n
                LEFT JOIN (
                    SELECT id, series_id, image_url
                    FROM news n2
                    WHERE n2.id = (
                        SELECT MIN(n3.id) FROM news n3
                        WHERE n3.series_id = n2.series_id AND n3.series_id IS NOT NULL
                    )
                ) first_article ON n.series_id = first_article.series_id
                LEFT JOIN series_covers sc ON n.series_id = sc.series_id
                WHERE n.series_id IS NOT NULL
                GROUP BY n.series_id, n.series_title, first_article.id, first_article.image_url,
                         sc.id, sc.cover_text, sc.text_color, sc.text_size, sc.text_x, sc.text_y, sc.is_featured, sc.display_order
                ORDER BY MAX(n.id) DESC
            ");
            $series = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => ['series' => $series]], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '조회 실패: ' . $e->getMessage()]);
    }
    exit;
}

// POST: 시리즈 표지 생성 (admin only)
if ($method === 'POST') {
    requireAdminApi($db);
    
    $input = getJsonInput();
    $seriesId = $input['series_id'] ?? null;
    
    if (!$seriesId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'series_id가 필요합니다.']);
        exit;
    }
    
    $coverText = $input['cover_text'] ?? null;
    $textColor = $input['text_color'] ?? '#ffffff';
    $textSize = (int)($input['text_size'] ?? 24);
    $textX = (int)($input['text_x'] ?? 50);
    $textY = (int)($input['text_y'] ?? 50);
    $isFeatured = !empty($input['is_featured']) ? 1 : 0;
    $displayOrder = (int)($input['display_order'] ?? 0);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO series_covers (series_id, cover_text, text_color, text_size, text_x, text_y, is_featured, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                cover_text = VALUES(cover_text),
                text_color = VALUES(text_color),
                text_size = VALUES(text_size),
                text_x = VALUES(text_x),
                text_y = VALUES(text_y),
                is_featured = VALUES(is_featured),
                display_order = VALUES(display_order),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$seriesId, $coverText, $textColor, $textSize, $textX, $textY, $isFeatured, $displayOrder]);
        
        // 저장된 데이터 반환
        $fetchStmt = $db->prepare("SELECT * FROM series_covers WHERE series_id = ?");
        $fetchStmt->execute([$seriesId]);
        $cover = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $cover], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '저장 실패: ' . $e->getMessage()]);
    }
    exit;
}

// PUT: 시리즈 표지 수정 (admin only)
if ($method === 'PUT') {
    requireAdminApi($db);
    
    $input = getJsonInput();
    $seriesId = $input['series_id'] ?? null;
    
    if (!$seriesId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'series_id가 필요합니다.']);
        exit;
    }
    
    $setClauses = [];
    $values = [];
    
    if (array_key_exists('cover_text', $input)) {
        $setClauses[] = 'cover_text = ?';
        $values[] = $input['cover_text'];
    }
    if (array_key_exists('text_color', $input)) {
        $setClauses[] = 'text_color = ?';
        $values[] = $input['text_color'];
    }
    if (array_key_exists('text_size', $input)) {
        $setClauses[] = 'text_size = ?';
        $values[] = (int)$input['text_size'];
    }
    if (array_key_exists('text_x', $input)) {
        $setClauses[] = 'text_x = ?';
        $values[] = (int)$input['text_x'];
    }
    if (array_key_exists('text_y', $input)) {
        $setClauses[] = 'text_y = ?';
        $values[] = (int)$input['text_y'];
    }
    if (array_key_exists('is_featured', $input)) {
        $setClauses[] = 'is_featured = ?';
        $values[] = !empty($input['is_featured']) ? 1 : 0;
    }
    if (array_key_exists('display_order', $input)) {
        $setClauses[] = 'display_order = ?';
        $values[] = (int)$input['display_order'];
    }
    
    if (empty($setClauses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '수정할 필드가 없습니다.']);
        exit;
    }
    
    $values[] = $seriesId;
    
    try {
        $stmt = $db->prepare("UPDATE series_covers SET " . implode(', ', $setClauses) . " WHERE series_id = ?");
        $stmt->execute($values);
        
        // 수정된 데이터 반환
        $fetchStmt = $db->prepare("SELECT * FROM series_covers WHERE series_id = ?");
        $fetchStmt->execute([$seriesId]);
        $cover = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $cover], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '수정 실패: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE: 시리즈 표지 삭제 (admin only)
if ($method === 'DELETE') {
    requireAdminApi($db);
    
    $seriesId = $_GET['series_id'] ?? null;
    
    if (!$seriesId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'series_id가 필요합니다.']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM series_covers WHERE series_id = ?");
        $stmt->execute([$seriesId]);
        
        echo json_encode(['success' => true, 'message' => '삭제되었습니다.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '삭제 실패: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
