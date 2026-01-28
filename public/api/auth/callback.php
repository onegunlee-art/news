<?php
/**
 * 카카오 콜백 - 직접 처리
 * 
 * 카카오 인증 후 콜백을 처리합니다.
 * 경로: /api/auth/kakao/callback -> 이 파일
 */

header('Content-Type: application/json; charset=utf-8');

// 에러 처리
if (isset($_GET['error'])) {
    $errorCode = $_GET['error'] ?? 'unknown';
    $errorDescription = $_GET['error_description'] ?? '알 수 없는 오류';
    
    // 프론트엔드로 에러 전달
    $frontendUrl = 'http://ailand.dothome.co.kr/auth/callback?error=' . urlencode($errorCode) . '&error_description=' . urlencode($errorDescription);
    header('Location: ' . $frontendUrl);
    exit;
}

// 인가 코드 확인
$code = $_GET['code'] ?? null;

if (empty($code)) {
    echo json_encode([
        'success' => false,
        'message' => '인가 코드가 없습니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 설정 파일 로드
$configPath = dirname(__DIR__, 3) . '/config/kakao.php';
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
$jwtPayload = [
    'user_id' => $kakaoId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
    'exp' => time() + 3600, // 1시간 후 만료
];

// 간단한 JWT 생성 (실제로는 더 안전한 방식 사용)
$jwtHeader = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
$jwtPayloadEncoded = base64_encode(json_encode($jwtPayload));
$jwtSecret = 'your-secret-key-change-this'; // 실제로는 환경 변수에서 가져와야 함
$jwtSignature = base64_encode(hash_hmac('sha256', "$jwtHeader.$jwtPayloadEncoded", $jwtSecret, true));
$accessTokenJwt = "$jwtHeader.$jwtPayloadEncoded.$jwtSignature";

// 리프레시 토큰 (간단한 버전)
$refreshTokenJwt = base64_encode(json_encode([
    'user_id' => $kakaoId,
    'exp' => time() + 86400 * 7, // 7일 후 만료
]));

// 프론트엔드로 리다이렉트 (토큰을 URL 프래그먼트로 전달)
$frontendUrl = 'http://ailand.dothome.co.kr/auth/callback';
$fragment = http_build_query([
    'access_token' => $accessTokenJwt,
    'refresh_token' => $refreshTokenJwt,
    'user' => json_encode([
        'id' => $kakaoId,
        'nickname' => $nickname,
        'email' => $email,
        'profile_image' => $profileImage,
    ]),
]);

// HTML 리다이렉트 (프래그먼트는 서버에서 Location 헤더로 전달 불가)
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>로그인 처리 중...</title>
</head>
<body>
    <p>로그인 처리 중입니다. 잠시만 기다려주세요...</p>
    <script>
        window.location.href = "' . $frontendUrl . '#' . $fragment . '";
    </script>
</body>
</html>';
