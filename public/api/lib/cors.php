<?php
/**
 * CORS 헤더 설정 공통 함수
 * config/app.php의 cors 설정을 사용합니다.
 */

function setCorsHeaders(): void {
    static $applied = false;
    if ($applied) return;
    $applied = true;

    $configPaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/config/app.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config/app.php',
        __DIR__ . '/../../../config/app.php',
    ];

    $corsConfig = null;
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            $config = require $path;
            $corsConfig = $config['cors'] ?? null;
            break;
        }
    }

    if (!$corsConfig) {
        $corsConfig = [
            'allowed_origins' => ['https://thegist.co.kr', 'https://www.thegist.co.kr'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Authorization', 'X-Requested-With'],
            'max_age' => 86400,
            'supports_credentials' => true,
        ];
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = $corsConfig['allowed_origins'] ?? [];

    if (in_array('*', $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin && in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
    }

    $methods = implode(', ', $corsConfig['allowed_methods'] ?? ['GET', 'POST', 'OPTIONS']);
    $headers = implode(', ', $corsConfig['allowed_headers'] ?? ['Content-Type', 'Authorization']);

    header("Access-Control-Allow-Methods: $methods");
    header("Access-Control-Allow-Headers: $headers");

    if (!empty($corsConfig['supports_credentials'])) {
        header('Access-Control-Allow-Credentials: true');
    }

    if (!empty($corsConfig['max_age'])) {
        header("Access-Control-Max-Age: {$corsConfig['max_age']}");
    }
}

function handleOptionsRequest(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(204);
        exit;
    }
}
