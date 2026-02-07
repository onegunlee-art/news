<?php
/**
 * 카카오 콜백 - 직접 처리
 * 
 * 카카오 인증 후 콜백을 처리합니다.
 * 경로: /api/auth/kakao/callback
 */

// 에러 처리
if (isset($_GET['error'])) {
    $errorCode = $_GET['error'] ?? 'unknown';
    $errorDescription = $_GET['error_description'] ?? '알 수 없는 오류';
    
    // 프론트엔드로 에러 전달
    $frontendUrl = 'https://www.thegist.co.kr/auth/callback?error=' . urlencode($errorCode) . '&error_description=' . urlencode($errorDescription);
    header('Location: ' . $frontendUrl);
    exit;
}

// 인가 코드 확인
$code = $_GET['code'] ?? null;

if (empty($code)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '인가 코드가 없습니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 설정 파일 로드
$configPath = dirname(__DIR__, 4) . '/config/kakao.php';

if (!file_exists($configPath)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '설정 파일을 찾을 수 없습니다.',
        'path' => $configPath,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

// 액세스 토큰 요청
$tokenUrl = $config['oauth']['token_url'];
$tokenParams = [
    'grant_type' => 'authorization_code',
    'client_id' => $config['rest_api_key'],
    'redirect_uri' => $config['oauth']['redirect_uri'],
    'code' => $code,
];

// cURL로 토큰 요청
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $tokenUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($tokenParams),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
    ],
]);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $error = json_decode($tokenResponse, true);
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '토큰 발급 실패',
        'error' => $error,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? null;

if (empty($accessToken)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '액세스 토큰이 없습니다.',
        'data' => $tokenData,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 사용자 정보 요청
$userInfoUrl = $config['api']['base_url'] . $config['api']['user_info'];
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $userInfoUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
    ],
]);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '사용자 정보 조회 실패',
        'error' => json_decode($userResponse, true),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userData = json_decode($userResponse, true);

// 사용자 정보 추출
$kakaoId = $userData['id'] ?? null;
$kakaoAccount = $userData['kakao_account'] ?? [];
$profile = $kakaoAccount['profile'] ?? [];

$nickname = $profile['nickname'] ?? '사용자';
$profileImage = $profile['profile_image_url'] ?? null;
$email = $kakaoAccount['email'] ?? null;

// JWT 토큰 생성 (간단한 버전)
$jwtSecret = 'news-context-jwt-secret-key-2026';
$jwtPayload = [
    'user_id' => $kakaoId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
    'provider' => 'kakao',
    'iat' => time(),
    'exp' => time() + 3600, // 1시간 후 만료
];

$jwtHeader = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
$jwtPayloadEncoded = rtrim(strtr(base64_encode(json_encode($jwtPayload)), '+/', '-_'), '=');
$jwtSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$jwtPayloadEncoded", $jwtSecret, true)), '+/', '-_'), '=');
$accessTokenJwt = "$jwtHeader.$jwtPayloadEncoded.$jwtSignature";

// 리프레시 토큰
$refreshPayload = [
    'user_id' => $kakaoId,
    'type' => 'refresh',
    'iat' => time(),
    'exp' => time() + 86400 * 7, // 7일 후 만료
];
$refreshPayloadEncoded = rtrim(strtr(base64_encode(json_encode($refreshPayload)), '+/', '-_'), '=');
$refreshSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$refreshPayloadEncoded", $jwtSecret, true)), '+/', '-_'), '=');
$refreshTokenJwt = "$jwtHeader.$refreshPayloadEncoded.$refreshSignature";

// 프론트엔드로 리다이렉트 (토큰을 URL 프래그먼트로 전달)
$frontendUrl = 'https://www.thegist.co.kr/auth/callback';
$fragment = http_build_query([
    'access_token' => $accessTokenJwt,
    'refresh_token' => $refreshTokenJwt,
]);

// HTML 리다이렉트 (프래그먼트는 서버에서 Location 헤더로 전달 불가)
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>로그인 처리 중...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .loading {
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e0e0e0;
            border-top-color: #FEE500;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>카카오 로그인 처리 중...</p>
    </div>
    <script>
        window.location.href = "' . $frontendUrl . '#' . $fragment . '";
    </script>
</body>
</html>';
