<?php
/**
 * Claude API 연동 테스트 엔드포인트
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 프로젝트 루트 자동 탐지 (로컬/서버 모두 지원)
// 서버 배포 구조: /html/ 아래에 src/agents 포함
function findProjectRoot(): string {
    $candidates = [
        __DIR__ . '/../',           // 로컬 (public/)
        __DIR__ . '/',              // 서버 (html/ = 루트)
        __DIR__ . '/../../',        
        dirname(__DIR__),
        dirname(__DIR__, 2),
    ];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    // 서버 배포 구조: __DIR__ 자체가 루트 (html/)
    if (file_exists(__DIR__ . '/src/agents/autoload.php')) {
        return __DIR__ . '/';
    }
    return dirname(__DIR__) . '/';
}

$projectRoot = findProjectRoot();

// Agent autoload
$autoloadPath = $projectRoot . 'src/agents/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success' => false,
        'error' => 'autoload.php not found',
        'tried' => $autoloadPath,
        'project_root' => $projectRoot,
        '__DIR__' => __DIR__,
        'check_html_src' => file_exists(__DIR__ . '/src/agents/autoload.php') ? 'EXISTS' : 'NOT_FOUND',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
require_once $autoloadPath;

// .env 로드
function loadEnvFile(string $path): bool {
    if (!is_file($path) || !is_readable($path)) return false;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

$envFiles = [
    $projectRoot . 'env.txt',
    $projectRoot . '.env',
];
foreach ($envFiles as $f) {
    if (loadEnvFile($f)) break;
}

use Agents\Services\ClaudeService;

try {
    $claude = new ClaudeService();
    $result = $claude->testConnection();
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
