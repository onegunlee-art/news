<?php
/**
 * 뉴스 상세 조회 API
 * GET: /api/news/detail.php?id=123
 *
 * 선택 컬럼(DB에 없을 수 있음): why_important, narration, source_url, published_at, original_source, updated_at
 * 새 선택 컬럼 추가 시 반드시: 1) SHOW COLUMNS 존재 여부 확인 추가 2) $columns 조합에 조건부 추가 3) 응답 data에 조건부 추가
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
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
// #region agent log
$debugLogPaths = [__DIR__ . '/debug_detail.log', __DIR__ . '/../../../.cursor/debug.log', __DIR__ . '/../../../storage/logs/debug.log'];
$debugPayload = function ($location, $message, $data, $hypothesisId) use ($debugLogPaths) {
    $line = json_encode(['timestamp' => round(microtime(true) * 1000), 'location' => $location, 'message' => $message, 'data' => $data, 'sessionId' => 'debug-session', 'runId' => isset($_GET['runId']) ? $_GET['runId'] : 'run1', 'hypothesisId' => $hypothesisId]) . "\n";
    foreach ($debugLogPaths as $p) {
        if (!is_dir(dirname($p))) @mkdir(dirname($p), 0755, true);
        @file_put_contents($p, $line, FILE_APPEND | LOCK_EX);
    }
};
$debugPayload('detail.php:entry', 'detail API called', ['id' => $id], 'H5');
// #endregion

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
    
    // future_prediction 컬럼 존재 여부 확인 (지스트 크리티크 미래 전망)
    $hasFuturePrediction = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'future_prediction'");
        $hasFuturePrediction = $checkCol->rowCount() > 0;
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
    
    // original_title 컬럼 존재 여부 확인 (원문 영어 제목, 매체글 TTS용)
    $hasOriginalTitle = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'original_title'");
        $hasOriginalTitle = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // subtitle 컬럼 존재 여부 확인 (부제목)
    $hasSubtitle = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'subtitle'");
        $hasSubtitle = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // updated_at 컬럼 존재 여부 확인 (admin에서 업데이트한 날짜; 없을 수 있음)
    $hasUpdatedAt = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'updated_at'");
        $hasUpdatedAt = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // status 컬럼 존재 여부 (draft는 유저에게 비노출)
    $hasStatus = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'status'");
        $hasStatus = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    // 기본 컬럼 (url: extractTitleFromUrl용, source_url 없을 때 fallback)
    $columns = 'id, category, title, description, content, source, url, image_url, created_at';
    if ($hasUpdatedAt) {
        $columns .= ', updated_at';
    }
    
    // why_important 추가
    if ($hasWhyImportant) {
        $columns = str_replace('content,', 'content, why_important,', $columns);
    }
    
    // narration 추가
    if ($hasNarration) {
        $columns = str_replace('why_important,', 'why_important, narration,', $columns);
        if (!$hasWhyImportant) {
            $columns = str_replace('content,', 'content, narration,', $columns);
        }
    }
    
    // future_prediction 추가
    if ($hasFuturePrediction) {
        if ($hasNarration) {
            $columns = str_replace('narration,', 'narration, future_prediction,', $columns);
        } elseif ($hasWhyImportant) {
            $columns = str_replace('why_important,', 'why_important, future_prediction,', $columns);
        } else {
            $columns = str_replace('content,', 'content, future_prediction,', $columns);
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
    if ($hasOriginalTitle) {
        $columns .= ', original_title';
    }
    if ($hasSubtitle) {
        $columns = str_replace('title,', 'title, subtitle,', $columns);
    }
    
    // #region agent log
    $debugPayload('detail.php:columns', 'optional columns built', ['hasWhyImportant' => $hasWhyImportant, 'hasNarration' => $hasNarration, 'hasSourceUrl' => $hasSourceUrl, 'hasPublishedAt' => $hasPublishedAt, 'hasOriginalSource' => $hasOriginalSource, 'hasOriginalTitle' => $hasOriginalTitle, 'hasUpdatedAt' => $hasUpdatedAt, 'columns' => $columns], 'H1');
    // #endregion
    
    // 뉴스 조회
    // #region agent log
    $debugPayload('detail.php:beforeExecute', 'SELECT about to run', ['id' => $id, 'columns' => $columns], 'H3');
    // #endregion
    $whereClause = 'id = ?';
    $whereParams = [$id];
    if ($hasStatus) {
        $whereClause .= " AND (status = 'published' OR status IS NULL)";
    }
    $stmt = $db->prepare("SELECT $columns FROM news WHERE $whereClause");
    $stmt->execute($whereParams);
    $news = $stmt->fetch();
    // #region agent log
    $debugPayload('detail.php:afterFetch', 'SELECT result', ['hasRow' => (bool)$news, 'rowKeys' => $news ? array_keys($news) : null], 'H4');
    // #endregion
    
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

    // 다음 기사 (같은 카테고리, id < 현재, 최신순 1건)
    $nextArticle = null;
    $category = $news['category'] ?? null;
    try {
        $nextSql = $category
            ? "SELECT id, title FROM news WHERE category = ? AND id < ? ORDER BY id DESC LIMIT 1"
            : "SELECT id, title FROM news WHERE id < ? ORDER BY id DESC LIMIT 1";
        $nextStmt = $db->prepare($nextSql);
        if ($category) {
            $nextStmt->execute([$category, $news['id']]);
        } else {
            $nextStmt->execute([$news['id']]);
        }
        $nextArticle = $nextStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // 응답 데이터 구성 (published_at = 포스팅 날짜로 표시)
    $responseData = [
        'id' => (int)$news['id'],
        'category' => $news['category'] ?? null,
        'title' => $news['title'],
        'subtitle' => $hasSubtitle ? ($news['subtitle'] ?? null) : null,
        'description' => $news['description'],
        'content' => $news['content'],
        'why_important' => $hasWhyImportant ? ($news['why_important'] ?? null) : null,
        'narration' => $hasNarration ? ($news['narration'] ?? null) : null,
        'future_prediction' => $hasFuturePrediction ? ($news['future_prediction'] ?? null) : null,
        'source' => $news['source'],
        'original_source' => $hasOriginalSource ? ($news['original_source'] ?? null) : null,
        'original_title' => $hasOriginalTitle ? ($news['original_title'] ?? null) : null,
        'url' => (!empty($news['source_url'] ?? '')) ? ($news['source_url'] ?? '') : ($news['url'] ?? ''),
        'image_url' => $news['image_url'],
        'published_at' => $dateForDisplay,
        'created_at' => $news['created_at'],
        'updated_at' => $news['updated_at'] ?? null,
        'time_ago' => $timeAgo,
        'is_bookmarked' => $isBookmarked,
        'next_article' => $nextArticle ? ['id' => (int)$nextArticle['id'], 'title' => $nextArticle['title']] : null,
    ];
    api_log('news/detail', 'GET', 200);
    echo json_encode([
        'success' => true,
        'message' => '뉴스 조회 성공',
        'data' => $responseData
    ]);
    
} catch (PDOException $e) {
    // #region agent log
    if (isset($debugPayload)) {
        $debugPayload('detail.php:catch', 'PDOException', ['message' => $e->getMessage(), 'columns' => isset($columns) ? $columns : '(not built)'], 'H3');
    }
    // #endregion
    api_log('news/detail', 'GET', 500, $e->getMessage());
    http_response_code(500);
    // 클라이언트에는 상세 메시지 노출하지 않음 (스키마/테이블 정보 유출 방지)
    echo json_encode(['success' => false, 'message' => '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.']);
}
