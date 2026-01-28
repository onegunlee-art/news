<?php
/**
 * 라우트 디버그 페이지
 * 
 * 요청 경로와 등록된 라우트를 비교합니다.
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

// 현재 요청 정보
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parsedUri = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . trim($parsedUri ?? '/', '/');

// API prefix 제거 (Router와 동일한 로직)
$testPath = $path;
if (str_starts_with($testPath, '/api')) {
    $testPath = substr($testPath, 4) ?: '/';
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>라우트 디버그</title>
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
        .route-item {
            padding: 10px;
            margin: 5px 0;
            background: rgba(255,255,255,0.05);
            border-left: 3px solid #00d9ff;
        }
        .match { border-left-color: #22c55e; }
        .no-match { border-left-color: #ef4444; }
        .test-path {
            background: rgba(0, 217, 255, 0.2);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 라우트 디버그 정보</h1>
    
    <div class="info-box">
        <h3>현재 요청 정보</h3>
        <p><strong>원본 URI:</strong> <?= htmlspecialchars($requestUri) ?></p>
        <p><strong>파싱된 경로:</strong> <?= htmlspecialchars($path) ?></p>
        <p><strong>테스트 경로 (API prefix 제거 후):</strong> <span class="test-path"><?= htmlspecialchars($testPath) ?></span></p>
        <p><strong>HTTP 메서드:</strong> <?= htmlspecialchars($requestMethod) ?></p>
    </div>
    
    <h2>등록된 라우트 목록</h2>
    
    <?php if (empty($routes)): ?>
        <p style="color: #ef4444;">⚠️ 등록된 라우트가 없습니다!</p>
    <?php else: ?>
        <?php 
        $foundMatch = false;
        foreach ($routes as $method => $methodRoutes): 
            if ($method !== $requestMethod) continue;
        ?>
            <h3><?= htmlspecialchars($method) ?> 메서드</h3>
            <?php foreach ($methodRoutes as $routePath => $routeData): 
                $matches = false;
                // 간단한 매칭 테스트
                if ($routePath === $testPath) {
                    $matches = true;
                    $foundMatch = true;
                }
            ?>
                <div class="route-item <?= $matches ? 'match' : 'no-match' ?>">
                    <span class="method <?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></span>
                    <strong><?= htmlspecialchars($routePath) ?></strong>
                    <?php if ($matches): ?>
                        <span style="color: #22c55e; margin-left: 10px;">✓ 매칭됨!</span>
                    <?php endif; ?>
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
        <?php endforeach; ?>
        
        <?php if (!$foundMatch): ?>
            <div class="info-box" style="border-left-color: #ef4444;">
                <p style="color: #ef4444;">⚠️ 매칭되는 라우트가 없습니다!</p>
                <p>요청 경로: <strong><?= htmlspecialchars($testPath) ?></strong></p>
                <p>HTTP 메서드: <strong><?= htmlspecialchars($requestMethod) ?></strong></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <h2>테스트할 엔드포인트</h2>
    <ul>
        <li><a href="/api/health" style="color: #00d9ff;">GET /api/health</a></li>
        <li><a href="/api/auth/kakao" style="color: #00d9ff;">GET /api/auth/kakao</a></li>
        <li><a href="/api/auth/me" style="color: #00d9ff;">GET /api/auth/me</a></li>
        <li><a href="/api/news" style="color: #00d9ff;">GET /api/news</a></li>
    </ul>
</body>
</html>
