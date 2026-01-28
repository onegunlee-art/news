<?php
/**
 * 간단한 카카오 로그인 테스트
 * 
 * 가장 기본적인 테스트부터 시작합니다.
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>카카오 로그인 기본 테스트</h1>";

// 1단계: 설정 파일 확인
echo "<h2>1단계: 설정 파일 확인</h2>";
$configPath = dirname(__DIR__, 2) . '/config/kakao.php';
echo "<p>설정 파일 경로: <code>" . htmlspecialchars($configPath) . "</code></p>";
echo "<p>파일 존재: " . (file_exists($configPath) ? "✅ YES" : "❌ NO") . "</p>";

if (file_exists($configPath)) {
    $config = require $configPath;
    echo "<p>REST API Key: <code>" . htmlspecialchars(substr($config['rest_api_key'], 0, 10)) . "...</code> (길이: " . strlen($config['rest_api_key']) . ")</p>";
    echo "<p>Redirect URI: <code>" . htmlspecialchars($config['oauth']['redirect_uri']) . "</code></p>";
    
    // 2단계: 카카오 로그인 URL 생성 테스트
    echo "<h2>2단계: 카카오 로그인 URL 생성</h2>";
    
    $params = [
        'client_id' => $config['rest_api_key'],
        'redirect_uri' => $config['oauth']['redirect_uri'],
        'response_type' => 'code',
        'scope' => implode(',', $config['oauth']['scope']),
    ];
    
    $loginUrl = $config['oauth']['authorize_url'] . '?' . http_build_query($params);
    
    echo "<p>생성된 로그인 URL:</p>";
    echo "<p><a href='" . htmlspecialchars($loginUrl) . "' target='_blank' style='word-break: break-all;'>" . htmlspecialchars($loginUrl) . "</a></p>";
    
    // 3단계: 직접 테스트
    echo "<h2>3단계: 직접 테스트</h2>";
    echo "<p><a href='" . htmlspecialchars($loginUrl) . "' style='display: inline-block; padding: 10px 20px; background: #FEE500; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold;'>카카오 로그인 테스트</a></p>";
    
    // 4단계: 라우트 확인
    echo "<h2>4단계: 라우트 확인</h2>";
    $routerPath = dirname(__DIR__, 2) . '/src/backend/Core/Router.php';
    echo "<p>Router 파일 존재: " . (file_exists($routerPath) ? "✅ YES" : "❌ NO") . "</p>";
    
    if (file_exists($routerPath)) {
        require_once $routerPath;
        
        $router = new App\Core\Router();
        $routesPath = dirname(__DIR__, 2) . '/config/routes.php';
        
        if (file_exists($routesPath)) {
            require $routesPath;
            $routes = $router->getRoutes();
            
            echo "<p>등록된 라우트 수: <strong>" . count($routes['GET'] ?? []) . "</strong></p>";
            
            if (isset($routes['GET']['/auth/kakao'])) {
                echo "<p>✅ /auth/kakao 라우트가 등록되어 있습니다!</p>";
            } else {
                echo "<p>❌ /auth/kakao 라우트가 등록되지 않았습니다.</p>";
                echo "<p>등록된 GET 라우트:</p><ul>";
                foreach ($routes['GET'] ?? [] as $path => $data) {
                    echo "<li><code>" . htmlspecialchars($path) . "</code></li>";
                }
                echo "</ul>";
            }
        }
    }
    
    // 5단계: API 엔드포인트 테스트
    echo "<h2>5단계: API 엔드포인트 테스트</h2>";
    echo "<p><a href='/api/auth/kakao' style='color: #00d9ff;'>GET /api/auth/kakao</a></p>";
    echo "<p><a href='/api/health' style='color: #00d9ff;'>GET /api/health</a></p>";
}

?>
