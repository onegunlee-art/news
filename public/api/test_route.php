<?php
/**
 * 라우트 테스트 페이지
 * 
 * 실제 라우트 매칭을 테스트합니다.
 */

header('Content-Type: text/html; charset=utf-8');

// Autoloader 설정
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

// 라우트 파일 로드
$routesPath = dirname(__DIR__, 2) . '/config/routes.php';
if (file_exists($routesPath)) {
    require $routesPath;
} else {
    die('Routes file not found: ' . $routesPath);
}

$routes = $router->getRoutes();

// 테스트할 경로들
$testPaths = [
    '/health',
    '/auth/kakao',
    '/auth/kakao/callback',
    '/auth/me',
    '/news',
];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>라우트 테스트</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a2e;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        h1 { color: #00d9ff; }
        h2 { color: #00d9ff; margin-top: 30px; border-bottom: 2px solid #00d9ff; padding-bottom: 10px; }
        .info-box {
            background: rgba(255,255,255,0.05);
            border-left: 3px solid #00d9ff;
            padding: 15px;
            margin: 15px 0;
        }
        .success { border-left-color: #22c55e; }
        .error { border-left-color: #ef4444; }
        .route-item {
            padding: 10px;
            margin: 5px 0;
            background: rgba(255,255,255,0.05);
            border-left: 3px solid #00d9ff;
        }
        pre {
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 라우트 테스트</h1>
    
    <div class="info-box">
        <h3>등록된 라우트 수</h3>
        <?php
        $totalRoutes = 0;
        foreach ($routes as $method => $methodRoutes) {
            $totalRoutes += count($methodRoutes);
        }
        echo "<p>총 <strong>{$totalRoutes}</strong>개의 라우트가 등록되어 있습니다.</p>";
        ?>
    </div>
    
    <h2>등록된 라우트 목록</h2>
    <pre><?php
    foreach ($routes as $method => $methodRoutes) {
        echo "\n=== {$method} ===\n";
        foreach ($methodRoutes as $path => $routeData) {
            $handler = $routeData['handler'];
            if (is_array($handler)) {
                $handlerStr = $handler[0] . '::' . $handler[1];
            } elseif ($handler instanceof Closure) {
                $handlerStr = 'Closure';
            } else {
                $handlerStr = gettype($handler);
            }
            echo "  {$path} -> {$handlerStr}\n";
        }
    }
    ?></pre>
    
    <h2>경로 매칭 테스트</h2>
    <?php
    foreach ($testPaths as $testPath) {
        $matched = false;
        $matchedRoute = null;
        
        // GET 메서드로 테스트
        if (isset($routes['GET'])) {
            foreach ($routes['GET'] as $routePath => $routeData) {
                // 정확한 매칭 먼저 시도
                if ($routePath === $testPath) {
                    $matched = true;
                    $matchedRoute = $routePath;
                    break;
                }
                
                // 정규표현식 매칭 시도
                $regex = preg_replace_callback(
                    '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                    fn($matches) => '(?P<' . $matches[1] . '>[^/]+)',
                    $routePath
                );
                $regex = '#^' . $regex . '$#';
                
                if (preg_match($regex, $testPath)) {
                    $matched = true;
                    $matchedRoute = $routePath;
                    break;
                }
            }
        }
        
        $boxClass = $matched ? 'success' : 'error';
        $status = $matched ? '✓ 매칭됨' : '✗ 매칭 실패';
        $statusColor = $matched ? '#22c55e' : '#ef4444';
    ?>
        <div class="info-box <?= $boxClass ?>">
            <p><strong style="color: <?= $statusColor ?>"><?= $status ?></strong></p>
            <p>테스트 경로: <code><?= htmlspecialchars($testPath) ?></code></p>
            <?php if ($matched): ?>
                <p>매칭된 라우트: <code><?= htmlspecialchars($matchedRoute) ?></code></p>
            <?php else: ?>
                <p style="color: #ef4444;">매칭되는 라우트가 없습니다.</p>
            <?php endif; ?>
        </div>
    <?php
    }
    ?>
    
    <h2>실제 API 테스트</h2>
    <ul>
        <li><a href="/api/health" style="color: #00d9ff;">GET /api/health</a></li>
        <li><a href="/api/auth/kakao" style="color: #00d9ff;">GET /api/auth/kakao</a></li>
        <li><a href="/api/auth/me" style="color: #00d9ff;">GET /api/auth/me</a></li>
        <li><a href="/api/news" style="color: #00d9ff;">GET /api/news</a></li>
    </ul>
</body>
</html>
