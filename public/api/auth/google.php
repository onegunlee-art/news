<?php
/**
 * Google 로그인 - 직접 처리 (카카오와 동일 패턴)
 * 경로: /api/auth/google
 */

// .env 로드 (닷홈: DOCUMENT_ROOT = /html)
$envPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/env.txt',
    $_SERVER['DOCUMENT_ROOT'] . '/.env',
    dirname(__DIR__, 3) . '/env.txt',
    dirname(__DIR__, 3) . '/.env',
];
foreach ($envPaths as $envFile) {
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\"'");
                if ($name !== '') { putenv("$name=$value"); $_ENV[$name] = $value; }
            }
        }
        break;
    }
}

// 설정 파일 로드
$configPath = null;
$tryPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/google.php',
    dirname(__DIR__, 2) . '/config/google.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/google.php',
    dirname(__DIR__, 3) . '/config/google.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) { $configPath = $p; break; }
}

if (!$configPath) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Google 설정 파일을 찾을 수 없습니다.',
        'tried' => $tryPaths,
        'docroot' => $_SERVER['DOCUMENT_ROOT'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

if (empty($config['client_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'GOOGLE_CLIENT_ID가 설정되지 않았습니다. GitHub Secrets에 등록 후 재배포하세요.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$params = [
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['oauth']['redirect_uri'],
    'response_type' => 'code',
    'scope'         => $config['oauth']['scope'],
    'access_type'   => 'offline',
    'prompt'        => 'consent',
];

$loginUrl = $config['oauth']['authorize_url'] . '?' . http_build_query($params);
header('Location: ' . $loginUrl);
exit;
