<?php
/**
 * original_title 누락 기사 점검 API
 * GET: /api/admin/check-original-title.php
 * - total: 전체 기사 수
 * - with_original_title: original_title 있는 기사 수
 * - missing: original_title 없거나 빈 기사 수
 * - missing_ids: 누락 기사 ID 목록 (최대 50건)
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

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $hasOriginalTitle = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM news LIKE 'original_title'");
        $hasOriginalTitle = $check->rowCount() > 0;
    } catch (Exception $e) {}

    if (!$hasOriginalTitle) {
        echo json_encode([
            'success' => true,
            'data' => [
                'total' => 0,
                'with_original_title' => 0,
                'missing' => 0,
                'missing_ids' => [],
                'message' => 'original_title 컬럼이 없습니다.',
            ],
        ]);
        exit;
    }

    $total = (int) $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
    $withTitle = (int) $pdo->query("SELECT COUNT(*) FROM news WHERE original_title IS NOT NULL AND TRIM(original_title) != ''")->fetchColumn();
    $missing = $total - $withTitle;

    $missingIds = [];
    if ($missing > 0) {
        $stmt = $pdo->query("
            SELECT id, title, url, source_url
            FROM news
            WHERE original_title IS NULL OR TRIM(original_title) = ''
            ORDER BY id ASC
            LIMIT 50
        ");
        $missingIds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $total,
            'with_original_title' => $withTitle,
            'missing' => $missing,
            'missing_ids' => $missingIds,
            'message' => $missing > 0
                ? "original_title 누락: {$missing}건 (총 {$total}건 중). 백필 실행 권장."
                : "모든 기사에 original_title이 있습니다.",
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '점검 실패: ' . $e->getMessage()]);
}
