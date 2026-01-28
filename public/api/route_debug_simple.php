<?php
/**
 * 라우트 디버그 - 간단 버전
 * 
 * /health는 작동하는데 /auth/kakao가 작동하지 않는 이유를 찾습니다.
 */

header('Content-Type: text/html; charset=utf-8');

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__, 2) . '/src/backend/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require_once dirname(__DIR__, 2) . '/src/backend/Core/Router.php';
require_once dirname(__DIR__, 2) . '/src/backend/Core/Request.php';
require_once dirname(__DIR__, 2) . '/src/backend/Core/Response.php';

$router = new App\Core\Router();
$routesPath = dirname(__DIR__, 2) . '/config/routes.php';

if (file_exists($routesPath)) {
    require $routesPath;
} else {
    die('Routes file not found');
}

$routes = $router->getRoutes();

echo "<h1>라우트 디버그</h1>";

echo "<h2>등록된 GET 라우트</h2>";
echo "<pre>";
foreach ($routes['GET'] ?? [] as $path => $data) {
    $handler = $data['handler'];
    if (is_array($handler)) {
        $handlerStr = $handler[0] . '::' . $handler[1];
    } else {
        $handlerStr = gettype($handler);
    }
    echo sprintf("%-30s -> %s\n", $path, $handlerStr);
}
echo "</pre>";

// 경로 매칭 테스트
echo "<h2>경로 매칭 테스트</h2>";

$testPaths = [
    '/health',
    '/auth/kakao',
    '/auth/kakao/callback',
];

foreach ($testPaths as $testPath) {
    echo "<h3>테스트 경로: <code>{$testPath}</code></h3>";
    
    if (isset($routes['GET'][$testPath])) {
        echo "<p style='color: green;'>✅ 정확히 일치하는 라우트 발견!</p>";
    } else {
        echo "<p style='color: red;'>❌ 정확히 일치하는 라우트 없음</p>";
        
        // 유사한 라우트 찾기
        echo "<p>유사한 라우트:</p><ul>";
        foreach ($routes['GET'] ?? [] as $routePath => $data) {
            if (str_contains($routePath, 'kakao') || str_contains($routePath, 'health')) {
                echo "<li><code>{$routePath}</code></li>";
            }
        }
        echo "</ul>";
    }
    
    // Router의 matchPath 메서드로 테스트
    $reflection = new ReflectionClass($router);
    $matchPathMethod = $reflection->getMethod('matchPath');
    $matchPathMethod->setAccessible(true);
    
    $matched = false;
    foreach ($routes['GET'] ?? [] as $routePath => $data) {
        $result = $matchPathMethod->invoke($router, $routePath, $testPath);
        if ($result !== null) {
            echo "<p style='color: green;'>✅ matchPath로 매칭됨: <code>{$routePath}</code></p>";
            $matched = true;
            break;
        }
    }
    
    if (!$matched) {
        echo "<p style='color: red;'>❌ matchPath로도 매칭 실패</p>";
    }
    
    echo "<hr>";
}
