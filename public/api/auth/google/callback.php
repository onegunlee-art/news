<?php
/**
 * Google 콜백 - 직접 처리 (카카오 콜백과 동일 패턴)
 * 경로: /api/auth/google/callback
 */

error_reporting(0);
ini_set('display_errors', '0');

$frontendBase = 'https://www.thegist.co.kr';

// .env 로드
$projectRoot = dirname(__DIR__, 4);
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

function googleRedirectError(string $desc): void {
    global $frontendBase;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>로그인 오류</title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#1a1a2e;color:#fff}.box{text-align:center;max-width:400px;padding:2rem}p{color:#aaa;margin-top:12px;font-size:14px}</style></head>
<body><div class="box"><h2>Google 로그인 실패</h2><p>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p><p>잠시 후 홈으로 이동합니다...</p></div>
<script>setTimeout(function(){ window.location.href = ' . json_encode($frontendBase) . '; }, 3000);</script></body></html>';
    exit;
}

if (isset($_GET['error'])) {
    googleRedirectError($_GET['error_description'] ?? 'Google 인증이 취소되었습니다.');
}

$code = $_GET['code'] ?? null;
if (empty($code)) {
    googleRedirectError('인가 코드가 없습니다. 다시 시도해주세요.');
}

// 설정 로드
$configPath = null;
$tryPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/google.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/google.php',
    dirname(__DIR__, 3) . '/config/google.php',
    dirname(__DIR__, 4) . '/config/google.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) { $configPath = $p; break; }
}
if (!$configPath) {
    googleRedirectError('서버 설정 파일을 찾을 수 없습니다 (config/google.php)');
}
$config = require $configPath;

// 토큰 교환
$tokenPostData = [
    'code'          => $code,
    'client_id'     => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri'  => $config['oauth']['redirect_uri'],
    'grant_type'    => 'authorization_code',
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $config['oauth']['token_url'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($tokenPostData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
]);
$tokenResponse = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    $err = json_decode($tokenResponse ?: '{}', true);
    googleRedirectError('토큰 교환 실패: ' . ($err['error_description'] ?? $curlErr ?? 'HTTP ' . $httpCode));
}

$tokenData = json_decode($tokenResponse, true);
$googleAccessToken = $tokenData['access_token'] ?? null;
if (empty($googleAccessToken)) {
    googleRedirectError('Google 액세스 토큰을 받지 못했습니다.');
}

// 사용자 정보 조회
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $config['userinfo_url'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $googleAccessToken],
    CURLOPT_SSL_VERIFYPEER => true,
]);
$userResponse = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    googleRedirectError('Google 사용자 정보 조회 실패 (HTTP ' . $httpCode . ')');
}

$gUser = json_decode($userResponse, true);
$googleId = $gUser['id'] ?? null;
if (empty($googleId)) {
    googleRedirectError('Google ID를 받지 못했습니다.');
}

$nickname = $gUser['name'] ?? ($gUser['email'] ?? 'Google User');
$email = $gUser['email'] ?? null;
$profileImage = $gUser['picture'] ?? null;

// DB 저장/업데이트
$dbUserId = null;
$isNewUser = false;
try {
    $dbConfigPath = null;
    $dbTry = [
        $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config/database.php',
        dirname(__DIR__, 3) . '/config/database.php',
        dirname(__DIR__, 4) . '/config/database.php',
    ];
    foreach ($dbTry as $p) { if (file_exists($p)) { $dbConfigPath = $p; break; } }
    $dbCfg = $dbConfigPath ? require $dbConfigPath : [
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

    $stmt = $pdo->prepare("SELECT id FROM users WHERE google_id = ? LIMIT 1");
    $stmt->execute([$googleId]);
    $row = $stmt->fetch();

    if ($row) {
        $dbUserId = (int) $row['id'];
        $pdo->prepare("UPDATE users SET nickname = ?, profile_image = ?, email = ?, last_login_at = NOW() WHERE id = ?")
            ->execute([$nickname, $profileImage, $email, $dbUserId]);
    } else {
        $isNewUser = true;
        $pdo->prepare("INSERT INTO users (google_id, nickname, profile_image, email, role, status, created_at, last_login_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW(), NOW())")
            ->execute([$googleId, $nickname, $profileImage, $email]);
        $dbUserId = (int) $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    googleRedirectError('DB 오류: ' . $e->getMessage());
}

// JWT 생성
$appConfigPath = null;
$appTry = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/app.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/app.php',
    dirname(__DIR__, 3) . '/config/app.php',
    dirname(__DIR__, 4) . '/config/app.php',
];
foreach ($appTry as $p) { if (file_exists($p)) { $appConfigPath = $p; break; } }
$appConfig = $appConfigPath ? (require $appConfigPath) : [];
$jwtSecret = $appConfig['security']['jwt_secret'] ?? 'news-context-jwt-secret-key-2026';

$jwtHeader = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');

$accessPayload = [
    'user_id' => $dbUserId,
    'type' => 'access',
    'google_id' => $googleId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
    'provider' => 'google',
    'iat' => time(),
    'exp' => time() + 3600 * 24,
];
$accessPayloadEnc = rtrim(strtr(base64_encode(json_encode($accessPayload)), '+/', '-_'), '=');
$accessSig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$accessPayloadEnc", $jwtSecret, true)), '+/', '-_'), '=');
$accessTokenJwt = "$jwtHeader.$accessPayloadEnc.$accessSig";

$refreshPayload = ['user_id' => $dbUserId, 'type' => 'refresh', 'iat' => time(), 'exp' => time() + 86400 * 30];
$refreshPayloadEnc = rtrim(strtr(base64_encode(json_encode($refreshPayload)), '+/', '-_'), '=');
$refreshSig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$refreshPayloadEnc", $jwtSecret, true)), '+/', '-_'), '=');
$refreshTokenJwt = "$jwtHeader.$refreshPayloadEnc.$refreshSig";

// 구독 상태 조회
$subStmt = $pdo->prepare("SELECT is_subscribed, subscription_expires_at FROM users WHERE id = ?");
$subStmt->execute([$dbUserId]);
$subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
$dbIsSubscribed = false;
$dbSubExpiresAt = null;
if ($subRow) {
    $dbIsSubscribed = (bool) ($subRow['is_subscribed'] ?? false);
    $dbSubExpiresAt = $subRow['subscription_expires_at'] ?? null;
    if ($dbIsSubscribed && $dbSubExpiresAt && strtotime($dbSubExpiresAt) < time()) {
        $dbIsSubscribed = false;
    }
}

$userObj = [
    'id' => $dbUserId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
    'role' => 'user',
    'created_at' => date('c'),
    'is_subscribed' => $dbIsSubscribed,
    'subscription_expires_at' => $dbSubExpiresAt,
];

$welcomePopupJs = '';
if ($isNewUser) {
    $welcomePopupJs = 'localStorage.setItem("consent_required", "1");';
    $welcomePopupJs .= 'localStorage.setItem("welcome_popup", JSON.stringify({userName:' . json_encode($nickname) . ',ts:Date.now()}));';
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>로그인 처리 중...</title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f5f5f5}.loading{text-align:center}.spinner{width:40px;height:40px;border:4px solid #e0e0e0;border-top-color:#4285F4;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px}@keyframes spin{to{transform:rotate(360deg)}}</style>
</head>
<body>
<div class="loading"><div class="spinner"></div><p>Google 로그인 처리 중...</p></div>
<script>
try {
    localStorage.setItem("access_token", ' . json_encode($accessTokenJwt) . ');
    localStorage.setItem("refresh_token", ' . json_encode($refreshTokenJwt) . ');
    localStorage.setItem("user", JSON.stringify(' . json_encode($userObj) . '));
    var authStorage = { state: { accessToken: ' . json_encode($accessTokenJwt) . ', refreshToken: ' . json_encode($refreshTokenJwt) . ', isSubscribed: ' . ($dbIsSubscribed ? 'true' : 'false') . ' }, version: 0 };
    localStorage.setItem("auth-storage", JSON.stringify(authStorage));
    ' . $welcomePopupJs . '
} catch(e) { console.error("localStorage error:", e); }
window.location.href = ' . json_encode($frontendBase . '/auth/callback#access_token=' . urlencode($accessTokenJwt) . '&refresh_token=' . urlencode($refreshTokenJwt)) . ';
</script>
</body>
</html>';
