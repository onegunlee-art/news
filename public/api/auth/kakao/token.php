<?php
/**
 * 카카오 인가 코드로 토큰 교환 API
 * 
 * POST /api/auth/kakao/token.php
 * Body: { "code": "인가코드" }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 요청 본문 파싱
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? null;

if (empty($code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '인가 코드가 필요합니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 설정 파일 로드
// 배포 환경에 맞게 경로 조정
$configPath = dirname(__DIR__, 4) . '/config/kakao.php';

// 배포 환경에서 경로가 다를 수 있으므로 여러 경로 시도
if (!file_exists($configPath)) {
    // 대체 경로 시도
    $configPath = dirname(__DIR__, 3) . '/config/kakao.php';
}

if (!file_exists($configPath)) {
    // 또 다른 대체 경로 시도
    $configPath = __DIR__ . '/../../../../config/kakao.php';
}

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '설정 파일을 찾을 수 없습니다.',
        'tried_paths' => [
            dirname(__DIR__, 4) . '/config/kakao.php',
            dirname(__DIR__, 3) . '/config/kakao.php',
            __DIR__ . '/../../../../config/kakao.php',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

// Redirect URI 설정 (프론트엔드 콜백 URL)
$redirectUri = 'http://ailand.dothome.co.kr/auth/callback';

// 액세스 토큰 요청
$tokenUrl = $config['oauth']['token_url'];
$tokenParams = [
    'grant_type' => 'authorization_code',
    'client_id' => $config['rest_api_key'],
    'redirect_uri' => $redirectUri,
    'code' => $code,
];

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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '토큰 발급 실패',
        'error' => $error,
        'http_code' => $httpCode,
        'response' => $tokenResponse,
        'redirect_uri' => $redirectUri,
        'code' => substr($code, 0, 20) . '...', // 코드 일부만 로깅
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenData = json_decode($tokenResponse, true);
$kakaoAccessToken = $tokenData['access_token'] ?? null;

if (empty($kakaoAccessToken)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '액세스 토큰이 없습니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 사용자 정보 요청
$userInfoUrl = $config['api']['base_url'] . $config['api']['user_info'];
$propertyKeys = implode(',', $config['property_keys'] ?? ['kakao_account.profile']);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $userInfoUrl . '?property_keys=[' . $propertyKeys . ']',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $kakaoAccessToken,
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
    ],
]);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    $errorData = json_decode($userResponse, true);
    echo json_encode([
        'success' => false,
        'message' => '사용자 정보 조회 실패',
        'error' => $errorData,
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => $userResponse,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userData = json_decode($userResponse, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '사용자 정보 파싱 실패',
        'json_error' => json_last_error_msg(),
        'response' => $userResponse,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 사용자 정보 추출
$kakaoId = $userData['id'] ?? null;

if (empty($kakaoId)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '카카오 ID를 찾을 수 없습니다.',
        'user_data' => $userData,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$kakaoAccount = $userData['kakao_account'] ?? [];
$profile = $kakaoAccount['profile'] ?? [];

$nickname = $profile['nickname'] ?? '사용자';
$profileImage = $profile['profile_image_url'] ?? null;

// JWT 토큰 생성
$jwtSecret = 'news-context-jwt-secret-key-2026';
$jwtPayload = [
    'user_id' => $kakaoId,
    'nickname' => $nickname,
    'profile_image' => $profileImage,
    'provider' => 'kakao',
    'iat' => time(),
    'exp' => time() + 3600 * 24, // 24시간 후 만료
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
    'exp' => time() + 86400 * 30, // 30일 후 만료
];
$refreshPayloadEncoded = rtrim(strtr(base64_encode(json_encode($refreshPayload)), '+/', '-_'), '=');
$refreshSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$refreshPayloadEncoded", $jwtSecret, true)), '+/', '-_'), '=');
$refreshTokenJwt = "$jwtHeader.$refreshPayloadEncoded.$refreshSignature";

// 성공 응답
echo json_encode([
    'success' => true,
    'message' => '로그인 성공',
    'access_token' => $accessTokenJwt,
    'refresh_token' => $refreshTokenJwt,
    'user' => [
        'id' => $kakaoId,
        'nickname' => $nickname,
        'profile_image' => $profileImage,
        'role' => 'user',
        'created_at' => date('c'),
        'is_subscribed' => false,
    ],
], JSON_UNESCAPED_UNICODE);
