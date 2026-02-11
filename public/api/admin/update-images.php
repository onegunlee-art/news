<?php
/**
 * 뉴스 이미지 자동 매칭 API
 * 저작권 무료 이미지 - 고정 placeholder URL 사용 (API 없음)
 * Supabase 설정 시 media_cache로 썸네일 URL 캐시 (동일 기사·제목·카테고리면 재계산 생략)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 프로젝트 루트 및 Supabase(미디어 캐시) 로드
$updateImagesProjectRoot = null;
foreach ([__DIR__ . '/../../../', __DIR__ . '/../../', __DIR__ . '/../'] as $raw) {
    $path = realpath($raw);
    if ($path === false) {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
    }
    if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
        $updateImagesProjectRoot = rtrim($path, '/\\') . '/';
        break;
    }
}
$supabaseForThumb = null;
if ($updateImagesProjectRoot) {
    foreach ([$updateImagesProjectRoot . 'env.txt', $updateImagesProjectRoot . '.env'] as $f) {
        if (is_file($f) && is_readable($f)) {
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\"'");
                    if ($name !== '') { putenv("$name=$value"); $_ENV[$name] = $value; }
                }
            }
            break;
        }
    }
    if (file_exists($updateImagesProjectRoot . 'src/agents/autoload.php')) {
        require_once $updateImagesProjectRoot . 'src/agents/autoload.php';
        $supabaseForThumb = new \Agents\Services\SupabaseService([]);
        if (!$supabaseForThumb->isConfigured()) {
            $supabaseForThumb = null;
        }
    }
}

function buildThumbnailCacheKey(string $title, string $category): string {
    return hash('sha256', json_encode([$title, $category], JSON_UNESCAPED_UNICODE));
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
    // 특정 뉴스 이미지 업데이트 (Supabase 설정 시 media_cache 조회 후 미스면 생성·저장)
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

    $title = $news['title'];
    $category = $news['category'] ?? '';
    $newImageUrl = null;
    $fromCache = false;

    if ($supabaseForThumb !== null) {
        $thumbHash = buildThumbnailCacheKey($title, $category);
        $cacheQuery = 'news_id=eq.' . (int) $newsId . '&media_type=eq.thumbnail&generation_params->>hash=eq.' . rawurlencode($thumbHash);
        $cached = $supabaseForThumb->select('media_cache', $cacheQuery, 1);
        if (!empty($cached) && is_array($cached) && !empty($cached[0]['file_url'])) {
            $newImageUrl = $cached[0]['file_url'];
            $fromCache = true;
        }
    }

    if ($newImageUrl === null) {
        $newImageUrl = smartImageUrl($title, $category, $pdo);
        if ($supabaseForThumb !== null) {
            $thumbHash = buildThumbnailCacheKey($title, $category);
            $supabaseForThumb->insert('media_cache', [
                'news_id' => (int) $newsId,
                'media_type' => 'thumbnail',
                'file_url' => $newImageUrl,
                'generation_params' => [
                    'hash' => $thumbHash,
                    'title_preview' => mb_substr($title, 0, 100),
                    'category' => $category,
                ],
            ]);
        }
    }
    
    $updateStmt = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
    $updateStmt->execute([$newImageUrl, $newsId]);
    
    $matchedKeywords = extractMatchedKeyword($title, $imageMap);
    
    $out = [
        'success' => true,
        'id' => $newsId,
        'title' => $title,
        'matched_keywords' => $matchedKeywords,
        'new_image' => $newImageUrl,
    ];
    if ($fromCache) {
        $out['from_cache'] = true;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    
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
