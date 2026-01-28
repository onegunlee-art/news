<?php
/**
 * 라우트 테스트 페이지
 * 
 * 등록된 라우트를 확인합니다.
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
        }
        h1 { color: #00d9ff; }
        .method {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 10px;
            min-width: 60px;
            text-align: center;
        }
        .GET { background: #22c55e; color: #000; }
        .POST { background: #3b82f6; color: #fff; }
        .PUT { background: #f59e0b; color: #000; }
        .DELETE { background: #ef4444; color: #fff; }
        .route-item {
            padding: 10px;
            margin: 5px 0;
            background: rgba(255,255,255,0.05);
            border-left: 3px solid #00d9ff;
        }
        .section {
            margin: 30px 0;
        }
        .section h2 {
            color: #00d9ff;
            border-bottom: 2px solid #00d9ff;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>🔍 등록된 라우트 목록</h1>
    
    <?php if (empty($routes)): ?>
        <p style="color: #ef4444;">⚠️ 등록된 라우트가 없습니다!</p>
    <?php else: ?>
        <?php foreach ($routes as $method => $methodRoutes): ?>
            <div class="section">
                <h2><?= htmlspecialchars($method) ?> 메서드</h2>
                <?php foreach ($methodRoutes as $path => $routeData): ?>
                    <div class="route-item">
                        <span class="method <?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></span>
                        <strong><?= htmlspecialchars($path) ?></strong>
                        <br>
                        <small style="color: #8b8b9a;">
                            Handler: <?php
                            if (is_array($routeData['handler'])) {
                                echo htmlspecialchars($routeData['handler'][0] . '::' . $routeData['handler'][1]);
                            } elseif ($routeData['handler'] instanceof Closure) {
                                echo 'Closure';
                            } else {
                                echo htmlspecialchars(gettype($routeData['handler']));
                            }
                            ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="section">
        <h2>📋 테스트할 라우트</h2>
        <ul>
            <li><a href="/api/health" style="color: #00d9ff;">GET /api/health</a></li>
            <li><a href="/api/auth/kakao" style="color: #00d9ff;">GET /api/auth/kakao</a></li>
            <li><a href="/api/auth/me" style="color: #00d9ff;">GET /api/auth/me</a></li>
            <li><a href="/api/news" style="color: #00d9ff;">GET /api/news</a></li>
        </ul>
    </div>
</body>
</html>
