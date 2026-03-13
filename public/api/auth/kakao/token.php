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
try {
    $dbConfigPath = __DIR__ . '/../../../../config/database.php';
    $dbCfg = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'database' => getenv('DB_DATABASE') ?: 'ailand',
        'username' => getenv('DB_USERNAME') ?: 'ailand',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ];
    $dbName = $dbCfg['database'] ?? $dbCfg['dbname'] ?? 'ailand';
    $pdo = new PDO(
        "mysql:host={$dbCfg['host']};dbname={$dbName};charset={$dbCfg['charset']}",
        $dbCfg['username'], $dbCfg['password'],
        $dbCfg['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE kakao_id = ? LIMIT 1");
    $stmt->execute([(string)$kakaoId]);
    $row = $stmt->fetch();

    $userRole = 'user';
    if ($row) {
        $dbUserId = (int)$row['id'];
        $userRole = $row['role'] ?? 'user';
        $pdo->prepare("UPDATE users SET nickname = ?, profile_image = ?, last_login_at = NOW() WHERE id = ?")
            ->execute([$nickname, $profileImage, $dbUserId]);
    } else {
        $pdo->prepare("INSERT INTO users (kakao_id, nickname, profile_image, email, role, status, created_at, last_login_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW(), NOW())")
            ->execute([(string)$kakaoId, $nickname, $profileImage, $email]);
        $dbUserId = (int)$pdo->lastInsertId();
        $isNewUser = true;
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

// JWT secret: config/app.php와 동일한 값 사용 (AuthController 검증과 일치)
$appConfigPath = null;
$appConfigTryPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/app.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/app.php',
    dirname(__DIR__, 3) . '/config/app.php',
    dirname(__DIR__, 4) . '/config/app.php',
    __DIR__ . '/../../../../config/app.php',
];
foreach ($appConfigTryPaths as $p) {
    if (file_exists($p)) { $appConfigPath = $p; break; }
}
$appConfig = $appConfigPath ? (require $appConfigPath) : [];
$jwtSecret = $appConfig['security']['jwt_secret'] ?? 'news-context-jwt-secret-key-2026';
if (empty($jwtSecret)) {
    $jwtSecret = 'news-context-jwt-secret-key-2026';
}

// JWT 토큰 생성 (AuthService::getUserFromToken 검증에 맞춤: type 필드 필수)
$jwtPayload = [
    'user_id' => $dbUserId,
    'type' => 'access',
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

// refresh token을 user_tokens 테이블에 저장 (이메일 로그인과 동일한 정책)
try {
    $pdo->prepare(
        "INSERT INTO user_tokens (user_id, token, token_type, expires_at, created_at)
         VALUES (?, ?, 'refresh', ?, NOW())"
    )->execute([
        $dbUserId,
        $refreshTokenJwt,
        date('Y-m-d H:i:s', time() + 86400 * 30),
    ]);
} catch (Throwable $e) {
    error_log('[kakao-token] Failed to save refresh token: ' . $e->getMessage());
}

// 구독 상태를 DB에서 조회
$subStmt = $pdo->prepare("SELECT is_subscribed, subscription_expires_at FROM users WHERE id = ?");
$subStmt->execute([$dbUserId]);
$subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
$dbIsSubscribed = false;
$dbSubExpiresAt = null;
if ($subRow) {
    $dbIsSubscribed = (bool)($subRow['is_subscribed'] ?? false);
    $dbSubExpiresAt = $subRow['subscription_expires_at'] ?? null;
    if ($dbIsSubscribed && $dbSubExpiresAt && strtotime($dbSubExpiresAt) < time()) {
        $dbIsSubscribed = false;
    }
}

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
        'role' => $userRole,
        'created_at' => date('c'),
        'is_subscribed' => $dbIsSubscribed,
        'subscription_expires_at' => $dbSubExpiresAt,
    ],
];
if ($isNewUser) {
    $response['is_new_user'] = true;
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
