<?php
/**
 * API 전용 진입점
 * 
 * api/* 요청은 index.php 대신 이 파일로 리라이트되어,
 * REQUEST_URI가 바뀌어도 항상 JSON을 반환합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

// 에러 리포팅 설정
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization, X-Requested-With');

// Preflight 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Apache/CGI에서 Authorization 헤더가 제거되는 경우 대비
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_X_AUTHORIZATION'];
    }
}

// 프로젝트 루트
$projectRoot = file_exists(__DIR__ . '/config/routes.php') ? __DIR__ : dirname(__DIR__);

// .env 로드
$envFile = $projectRoot . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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
}

// Autoloader 설정
spl_autoload_register(function (string $class) use ($projectRoot): void {
    $prefix = 'App\\';
    $baseDir = $projectRoot . '/src/backend/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// 요청 URI: __path 쿼리 파라미터로 전달 (리라이트 시 항상 설정됨)
$requestUri = $_GET['__path'] ?? $_SERVER['REQUEST_URI'] ?? '/';
$requestUri = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$requestUri = '/' . trim((string) $requestUri, '/') ?: '/';
$requestMethod = $_SERVER['REQUEST_METHOD'];

// __path 없이 직접 호출된 경우 (리라이트 실패)
if (!isset($_GET['__path']) && !str_starts_with($requestUri, '/api/')) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Bad Request',
        'message' => 'API는 /api/* 경로로만 접근 가능합니다. __path가 필요합니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// API 전용: 항상 JSON 응답
header('Content-Type: application/json; charset=UTF-8');

try {
    $routerPath = $projectRoot . '/src/backend/Core/Router.php';
    if (file_exists($routerPath)) {
        require_once $routerPath;
        $router = new App\Core\Router();
        $routesPath = $projectRoot . '/config/routes.php';
        if (file_exists($routesPath)) require $routesPath;
        $router->dispatch($requestMethod, $requestUri);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'News 맥락 분석 API',
            'version' => '1.0.0',
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
} catch (Throwable $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();
    // 로그 기록 (배포 서버 디버깅용)
    $logDir = ($projectRoot ?? __DIR__) . '/storage/logs';
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        @file_put_contents($logDir . '/api_error.log', date('Y-m-d H:i:s') . " $msg in $file:$line\n", FILE_APPEND | LOCK_EX);
    }
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => $msg,
        'file' => basename($file),
        'line' => $line,
    ], JSON_UNESCAPED_UNICODE);
}
