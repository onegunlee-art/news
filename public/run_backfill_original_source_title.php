<?php
/**
 * original_source, original_title 백필 마이그레이션
 * 브라우저에서 /run_backfill_original_source_title.php 접속 또는: php run_backfill_original_source_title.php
 */
$projectRoot = dirname(__DIR__) . '/';
if (file_exists(__DIR__ . '/../database/migrations/backfill_original_source_title.sql')) {
    $projectRoot = __DIR__ . '/../';
}

// .env 로드
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

$cfg = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'ailand',
    'username' => getenv('DB_USERNAME') ?: 'ailand',
    'password' => getenv('DB_PASSWORD') ?: 'romi4120!',
    'charset' => 'utf8mb4'
];

$cfgPath = $projectRoot . 'config/database.php';
if (file_exists($cfgPath)) {
    $content = file_get_contents($cfgPath);
    if (preg_match("/'host'\s*=>\s*(?:getenv\('DB_HOST'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['host'] = $m[1];
    if (preg_match("/'database'\s*=>\s*(?:getenv\('DB_DATABASE'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['database'] = $m[1];
    if (preg_match("/'username'\s*=>\s*(?:getenv\('DB_USERNAME'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['username'] = $m[1];
    if (preg_match("/'password'\s*=>\s*(?:getenv\('DB_PASSWORD'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['password'] = $m[1];
}

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";

// SQL 파일은 세미콜론으로 구분된 여러 문 - exec로 한 번에 안 될 수 있음
$sqlFile = $projectRoot . 'database/migrations/backfill_original_source_title.sql';
if (!file_exists($sqlFile)) {
    echo "ERROR: Migration file not found: $sqlFile\n";
    exit(1);
}

$fullSql = file_get_contents($sqlFile);
// -- 로 시작하는 줄 제거
$fullSql = preg_replace('/--[^\n]*\n/', "\n", $fullSql);
$queries = array_filter(array_map('trim', explode(';', $fullSql)));

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $affectedTotal = 0;
    foreach ($queries as $sql) {
        if (trim($sql) === '') continue;
        $n = $pdo->exec($sql . ';');
        $affectedTotal += ($n !== false ? $n : 0);
    }
    echo "OK: original_source, original_title backfill done (rows affected: $affectedTotal)\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
