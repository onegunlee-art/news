<?php
/**
 * 카카오 콜백 - 직접 처리
 * 
 * 카카오 인증 후 콜백을 처리합니다.
 * 경로: /api/auth/kakao/callback
 */

$frontendBase = 'https://www.thegist.co.kr';

// 에러 처리
if (isset($_GET['error'])) {
    $errorCode = $_GET['error'] ?? 'unknown';
    $errorDescription = $_GET['error_description'] ?? '알 수 없는 오류';
    $frontendUrl = $frontendBase . '/auth/callback?error=' . urlencode($errorCode) . '&error_description=' . urlencode($errorDescription);
    header('Location: ' . $frontendUrl);
    exit;
}

// 인가 코드 확인
$code = $_GET['code'] ?? null;

if (empty($code)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '인가 코드가 없습니다.'], JSON_UNESCAPED_UNICODE);
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
    $frontendUrl = $frontendBase . '/auth/callback?error=config_not_found&error_description=' . urlencode('카카오 설정 파일을 찾을 수 없습니다.');
    header('Location: ' . $frontendUrl);
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
    $msg = $error['error_description'] ?? ($error['error'] ?? '토큰 발급 실패');
    $frontendUrl = $frontendBase . '/auth/callback?error=' . urlencode('token_error') . '&error_description=' . urlencode($msg);
    header('Location: ' . $frontendUrl);
    exit;
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? null;

if (empty($accessToken)) {
    $frontendUrl = $frontendBase . '/auth/callback?error=no_token&error_description=' . urlencode('카카오 액세스 토큰을 받지 못했습니다.');
    header('Location: ' . $frontendUrl);
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
    $frontendUrl = $frontendBase . '/auth/callback?error=user_info_failed&error_description=' . urlencode('카카오 사용자 정보 조회 실패 (HTTP ' . $httpCode . ')');
    header('Location: ' . $frontendUrl);
    exit;
}

$userData = json_decode($userResponse, true);
$kakaoId = $userData['id'] ?? null;

if (empty($kakaoId)) {
    $frontendUrl = $frontendBase . '/auth/callback?error=no_kakao_id&error_description=' . urlencode('카카오 ID를 받지 못했습니다.');
    header('Location: ' . $frontendUrl);
    exit;
}

$kakaoAccount = $userData['kakao_account'] ?? [];
$profile = $kakaoAccount['profile'] ?? [];
$nickname = $profile['nickname'] ?? '사용자';
$profileImage = $profile['profile_image_url'] ?? null;
$email = $kakaoAccount['email'] ?? null;

// DB에 사용자 저장/업데이트
$dbUserId = null;
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

    if ($row) {
        $dbUserId = (int)$row['id'];
        $pdo->prepare("UPDATE users SET nickname = ?, profile_image = ?, last_login_at = NOW() WHERE id = ?")
            ->execute([$nickname, $profileImage, $dbUserId]);
    } else {
        $pdo->prepare("INSERT INTO users (kakao_id, nickname, profile_image, email, role, status, created_at, last_login_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW(), NOW())")
            ->execute([(string)$kakaoId, $nickname, $profileImage, $email]);
        $dbUserId = (int)$pdo->lastInsertId();
    }
} catch (Throwable $e) {
    error_log('[kakao-callback] DB error: ' . $e->getMessage());
    $dbUserId = $kakaoId;
}

// JWT 토큰 생성
$jwtSecret = 'news-context-jwt-secret-key-2026';

$jwtPayload = [
    'user_id' => $dbUserId,
    'kakao_id' => (string)$kakaoId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
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

// user 객체 (프론트엔드용)
$userJson = json_encode([
    'id' => $dbUserId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
    'role' => 'user',
    'created_at' => date('c'),
    'is_subscribed' => false,
], JSON_UNESCAPED_UNICODE);

// HTML 리다이렉트 (localStorage에 저장 후 이동)
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>로그인 처리 중...</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .loading { text-align: center; }
        .spinner { width: 40px; height: 40px; border: 4px solid #e0e0e0; border-top-color: #FEE500; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>카카오 로그인 처리 중...</p>
    </div>
    <script>
        try {
            localStorage.setItem("access_token", ' . json_encode($accessTokenJwt) . ');
            localStorage.setItem("refresh_token", ' . json_encode($refreshTokenJwt) . ');
            localStorage.setItem("user", ' . json_encode($userJson) . ');
        } catch(e) {}
        window.location.href = "' . $frontendBase . '/auth/callback#access_token=' . urlencode($accessTokenJwt) . '&refresh_token=' . urlencode($refreshTokenJwt) . '";
    </script>
</body>
</html>';
