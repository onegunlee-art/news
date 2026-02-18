<?php
/**
 * original_source, original_title 백필 마이그레이션 (Admin API - JSON 응답)
 * GET: 백필 실행 후 결과 반환
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$projectRoot = dirname(__DIR__, 3) . '/';
if (file_exists(__DIR__ . '/../../database/migrations/backfill_original_source_title.sql')) {
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

$migrationPath = $projectRoot . 'database/migrations/backfill_original_source_title.sql';
if (!file_exists($migrationPath)) {
    echo json_encode(['success' => false, 'message' => '마이그레이션 파일을 찾을 수 없습니다.']);
    exit;
}

$fullSql = file_get_contents($migrationPath);
$fullSql = preg_replace('/--[^\n]*\n/', "\n", $fullSql);
$queries = array_filter(array_map('trim', explode(';', $fullSql)));

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $affectedTotal = 0;
    foreach ($queries as $sql) {
        if (trim($sql) === '') continue;
        $n = $pdo->exec($sql . ';');
        $affectedTotal += ($n !== false ? $n : 0);
    }
    echo json_encode([
        'success' => true,
        'message' => "original_source, original_title 백필 완료 (영향받은 행: {$affectedTotal})"
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '백필 실패: ' . $e->getMessage()]);
}
