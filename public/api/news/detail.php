<?php
/**
 * 뉴스 상세 조회 API
 * GET: /api/news/detail.php?id=123
 *
 * 선택 컬럼(DB에 없을 수 있음): why_important, narration, source_url, published_at, original_source, updated_at
 * 새 선택 컬럼 추가 시 반드시: 1) SHOW COLUMNS 존재 여부 확인 추가 2) $columns 조합에 조건부 추가 3) 응답 data에 조건부 추가
 */

require_once __DIR__ . '/../lib/cors.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
setCorsHeaders();

// OPTIONS 요청 처리
handleOptionsRequest();

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
    
    // 스키마 캐싱: 단일 SHOW COLUMNS 쿼리로 모든 컬럼 확인 (10+회 → 1회)
    $schemaCacheFile = __DIR__ . '/../../../storage/cache/news_schema.json';
    $schemaCacheTtl = 3600; // 1시간 캐시
    $newsColumns = [];
    
    if (file_exists($schemaCacheFile) && (time() - filemtime($schemaCacheFile)) < $schemaCacheTtl) {
        $newsColumns = json_decode(file_get_contents($schemaCacheFile), true) ?: [];
    }
    
    if (empty($newsColumns)) {
        try {
            $colsStmt = $db->query("SHOW COLUMNS FROM news");
            while ($col = $colsStmt->fetch()) {
                $newsColumns[] = $col['Field'];
            }
            if (!is_dir(dirname($schemaCacheFile))) {
                @mkdir(dirname($schemaCacheFile), 0755, true);
            }
            @file_put_contents($schemaCacheFile, json_encode($newsColumns));
        } catch (Exception $e) {
            $newsColumns = [];
        }
    }
    
    $hasWhyImportant = in_array('why_important', $newsColumns, true);
    $hasNarration = in_array('narration', $newsColumns, true);
    $hasFuturePrediction = in_array('future_prediction', $newsColumns, true);
    $hasSourceUrl = in_array('source_url', $newsColumns, true);
    $hasPublishedAt = in_array('published_at', $newsColumns, true);
    $hasOriginalSource = in_array('original_source', $newsColumns, true);
    $hasOriginalTitle = in_array('original_title', $newsColumns, true);
    $hasUpdatedAt = in_array('updated_at', $newsColumns, true);
    $hasStatus = in_array('status', $newsColumns, true);
    $hasCategoryParent = in_array('category_parent', $newsColumns, true);
    $hasViewCount = in_array('view_count', $newsColumns, true);
    
    // 기본 컬럼 (url: extractTitleFromUrl용, source_url 없을 때 fallback)
    $columns = 'id, category, title, description, content, source, url, image_url, created_at';
    if ($hasCategoryParent) {
        $columns = 'id, category_parent, category, title, description, content, source, url, image_url, created_at';
    }
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
    if ($hasViewCount) {
        $columns .= ', view_count';
    }
    if ($hasOriginalSource) {
        $columns .= ', original_source';
    }
    if ($hasOriginalTitle) {
        $columns .= ', original_title';
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
    
    // 조회수 증가 (GET 1회당 1회, view_count 컬럼 있을 때만)
    if ($hasViewCount) {
        try {
            $db->prepare("UPDATE news SET view_count = view_count + 1 WHERE id = ?")->execute([$news['id']]);
        } catch (Exception $e) { /* 무시 */ }
    }
    
    // 표시용 날짜 정책: published_at 우선 (게시 시점). 없으면 created_at (docs/DATE_POLICY.md)
    $dateForDisplay = $news['published_at'] ?? $news['created_at'];
    
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
    $authUserId = null;
    $userRole = null;
    $userIsSubscribed = false;
    try {
        $authUserId = getAuthUserId($db);
        if ($authUserId !== null) {
            $uStmt = $db->prepare("SELECT role, is_subscribed FROM users WHERE id = ?");
            $uStmt->execute([$authUserId]);
            $uRow = $uStmt->fetch();
            if ($uRow) {
                $userRole = $uRow['role'];
                $userIsSubscribed = (bool)$uRow['is_subscribed'];
            }
            $chk = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND news_id = ?");
            $chk->execute([$authUserId, $news['id']]);
            $isBookmarked = (bool) $chk->fetch();
        }
    } catch (Exception $e) { /* bookmarks/users 테이블 오류 무시 */ }

    // 이전/다음 기사: from_tab(진입 탭)에 따라 해당 리스트에서의 이전·다음 기사 각 1건
    $fromTab = isset($_GET['from_tab']) ? trim((string)$_GET['from_tab']) : '';
    $prevArticle = null;
    $nextArticle = null;
    $categoryParent = $news['category_parent'] ?? $news['category'] ?? null;
    $statusCond = $hasStatus ? " AND (status = 'published' OR status IS NULL)" : "";
    // 표시용 날짜 정책: 정렬·이전/다음 모두 published_at 기준 (게시 시점). 없으면 created_at
    $pubCol = 'COALESCE(published_at, created_at)';
    $currentPub = $news['published_at'] ?? $news['created_at'] ?? null;

    try {
        if ($fromTab === 'latest') {
            // 전체 published 기사 대상 (날짜순, 4일 제한 제거)
            $nextSql = "SELECT id, title FROM news WHERE 1=1 $statusCond AND ($pubCol < ? OR ($pubCol = ? AND id < ?)) ORDER BY $pubCol DESC, id DESC LIMIT 1";
            $nextStmt = $db->prepare($nextSql);
            $nextStmt->execute([$currentPub, $currentPub, $news['id']]);
            $nextArticle = $nextStmt->fetch(PDO::FETCH_ASSOC);
            $prevSql = "SELECT id, title FROM news WHERE 1=1 $statusCond AND ($pubCol > ? OR ($pubCol = ? AND id > ?)) ORDER BY $pubCol ASC, id ASC LIMIT 1";
            $prevStmt = $db->prepare($prevSql);
            $prevStmt->execute([$currentPub, $currentPub, $news['id']]);
            $prevArticle = $prevStmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($fromTab === 'popular' && $hasViewCount) {
            // 조회수는 이미 이 요청에서 +1 반영됨. 이전/다음은 '현재 기사 기준'으로만 비교 (원래 view_count 사용)
            $currentVc = (int)($news['view_count'] ?? 0);
            $nextSql = "SELECT id, title FROM news WHERE 1=1 $statusCond AND (view_count < ? OR (view_count = ? AND id < ?)) ORDER BY view_count DESC, id DESC LIMIT 1";
            $nextStmt = $db->prepare($nextSql);
            $nextStmt->execute([$currentVc, $currentVc, $news['id']]);
            $nextArticle = $nextStmt->fetch(PDO::FETCH_ASSOC);
            $prevSql = "SELECT id, title FROM news WHERE 1=1 $statusCond AND id != ? AND (view_count > ? OR (view_count = ? AND id > ?)) ORDER BY view_count ASC, id ASC LIMIT 1";
            $prevStmt = $db->prepare($prevSql);
            $prevStmt->execute([$news['id'], $currentVc, $currentVc, $news['id']]);
            $prevArticle = $prevStmt->fetch(PDO::FETCH_ASSOC);
        } elseif (in_array($fromTab, ['diplomacy', 'economy', 'special'], true) && $hasCategoryParent) {
            $nextSql = "SELECT id, title FROM news WHERE category_parent = ? $statusCond AND ($pubCol < ? OR ($pubCol = ? AND id < ?)) ORDER BY $pubCol DESC, id DESC LIMIT 1";
            $nextStmt = $db->prepare($nextSql);
            $nextStmt->execute([$fromTab, $currentPub, $currentPub, $news['id']]);
            $nextArticle = $nextStmt->fetch(PDO::FETCH_ASSOC);
            $prevSql = "SELECT id, title FROM news WHERE category_parent = ? $statusCond AND ($pubCol > ? OR ($pubCol = ? AND id > ?)) ORDER BY $pubCol ASC, id ASC LIMIT 1";
            $prevStmt = $db->prepare($prevSql);
            $prevStmt->execute([$fromTab, $currentPub, $currentPub, $news['id']]);
            $prevArticle = $prevStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // fallback: 목록과 동일하게 pub 기준 정렬 (id 대신 published_at/created_at)
            if ($categoryParent) {
                $nextSql = "SELECT id, title FROM news WHERE category_parent = ? $statusCond AND ($pubCol < ? OR ($pubCol = ? AND id < ?)) ORDER BY $pubCol DESC, id DESC LIMIT 1";
                $nextStmt = $db->prepare($nextSql);
                $nextStmt->execute([$categoryParent, $currentPub, $currentPub, $news['id']]);
                $nextArticle = $nextStmt->fetch(PDO::FETCH_ASSOC);
                $prevSql = "SELECT id, title FROM news WHERE category_parent = ? $statusCond AND ($pubCol > ? OR ($pubCol = ? AND id > ?)) ORDER BY $pubCol ASC, id ASC LIMIT 1";
                $prevStmt = $db->prepare($prevSql);
                $prevStmt->execute([$categoryParent, $currentPub, $currentPub, $news['id']]);
                $prevArticle = $prevStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $nextSql = "SELECT id, title FROM news WHERE 1=1 $statusCond AND ($pubCol < ? OR ($pubCol = ? AND id < ?)) ORDER BY $pubCol DESC, id DESC LIMIT 1";
                $nextStmt = $db->prepare($nextSql);
                $nextStmt->execute([$currentPub, $currentPub, $news['id']]);
                $nextArticle = $nextStmt->fetch(PDO::FETCH_ASSOC);
                $prevSql = "SELECT id, title FROM news WHERE 1=1 $statusCond AND ($pubCol > ? OR ($pubCol = ? AND id > ?)) ORDER BY $pubCol ASC, id ASC LIMIT 1";
                $prevStmt = $db->prepare($prevSql);
                $prevStmt->execute([$currentPub, $currentPub, $news['id']]);
                $prevArticle = $prevStmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {}

    // category_parent: 기사 소속 상위 카테고리 (외교/경제/특집) - 상세 back 버튼 등에 사용
    $categoryParent = null;
    if ($hasCategoryParent && isset($news['category_parent'])) {
        $categoryParent = $news['category_parent'];
    } else {
        $cat = $news['category'] ?? null;
        if ($cat === 'economy') $categoryParent = 'economy';
        elseif (in_array($cat, ['entertainment', 'technology', 'special'], true)) $categoryParent = 'special';
        else $categoryParent = $cat ?: 'diplomacy'; // diplomacy 또는 기타
    }

    // 응답 데이터 구성 (display_date = created_at 기준, docs/DATE_POLICY.md)
    $responseData = [
        'id' => (int)$news['id'],
        'category' => $news['category'] ?? null,
        'category_parent' => $categoryParent,
        'title' => $news['title'],
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
        'display_date' => $dateForDisplay,
        'published_at' => $dateForDisplay,
        'created_at' => $news['created_at'],
        'updated_at' => $news['updated_at'] ?? null,
        'time_ago' => $timeAgo,
        'is_bookmarked' => $isBookmarked,
        'prev_article' => $prevArticle ? ['id' => (int)$prevArticle['id'], 'title' => $prevArticle['title']] : null,
        'next_article' => $nextArticle ? ['id' => (int)$nextArticle['id'], 'title' => $nextArticle['title']] : null,
    ];
    // 페이월: 접근 권한 판단
    $accessGranted = true;
    $restrictionType = null;
    try {
        $statusFilter = $hasStatus ? "(status = 'published' OR status IS NULL)" : "1=1";
        $latestCol = 'COALESCE(published_at, created_at)';
        $latestStmt = $db->query(
            "SELECT id FROM news WHERE $statusFilter ORDER BY $latestCol DESC LIMIT 2"
        );
        $latestIds = array_map('intval', $latestStmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {
        $latestIds = [];
    }

    if ($userRole === 'admin' || $userIsSubscribed) {
        $accessGranted = true;
    } elseif (in_array((int)$news['id'], $latestIds, true)) {
        $accessGranted = true;
    } elseif ($authUserId && ($news['category_parent'] ?? '') === 'special') {
        $accessGranted = true;
    } else {
        $accessGranted = false;
        $restrictionType = $authUserId ? 'subscription_required' : 'login_or_subscribe';
    }

    if (!$accessGranted) {
        $truncate = function($text, $len = 200) {
            if (!$text) return $text;
            $stripped = strip_tags($text);
            return mb_strlen($stripped) > $len ? mb_substr($stripped, 0, $len) . '...' : $stripped;
        };
        $responseData['description'] = $truncate($responseData['description']);
        $responseData['why_important'] = $truncate($responseData['why_important']);
        $responseData['narration'] = $truncate($responseData['narration']);
        $responseData['content'] = null;
        $responseData['future_prediction'] = null;
        $responseData['access_restricted'] = true;
        $responseData['restriction_type'] = $restrictionType;
    }

    api_log('news/detail', 'GET', 200, null, $authUserId);
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
    api_log('news/detail', 'GET', 500, $e->getMessage(), $authUserId ?? null);
    http_response_code(500);
    // 클라이언트에는 상세 메시지 노출하지 않음 (스키마/테이블 정보 유출 방지)
    echo json_encode(['success' => false, 'message' => '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.']);
}
