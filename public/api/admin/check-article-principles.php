<?php
/**
 * 기사 원칙 점검 API
 * GET: /api/admin/check-article-principles.php
 *
 * 원칙 1: 기사 제목(title) - 한글
 * 원칙 2: 매체 설명 기사 제목(original_title) - 영문 (URL 기사 제목 그대로)
 *
 * 반환:
 * - total: 전체 기사 수
 * - title_not_korean: title이 한글이 아닌 기사 수 (영문만 있거나 빈 값)
 * - original_title_has_korean: original_title에 한글이 포함된 기사 수
 * - original_title_missing: original_title 누락
 * - violations: 위반 기사 목록 (최대 50건)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$projectRoot = dirname(__DIR__, 3) . '/';
if (file_exists(__DIR__ . '/../../config/database.php')) {
    $projectRoot = dirname(__DIR__, 2) . '/';
}

$cfg = ['host' => 'localhost', 'database' => 'ailand', 'username' => 'ailand', 'password' => '', 'charset' => 'utf8mb4'];

if (file_exists($projectRoot . '.env')) {
    foreach (file($projectRoot . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\"'"));
        }
    }
}

if (file_exists($projectRoot . 'config/database.php')) {
    $content = file_get_contents($projectRoot . 'config/database.php');
    if (preg_match("/'host'\s*=>\s*(?:getenv\('DB_HOST'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['host'] = $m[1];
    if (preg_match("/'database'\s*=>\s*(?:getenv\('DB_DATABASE'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['database'] = $m[1];
    if (preg_match("/'username'\s*=>\s*(?:getenv\('DB_USERNAME'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['username'] = $m[1];
    if (preg_match("/'password'\s*=>\s*(?:getenv\('DB_PASSWORD'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['password'] = $m[1];
}

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";

/**
 * 한글 포함 여부 (완성형 한글: AC00-D7A3)
 */
function hasKorean(string $str): bool {
    return (bool) preg_match('/[\x{AC00}-\x{D7A3}]/u', $str);
}

/**
 * title이 한글인지 (원칙: 기사 제목은 한글)
 * 빈 값이거나 한글이 없으면 위반
 */
function isTitleKorean(?string $title): bool {
    if ($title === null || trim($title) === '') {
        return false;
    }
    return hasKorean($title);
}

/**
 * original_title이 영문인지 (원칙: 매체 설명 기사 제목은 영문)
 * 한글이 포함되면 위반
 */
function isOriginalTitleEnglish(?string $originalTitle): bool {
    if ($originalTitle === null || trim($originalTitle) === '') {
        return true; // 누락은 별도 카운트
    }
    return !hasKorean($originalTitle);
}

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $hasOriginalTitle = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM news LIKE 'original_title'");
        $hasOriginalTitle = $check->rowCount() > 0;
    } catch (Exception $e) {}

    $columns = 'id, title, url' . ($hasOriginalTitle ? ', original_title' : '');
    $stmt = $pdo->query("SELECT $columns FROM news ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($rows);
    $titleNotKorean = 0;
    $originalTitleHasKorean = 0;
    $originalTitleMissing = 0;
    $violations = [];

    foreach ($rows as $row) {
        $title = $row['title'] ?? null;
        $originalTitle = $hasOriginalTitle ? ($row['original_title'] ?? null) : null;

        $vTitle = !isTitleKorean($title);
        $vOriginalKorean = $hasOriginalTitle && !isOriginalTitleEnglish($originalTitle);
        $vMissing = $hasOriginalTitle && ($originalTitle === null || trim($originalTitle) === '');

        if ($vTitle) $titleNotKorean++;
        if ($vOriginalKorean) $originalTitleHasKorean++;
        if ($vMissing) $originalTitleMissing++;

        if ($vTitle || $vOriginalKorean || $vMissing) {
            $violations[] = [
                'id' => (int) $row['id'],
                'title' => $title,
                'original_title' => $originalTitle,
                'title_not_korean' => $vTitle,
                'original_title_has_korean' => $vOriginalKorean,
                'original_title_missing' => $vMissing,
            ];
            if (count($violations) >= 50) break;
        }
    }

    $allOk = ($titleNotKorean === 0 && $originalTitleHasKorean === 0 && $originalTitleMissing === 0);
    $message = $allOk
        ? '모든 기사가 원칙을 준수합니다.'
        : ($titleNotKorean > 0 ? "title 한글 아님: {$titleNotKorean}건. " : '')
          . ($originalTitleHasKorean > 0 ? "original_title 한글 포함: {$originalTitleHasKorean}건. " : '')
          . ($originalTitleMissing > 0 ? "original_title 누락: {$originalTitleMissing}건." : '');

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $total,
            'title_not_korean' => $titleNotKorean,
            'original_title_has_korean' => $originalTitleHasKorean,
            'original_title_missing' => $originalTitleMissing,
            'violations' => $violations,
            'message' => trim($message),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '점검 실패: ' . $e->getMessage()]);
}
