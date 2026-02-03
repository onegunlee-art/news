<?php
/**
 * News 맥락 분석 - 메인 진입점
 * 
 * 모든 API 요청과 프론트엔드 라우팅을 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

// 에러 리포팅 설정 (프로덕션에서는 비활성화)
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

// Apache/CGI에서 Authorization 헤더가 제거되는 경우 대비 (RewriteRule E= / X-Authorization 폴백)
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_X_AUTHORIZATION'];
    }
}

// 프로젝트 루트 (배포 시 __DIR__에 config·src가 있음, 로컬은 상위)
$projectRoot = file_exists(__DIR__ . '/config/routes.php') ? __DIR__ : dirname(__DIR__);

// Autoloader 설정
spl_autoload_register(function (string $class) use ($projectRoot): void {
    $prefix = 'App\\';
    $baseDir = $projectRoot . '/src/backend/';
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

// 설정 파일 로드
$configPath = $projectRoot . '/config/app.php';
$config = file_exists($configPath) ? require $configPath : [];

// 요청 URI 파싱
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestUri = parse_url($requestUri, PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// API 요청인지 확인
$isApiRequest = str_starts_with($requestUri, '/api/');

if ($isApiRequest) {
    // API 라우팅
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $routerPath = $projectRoot . '/src/backend/Core/Router.php';
        
        if (file_exists($routerPath)) {
            require_once $routerPath;
            
            $router = new App\Core\Router();
            
            $routesPath = $projectRoot . '/config/routes.php';
            if (file_exists($routesPath)) {
                require $routesPath;
            }
            
            // 요청 처리
            $router->dispatch($requestMethod, $requestUri);
        } else {
            // Router가 없으면 기본 API 응답
            echo json_encode([
                'success' => true,
                'message' => 'News 맥락 분석 API',
                'version' => '1.0.0',
                'timestamp' => date('c'),
                'endpoints' => [
                    'GET /api/health' => '서버 상태 확인',
                    'GET /api/news' => '뉴스 목록 조회',
                    'GET /api/news/{id}' => '뉴스 상세 조회',
                    'POST /api/news/{id}/analyze' => '뉴스 분석 요청',
                    'GET /api/auth/kakao' => '카카오 로그인',
                    'GET /api/auth/kakao/callback' => '카카오 콜백',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    // 프론트엔드 라우팅 (SPA)
    $indexHtml = __DIR__ . '/index.html';
    
    if (file_exists($indexHtml)) {
        // React 빌드된 index.html 제공
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexHtml);
    } else {
        // 개발 중 임시 페이지
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News 맥락 분석</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00d9ff;
            --primary-dark: #00a8cc;
            --bg-dark: #0a0a0f;
            --bg-card: #12121a;
            --text-primary: #ffffff;
            --text-secondary: #8b8b9a;
            --accent: #ff6b6b;
            --success: #00d26a;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #00d9ff 0%, #00a8cc 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* 배경 애니메이션 */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: 
                radial-gradient(ellipse at 20% 80%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(0, 217, 255, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 40% 40%, rgba(118, 75, 162, 0.1) 0%, transparent 40%);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        /* 헤더 */
        header {
            padding: 1.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }
        
        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .login-btn {
            background: var(--gradient-2);
            color: var(--bg-dark);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 217, 255, 0.3);
        }
        
        /* 히어로 섹션 */
        .hero {
            padding: 8rem 0 6rem;
            text-align: center;
        }
        
        .hero h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        
        .hero h1 span {
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 3rem;
            line-height: 1.8;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: var(--gradient-2);
            color: var(--bg-dark);
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 217, 255, 0.4);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--text-primary);
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: border-color 0.3s, background 0.3s;
        }
        
        .btn-secondary:hover {
            border-color: var(--primary);
            background: rgba(0, 217, 255, 0.1);
        }
        
        /* 기능 섹션 */
        .features {
            padding: 6rem 0;
        }
        
        .features h2 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 4rem;
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .feature-icon.keywords {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .feature-icon.sentiment {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .feature-icon.summary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .feature-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .feature-card p {
            color: var(--text-secondary);
            line-height: 1.7;
        }
        
        /* 상태 배너 */
        .status-banner {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 2rem;
            margin: 4rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            border: 1px solid rgba(0, 210, 106, 0.3);
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .status-text {
            color: var(--success);
            font-weight: 500;
        }
        
        /* 푸터 */
        footer {
            padding: 3rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
            color: var(--text-secondary);
        }
        
        /* 반응형 */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero {
                padding: 4rem 0 3rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <header>
        <div class="container">
            <nav>
                <div class="logo">Infer</div>
                <ul class="nav-links">
                    <li><a href="#">뉴스</a></li>
                    <li><a href="#">분석</a></li>
                    <li><a href="#">트렌드</a></li>
                    <li><a href="#">API</a></li>
                </ul>
                <button class="login-btn" onclick="location.href='/api/auth/kakao'">
                    카카오 로그인
                </button>
            </nav>
        </div>
    </header>
    
    <main>
        <section class="hero">
            <div class="container">
                <h1>뉴스를 더 깊이<br><span>맥락으로 이해하다</span></h1>
                <p>
                    AI 기반 뉴스 분석 서비스로 키워드 추출, 감정 분석, 
                    맥락 요약을 통해 뉴스의 본질을 파악하세요.
                </p>
                <div class="cta-buttons">
                    <a href="/api" class="btn-primary">
                        🚀 시작하기
                    </a>
                    <a href="/test_connection.php" class="btn-secondary">
                        서버 상태 확인
                    </a>
                </div>
            </div>
        </section>
        
        <section class="features">
            <div class="container">
                <h2>핵심 기능</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon keywords">🔑</div>
                        <h3>키워드 추출</h3>
                        <p>
                            형태소 분석 기반 한국어 NLP로 뉴스 기사에서 
                            핵심 키워드와 주제를 자동으로 추출합니다.
                        </p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon sentiment">💭</div>
                        <h3>감정 분석</h3>
                        <p>
                            기사의 논조를 긍정, 부정, 중립으로 분류하여 
                            뉴스의 감정적 맥락을 파악할 수 있습니다.
                        </p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon summary">📋</div>
                        <h3>맥락 요약</h3>
                        <p>
                            긴 기사도 핵심 내용만 추출하여 빠르게 
                            뉴스의 맥락을 이해할 수 있도록 요약합니다.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        
        <div class="container">
            <div class="status-banner">
                <div class="status-dot"></div>
                <span class="status-text">서버 정상 운영 중 • PHP <?php echo PHP_VERSION; ?></span>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> News 맥락 분석. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
<?php
    }
}
