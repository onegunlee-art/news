<?php
/**
 * 카카오 콜백 - 직접 처리
 * 경로: /api/auth/kakao/callback
 */

error_reporting(0);
ini_set('display_errors', '0');

$frontendBase = 'https://www.thegist.co.kr';

function kakaoLog(string $step, $data = null): void {
    $dirs = [
        $_SERVER['DOCUMENT_ROOT'] . '/../storage/logs',
        $_SERVER['DOCUMENT_ROOT'] . '/storage/logs',
        __DIR__ . '/../../../../storage/logs',
    ];
    foreach ($dirs as $d) {
        if (is_dir($d) || @mkdir($d, 0755, true)) {
            @file_put_contents($d . '/kakao_callback.log', json_encode([
                'ts' => date('Y-m-d H:i:s'),
                'step' => $step,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
            return;
        }
    }
}

function kakaoRedirectError(string $code, string $desc): void {
    global $frontendBase;
    kakaoLog('ERROR', ['code' => $code, 'desc' => $desc]);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>로그인 오류</title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#1a1a2e;color:#fff}.box{text-align:center;max-width:400px;padding:2rem}.icon{font-size:48px;margin-bottom:16px}p{color:#aaa;margin-top:12px;font-size:14px}</style></head>
<body><div class="box"><div class="icon">⚠️</div><h2>로그인 실패</h2><p>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p><p>잠시 후 홈으로 이동합니다...</p></div>
<script>
console.error("Kakao login error:", ' . json_encode(['code' => $code, 'desc' => $desc]) . ');
setTimeout(function(){ window.location.href = ' . json_encode($frontendBase) . '; }, 3000);
</script></body></html>';
    exit;
}

kakaoLog('start', ['method' => $_SERVER['REQUEST_METHOD'], 'has_code' => isset($_GET['code']), 'docroot' => $_SERVER['DOCUMENT_ROOT']]);

// 버전 확인용
if (isset($_GET['_version'])) {
    header('Content-Type: application/json');
    echo json_encode(['version' => '2026-02-20-v3', 'php' => PHP_VERSION, 'docroot' => $_SERVER['DOCUMENT_ROOT']]);
    exit;
}

// 최근 로그 확인용
if (isset($_GET['_logs'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $logDirs = [
        $_SERVER['DOCUMENT_ROOT'] . '/../storage/logs',
        $_SERVER['DOCUMENT_ROOT'] . '/storage/logs',
        __DIR__ . '/../../../../storage/logs',
    ];
    foreach ($logDirs as $d) {
        $f = $d . '/kakao_callback.log';
        if (file_exists($f)) {
            $lines = file($f);
            echo "=== Log: $f ===\n";
            echo implode('', array_slice($lines, -30));
            exit;
        }
    }
    echo 'No log file found. Tried: ' . implode(', ', $logDirs);
    exit;
}

// 카카오에서 에러 반환
if (isset($_GET['error'])) {
    kakaoRedirectError($_GET['error'] ?? 'unknown', $_GET['error_description'] ?? '카카오 인증 중 에러가 발생했습니다.');
}

// 인가 코드 확인
$code = $_GET['code'] ?? null;
if (empty($code)) {
    kakaoRedirectError('no_code', '인가 코드가 없습니다. 카카오 로그인을 다시 시도해주세요.');
}

// 설정 파일 로드 (배포 환경별 여러 경로 시도)
$configPath = null;
$tryPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config/kakao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/kakao.php',
    dirname(__DIR__, 3) . '/config/kakao.php',
    dirname(__DIR__, 4) . '/config/kakao.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) { $configPath = $p; break; }
}

kakaoLog('config', ['found' => $configPath, 'tried' => $tryPaths]);

if (!$configPath) {
    kakaoRedirectError('config_not_found', '서버 설정 파일을 찾을 수 없습니다 (config/kakao.php). 관리자에게 문의하세요.');
}

$config = require $configPath;

kakaoLog('token_exchange', ['redirect_uri' => $config['oauth']['redirect_uri'], 'has_key' => !empty($config['rest_api_key'])]);

// 카카오 토큰 교환
$tokenPostData = [
    'grant_type' => 'authorization_code',
    'client_id' => $config['rest_api_key'],
    'redirect_uri' => $config['oauth']['redirect_uri'],
    'code' => $code,
];
kakaoLog('token_request_data', ['redirect_uri' => $config['oauth']['redirect_uri'], 'client_id_prefix' => substr($config['rest_api_key'], 0, 8)]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['oauth']['token_url'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($tokenPostData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
]);
$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

kakaoLog('token_response', ['http' => $httpCode, 'curl_err' => $curlErr, 'body_preview' => mb_substr($tokenResponse ?: '', 0, 200)]);

if ($curlErr) {
    if (strpos($curlErr, 'SSL') !== false || strpos($curlErr, 'certificate') !== false) {
        kakaoLog('ssl_retry', 'Retrying without SSL verification');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['oauth']['token_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenPostData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        kakaoLog('ssl_retry_result', ['http' => $httpCode, 'curl_err' => $curlErr]);
    }
    if ($curlErr) {
        kakaoRedirectError('curl_error', 'curl 오류: ' . $curlErr);
    }
}

if ($httpCode !== 200) {
    $err = json_decode($tokenResponse, true);
    $msg = $err['error_description'] ?? ($err['error'] ?? '토큰 발급 실패');
    kakaoRedirectError('token_error', '카카오 토큰 교환 실패 (HTTP ' . $httpCode . '): ' . $msg);
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? null;

if (empty($accessToken)) {
    kakaoRedirectError('no_access_token', '카카오 액세스 토큰을 받지 못했습니다.');
}

// 사용자 정보 요청
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['api']['base_url'] . $config['api']['user_info'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
]);
$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

kakaoLog('userinfo_response', ['http' => $httpCode, 'curl_err' => $curlErr]);

if ($httpCode !== 200) {
    kakaoRedirectError('user_info_failed', '카카오 사용자 정보 조회 실패 (HTTP ' . $httpCode . ')');
}

$userData = json_decode($userResponse, true);
$kakaoId = $userData['id'] ?? null;

if (empty($kakaoId)) {
    kakaoRedirectError('no_kakao_id', '카카오 ID를 받지 못했습니다.');
}

$kakaoAccount = $userData['kakao_account'] ?? [];
$profile = $kakaoAccount['profile'] ?? [];
$nickname = $profile['nickname'] ?? '사용자';
$profileImage = $profile['profile_image_url'] ?? null;
$email = $kakaoAccount['email'] ?? null;

// DB에 사용자 저장/업데이트
$dbUserId = null;
try {
    $dbCfg = ['host' => 'localhost', 'dbname' => 'ailand', 'username' => 'ailand', 'password' => 'romi4120!', 'charset' => 'utf8mb4'];
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
    kakaoLog('db_ok', ['dbUserId' => $dbUserId, 'isNew' => !$row]);
} catch (Throwable $e) {
    kakaoLog('db_error', ['msg' => $e->getMessage()]);
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

$refreshPayload = ['user_id' => $dbUserId, 'type' => 'refresh', 'iat' => time(), 'exp' => time() + 86400 * 30];
$refreshPayloadEncoded = rtrim(strtr(base64_encode(json_encode($refreshPayload)), '+/', '-_'), '=');
$refreshSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$refreshPayloadEncoded", $jwtSecret, true)), '+/', '-_'), '=');
$refreshTokenJwt = "$jwtHeader.$refreshPayloadEncoded.$refreshSignature";

$userObj = [
    'id' => $dbUserId,
    'nickname' => $nickname,
    'email' => $email,
    'profile_image' => $profileImage,
    'role' => 'user',
    'created_at' => date('c'),
    'is_subscribed' => false,
];

kakaoLog('success', ['dbUserId' => $dbUserId, 'nickname' => $nickname]);

// HTML → localStorage 저장 후 프론트엔드로 이동
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>로그인 처리 중...</title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f5f5f5}.loading{text-align:center}.spinner{width:40px;height:40px;border:4px solid #e0e0e0;border-top-color:#FEE500;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px}@keyframes spin{to{transform:rotate(360deg)}}</style>
</head>
<body>
<div class="loading"><div class="spinner"></div><p>카카오 로그인 처리 중...</p></div>
<script>
try {
    localStorage.setItem("access_token", ' . json_encode($accessTokenJwt) . ');
    localStorage.setItem("refresh_token", ' . json_encode($refreshTokenJwt) . ');
    localStorage.setItem("user", JSON.stringify(' . json_encode($userObj) . '));
} catch(e) { console.error("localStorage error:", e); }
window.location.href = ' . json_encode($frontendBase . '/auth/callback#access_token=' . urlencode($accessTokenJwt) . '&refresh_token=' . urlencode($refreshTokenJwt)) . ';
</script>
</body>
</html>';
