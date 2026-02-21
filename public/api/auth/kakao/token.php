<?php
/**
 * 카카오 인가 코드로 토큰 교환 API
 * 
 * POST /api/auth/kakao/token
 * Body: { "code": "인가코드" }
 * 
 * AuthCallback.tsx에서 code로 직접 토큰 교환할 때 사용 (fallback)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? null;

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '인가 코드가 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 설정 파일 로드 (배포 환경 대응: 여러 경로 시도)
$configPath = null;
$tryPaths = [
    dirname(__DIR__, 4) . '/config/kakao.php',
    dirname(__DIR__, 3) . '/config/kakao.php',
    __DIR__ . '/../../../../config/kakao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/kakao.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) { $configPath = $p; break; }
}

if (!$configPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '설정 파일을 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

$redirectUri = $config['oauth']['redirect_uri'];

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
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenData = json_decode($tokenResponse, true);
$kakaoAccessToken = $tokenData['access_token'] ?? null;

if (empty($kakaoAccessToken)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '액세스 토큰이 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 사용자 정보 요청
$userInfoUrl = $config['api']['base_url'] . $config['api']['user_info'];
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $userInfoUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $kakaoAccessToken,
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
    ],
]);
$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '사용자 정보 조회 실패'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userData = json_decode($userResponse, true);
$kakaoId = $userData['id'] ?? null;

if (empty($kakaoId)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '카카오 ID를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$kakaoAccount = $userData['kakao_account'] ?? [];
$profile = $kakaoAccount['profile'] ?? [];
$nickname = $profile['nickname'] ?? '사용자';
$profileImage = $profile['profile_image_url'] ?? null;
$email = $kakaoAccount['email'] ?? null;

// DB에 사용자 저장/업데이트
$dbUserId = null;
$isNewUser = false;
$promoCode = null;
try {
    $dbCfg = [
        'host' => 'localhost',
        'dbname' => 'ailand',
        'username' => 'ailand',
        'password' => 'romi4120!',
        'charset' => 'utf8mb4',
    ];
    $pdo = new PDO(
        "mysql:host={$dbCfg['host']};dbname={$dbCfg['dbname']};charset={$dbCfg['charset']}",
        $dbCfg['username'], $dbCfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $pdo->prepare("SELECT id FROM users WHERE kakao_id = ? LIMIT 1");
    $stmt->execute([(string)$kakaoId]);
    $row = $stmt->fetch();

    $isNewUser = false;
    $promoCode = null;
    if ($row) {
        $dbUserId = (int)$row['id'];
        $pdo->prepare("UPDATE users SET nickname = ?, profile_image = ?, last_login_at = NOW() WHERE id = ?")
            ->execute([$nickname, $profileImage, $dbUserId]);
    } else {
        $pdo->prepare("INSERT INTO users (kakao_id, nickname, profile_image, email, role, status, created_at, last_login_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW(), NOW())")
            ->execute([(string)$kakaoId, $nickname, $profileImage, $email]);
        $dbUserId = (int)$pdo->lastInsertId();
        $isNewUser = true;
        try {
            $prefix = 'WELCOME';
            $pr = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'promo_code_prefix' LIMIT 1");
            if ($pr && ($prRow = $pr->fetch(PDO::FETCH_ASSOC)) && !empty(trim($prRow['value'] ?? ''))) {
                $prefix = trim($prRow['value']);
            }
            $promoCode = $prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->prepare("INSERT INTO promo_codes (user_id, code) VALUES (?, ?)")->execute([$dbUserId, $promoCode]);
        } catch (Throwable $ex) {
            error_log('[kakao-token] Promo code: ' . $ex->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('[kakao-token] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '로그인 처리 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$dbUserId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '사용자 정보를 가져올 수 없습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// JWT 토큰 생성
$jwtSecret = 'news-context-jwt-secret-key-2026';

$jwtPayload = [
    'user_id' => $dbUserId,
    'kakao_id' => (string)$kakaoId,
    'nickname' => $nickname,
    'provider' => 'kakao',
    'iat' => time(),
    'exp' => time() + 3600 * 24,
];

$jwtHeader = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
$jwtPayloadEncoded = rtrim(strtr(base64_encode(json_encode($jwtPayload)), '+/', '-_'), '=');
$jwtSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$jwtPayloadEncoded", $jwtSecret, true)), '+/', '-_'), '=');
$accessTokenJwt = "$jwtHeader.$jwtPayloadEncoded.$jwtSignature";

$refreshPayload = [
    'user_id' => $dbUserId,
    'type' => 'refresh',
    'iat' => time(),
    'exp' => time() + 86400 * 30,
];
$refreshPayloadEncoded = rtrim(strtr(base64_encode(json_encode($refreshPayload)), '+/', '-_'), '=');
$refreshSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$refreshPayloadEncoded", $jwtSecret, true)), '+/', '-_'), '=');
$refreshTokenJwt = "$jwtHeader.$refreshPayloadEncoded.$refreshSignature";

$response = [
    'success' => true,
    'message' => '로그인 성공',
    'access_token' => $accessTokenJwt,
    'refresh_token' => $refreshTokenJwt,
    'user' => [
        'id' => $dbUserId,
        'nickname' => $nickname,
        'email' => $email,
        'profile_image' => $profileImage,
        'role' => 'user',
        'created_at' => date('c'),
        'is_subscribed' => false,
    ],
];
if ($isNewUser ?? false) {
    $response['is_new_user'] = true;
    $response['promo_code'] = $promoCode ?? null;
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
