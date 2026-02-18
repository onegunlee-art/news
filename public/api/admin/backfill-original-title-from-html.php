<?php
/**
 * 원문 URL HTML에서 <title> 추출하여 original_title 백필
 * GET: ?dry_run=1 (실행 없이 대상만 확인)
 * GET: ?limit=N (처리 개수 제한)
 * 모든 기사에 적용.
 */
set_time_limit(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$projectRoot = dirname(__DIR__, 3) . '/';
if (file_exists(__DIR__ . '/../../config/database.php')) {
    $projectRoot = dirname(__DIR__, 2) . '/';
}

require __DIR__ . '/../lib/extractTitleFromHtml.php';
require __DIR__ . '/../lib/extractTitleFromUrl.php';

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

$cfg['host'] = getenv('DB_HOST') ?: $cfg['host'];
$cfg['database'] = getenv('DB_DATABASE') ?: $cfg['database'];
$cfg['username'] = getenv('DB_USERNAME') ?: $cfg['username'];
$cfg['password'] = getenv('DB_PASSWORD') ?: $cfg['password'];

if (file_exists($projectRoot . 'config/database.php')) {
    $content = file_get_contents($projectRoot . 'config/database.php');
    if (preg_match("/'host'\s*=>\s*(?:getenv\('DB_HOST'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['host'] = $m[1];
    if (preg_match("/'database'\s*=>\s*(?:getenv\('DB_DATABASE'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['database'] = $m[1];
    if (preg_match("/'username'\s*=>\s*(?:getenv\('DB_USERNAME'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['username'] = $m[1];
    if (preg_match("/'password'\s*=>\s*(?:getenv\('DB_PASSWORD'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['password'] = $m[1];
}

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] !== '0';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
$delayMs = isset($_GET['delay']) ? (int)$_GET['delay'] : 1500;

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // original_title 컬럼 존재 확인
    $hasOriginalTitle = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM news LIKE 'original_title'");
        $hasOriginalTitle = $check->rowCount() > 0;
    } catch (Exception $e) {}
    if (!$hasOriginalTitle) {
        echo json_encode(['success' => false, 'message' => 'original_title 컬럼이 없습니다.']);
        exit;
    }

    // source_url 컬럼 존재 확인
    $hasSourceUrl = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM news LIKE 'source_url'");
        $hasSourceUrl = $check->rowCount() > 0;
    } catch (Exception $e) {}

    $columns = 'id, title, url' . ($hasSourceUrl ? ', source_url' : '');
    $sql = "SELECT $columns FROM news ORDER BY id ASC";
    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $skipped = 0;
    $failed = 0;
    $details = [];

    foreach ($rows as $row) {
        $articleUrl = ($hasSourceUrl && !empty(trim($row['source_url'] ?? ''))) ? $row['source_url'] : ($row['url'] ?? '');
        if (empty(trim($articleUrl ?? '')) || $articleUrl === '#') {
            $skipped++;
            $details[] = ['id' => $row['id'], 'status' => 'skip', 'reason' => 'no_url'];
            continue;
        }

        $titleFromHtml = extractTitleFromHtml($articleUrl);

        if ($titleFromHtml !== null && trim($titleFromHtml) !== '') {
            $finalTitle = trim($titleFromHtml);
        } else {
            $titleFromUrl = extractTitleFromUrl($articleUrl);
            if ($titleFromUrl !== null && trim($titleFromUrl) !== '') {
                $finalTitle = trim($titleFromUrl);
            } else {
                $failed++;
                $details[] = ['id' => $row['id'], 'status' => 'fail', 'url' => $articleUrl];
                if (!$dryRun) {
                    usleep($delayMs * 1000);
                }
                continue;
            }
        }

        if (!$dryRun) {
            $upd = $pdo->prepare('UPDATE news SET original_title = ? WHERE id = ?');
            $upd->execute([$finalTitle, $row['id']]);
            if ($upd->rowCount() > 0) {
                $updated++;
            }
        } else {
            $updated++;
        }
        $details[] = ['id' => $row['id'], 'status' => 'ok', 'original_title' => $finalTitle];

        if (!$dryRun && $delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    echo json_encode([
        'success' => true,
        'dry_run' => $dryRun,
        'total' => count($rows),
        'updated' => $updated,
        'skipped' => $skipped,
        'failed' => $failed,
        'message' => $dryRun
            ? "대상 확인 완료. 실제 실행 시 {$updated}건 업데이트 예정."
            : "original_title 백필 완료. 업데이트: {$updated}, 스킵: {$skipped}, 실패: {$failed}",
        'details' => array_slice($details, 0, 20),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
