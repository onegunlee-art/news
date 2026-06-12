<?php
/**
 * GIST EDU — 카카오 로그인 시작
 * GET /api/edu/auth/kakao
 * 
 * 학생이 카카오 로그인 버튼 클릭 시 이 엔드포인트로 리다이렉트.
 * 카카오 인증 페이지로 redirect_uri와 함께 보냄.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$configPath = null;
$tryPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/kakao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/kakao.php',
    dirname(__DIR__, 2) . '/config/kakao.php',
    dirname(__DIR__, 3) . '/config/kakao.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) { $configPath = $p; break; }
}

if (!$configPath) {
    eduSendError('카카오 설정 파일을 찾을 수 없습니다.', 500);
}

$config = require $configPath;

if (empty($config['rest_api_key'])) {
    eduSendError('카카오 REST API 키가 설정되지 않았습니다.', 500);
}

$eduClientId = getenv('EDU_KAKAO_CLIENT_ID') ?: $config['rest_api_key'];
$eduRedirectUri = getenv('EDU_KAKAO_REDIRECT_URI') ?: 'https://edu.thegist.co.kr/api/edu/auth/kakao/callback';

$state = bin2hex(random_bytes(16));
setcookie('edu_oauth_state', $state, [
    'expires' => time() + 600,
    'path' => '/',
    'domain' => '.thegist.co.kr',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

$params = [
    'client_id' => $eduClientId,
    'redirect_uri' => $eduRedirectUri,
    'response_type' => 'code',
    'state' => $state,
];

if (!empty($config['oauth']['scope'])) {
    $params['scope'] = implode(',', $config['oauth']['scope']);
}

$loginUrl = ($config['oauth']['authorize_url'] ?? 'https://kauth.kakao.com/oauth/authorize') 
    . '?' . http_build_query($params);

header('Location: ' . $loginUrl);
exit;
