<?php
/**
 * series_covers 테이블 마이그레이션 (1회 실행)
 * GET /api/admin/run-series-covers-migration.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$projectRoot = dirname(__DIR__, 3) . '/';
if (!is_file($projectRoot . 'database/migrations/add_series_covers.sql')) {
    $projectRoot = dirname(__DIR__, 2) . '/';
}

require_once __DIR__ . '/../lib/admin_auth.php';

$cfg = ['host' => 'localhost', 'database' => 'ailand', 'username' => 'ailand', 'password' => '', 'charset' => 'utf8mb4'];

if (is_file($projectRoot . '.env')) {
    foreach (file($projectRoot . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || ($line[0] ?? '') === '#') {
            continue;
        }
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

if (is_file($projectRoot . 'config/database.php')) {
    $dbCfg = require $projectRoot . 'config/database.php';
    if (is_array($dbCfg)) {
        $cfg['host'] = $dbCfg['host'] ?? $cfg['host'];
        $cfg['database'] = $dbCfg['database'] ?? $dbCfg['dbname'] ?? $cfg['database'];
        $cfg['username'] = $dbCfg['username'] ?? $cfg['username'];
        $cfg['password'] = $dbCfg['password'] ?? $cfg['password'];
    }
}

$steps = [];

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}",
        $cfg['username'],
        $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    requireAdminApi($pdo);

    $hasTable = (bool) $pdo->query("SHOW TABLES LIKE 'series_covers'")->fetch(PDO::FETCH_NUM);
    if (!$hasTable) {
        $sqlFile = $projectRoot . 'database/migrations/add_series_covers.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException('add_series_covers.sql not found');
        }
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        $steps[] = 'series_covers table created';
    } else {
        $steps[] = 'series_covers already exists';
    }

    echo json_encode([
        'success' => true,
        'message' => 'series_covers 마이그레이션 완료',
        'steps' => $steps,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '마이그레이션 실패: ' . $e->getMessage(),
        'steps' => $steps,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '마이그레이션 실패: ' . $e->getMessage(),
        'steps' => $steps,
    ], JSON_UNESCAPED_UNICODE);
}
