<?php
/**
 * Google 로그인 - 직접 처리
 * 경로: /api/auth/google
 */

// .env 로드
$projectRoot = dirname(__DIR__, 3);
$envFile = $projectRoot . '/env.txt';
if (!is_file($envFile)) $envFile = $projectRoot . '/.env';
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
}

$configPath = null;
$tryPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/google.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/google.php',
    dirname(__DIR__, 2) . '/config/google.php',
    dirname(__DIR__, 3) . '/config/google.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) { $configPath = $p; break; }
}

if (!$configPath) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google 설정 파일을 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

if (empty($config['client_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'GOOGLE_CLIENT_ID가 설정되지 않았습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$state = bin2hex(random_bytes(16));
session_start();
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['oauth']['redirect_uri'],
    'response_type' => 'code',
    'scope'         => $config['oauth']['scope'],
    'state'         => $state,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
];

$loginUrl = $config['oauth']['authorize_url'] . '?' . http_build_query($params);
header('Location: ' . $loginUrl);
exit;
