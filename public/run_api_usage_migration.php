<?php
/**
 * api_usage_logs 테이블 마이그레이션 실행
 * 브라우저에서 /run_api_usage_migration.php 접속 또는: php run_api_usage_migration.php
 */
// 배포 시: html/database/migrations, 로컬: project/database/migrations
$projectRoot = dirname(__DIR__) . '/';
if (file_exists(__DIR__ . '/database/migrations/add_api_usage_logs.sql')) {
    $projectRoot = __DIR__ . '/';
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

// config/database.php에서 연결 정보만 추출 (options는 PDO 상수 의존으로 생략)
$cfgPath = $projectRoot . 'config/database.php';
if (file_exists($cfgPath)) {
    $content = file_get_contents($cfgPath);
    if (preg_match("/'host'\s*=>\s*(?:getenv\('DB_HOST'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['host'] = $m[1];
    if (preg_match("/'database'\s*=>\s*(?:getenv\('DB_DATABASE'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['database'] = $m[1];
    if (preg_match("/'username'\s*=>\s*(?:getenv\('DB_USERNAME'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['username'] = $m[1];
    if (preg_match("/'password'\s*=>\s*(?:getenv\('DB_PASSWORD'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['password'] = $m[1];
}

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
$sql = file_get_contents($projectRoot . 'database/migrations/add_api_usage_logs.sql');

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec($sql);
    echo "OK: api_usage_logs 테이블 생성 완료\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
