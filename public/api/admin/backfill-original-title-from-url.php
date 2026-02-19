<?php
/**
 * URL 슬러그에서 original_title 추출하여 백필
 * extractTitleFromUrl 사용 (네트워크 없이 즉시 처리)
 * GET: ?dry_run=1 (실행 없이 대상만 확인), ?limit=N (처리 개수 제한)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$projectRoot = dirname(__DIR__, 3) . '/';
if (file_exists(__DIR__ . '/../../config/database.php')) {
    $projectRoot = dirname(__DIR__, 2) . '/';
}

require __DIR__ . '/../lib/extractTitleFromUrl.php';
require __DIR__ . '/../lib/invalidateTtsCache.php';

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

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] !== '0';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $hasOriginalTitle = false;
    $hasSourceUrl = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM news LIKE 'original_title'");
        $hasOriginalTitle = $check->rowCount() > 0;
        $check = $pdo->query("SHOW COLUMNS FROM news LIKE 'source_url'");
        $hasSourceUrl = $check->rowCount() > 0;
    } catch (Exception $e) {}

    if (!$hasOriginalTitle) {
        echo json_encode(['success' => false, 'message' => 'original_title 컬럼이 없습니다.']);
        exit;
    }

    $sql = "SELECT id, url" . ($hasSourceUrl ? ', source_url' : '') . " FROM news WHERE (original_title IS NULL OR TRIM(original_title) = '') ORDER BY id ASC";
    if ($limit > 0) $sql .= " LIMIT " . (int)$limit;
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        $url = ($hasSourceUrl && !empty(trim($row['source_url'] ?? ''))) ? $row['source_url'] : ($row['url'] ?? '');
        if (empty(trim($url ?? '')) || $url === '#') {
            $skipped++;
            continue;
        }

        $titleFromUrl = extractTitleFromUrl($url);
        if ($titleFromUrl === null || trim($titleFromUrl) === '') {
            $skipped++;
            continue;
        }

        if (!$dryRun) {
            $upd = $pdo->prepare('UPDATE news SET original_title = ? WHERE id = ?');
            $upd->execute([trim($titleFromUrl), $row['id']]);
            if ($upd->rowCount() > 0) {
                $updated++;
                invalidateTtsCacheForNews((int) $row['id']);
            }
        } else {
            $updated++;
        }
    }

    echo json_encode([
        'success' => true,
        'dry_run' => $dryRun,
        'total' => count($rows),
        'updated' => $updated,
        'skipped' => $skipped,
        'message' => $dryRun
            ? "대상 확인 완료. URL 슬러그로 {$updated}건 업데이트 예정."
            : "original_title URL 백필 완료. 업데이트: {$updated}, 스킵: {$skipped}",
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
