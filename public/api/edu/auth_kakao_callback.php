<?php
/**
 * GIST EDU — 카카오 OAuth 콜백
 * GET /api/edu/auth_kakao_callback.php
 *
 * 카카오 인증 후 code → 토큰 교환 → edu_students upsert → raw hex 토큰 발급 (invite/guest와 동일)
 */
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/eduTier.php';

$frontendBase = getenv('EDU_FRONTEND_BASE') ?: 'https://edu.thegist.co.kr';
$eduRedirectUri = getenv('EDU_KAKAO_REDIRECT_URI') ?: 'https://edu.thegist.co.kr/api/edu/auth_kakao_callback.php';

function eduKakaoLog(string $step, $data = null): void
{
    $logDir = dirname(__DIR__, 3) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/edu_kakao_callback.log', json_encode([
        'ts' => date('Y-m-d H:i:s'),
        'step' => $step,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function eduKakaoError(string $code, string $desc): void
{
    global $frontendBase;
    eduKakaoLog('ERROR', ['code' => $code, 'desc' => $desc]);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>로그인 오류</title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#1a1a2e;color:#fff}.box{text-align:center;max-width:400px;padding:2rem}.icon{font-size:48px;margin-bottom:16px}p{color:#aaa;margin-top:12px;font-size:14px}</style></head>
<body><div class="box"><div class="icon">⚠️</div><h2>로그인 실패</h2><p>' . htmlspecialchars($desc) . '</p><p>잠시 후 홈으로 이동합니다...</p></div>
<script>setTimeout(function(){ window.location.href = ' . json_encode($frontendBase . '/edu') . '; }, 3000);</script></body></html>';
    exit;
}

eduKakaoLog('start', ['has_code' => isset($_GET['code'])]);

if (isset($_GET['error'])) {
    eduKakaoError($_GET['error'], $_GET['error_description'] ?? '카카오 인증 에러');
}

$stateFromKakao = $_GET['state'] ?? null;
$stateFromCookie = $_COOKIE['edu_oauth_state'] ?? null;
if ($stateFromKakao && $stateFromCookie && !hash_equals($stateFromCookie, $stateFromKakao)) {
    eduKakaoError('state_mismatch', '보안 검증 실패. 다시 로그인해주세요.');
}
setcookie('edu_oauth_state', '', time() - 3600, '/', '.thegist.co.kr', true, true);

$code = $_GET['code'] ?? null;
if (empty($code)) {
    eduKakaoError('no_code', '인가 코드가 없습니다.');
}

$configPath = null;
$tryPaths = [
    dirname(__DIR__, 2) . '/config/kakao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/kakao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/kakao.php',
];
foreach ($tryPaths as $p) {
    if (file_exists($p)) {
        $configPath = $p;
        break;
    }
}
if (!$configPath) {
    eduKakaoError('config_not_found', '서버 설정 오류');
}

$config = require $configPath;
$eduClientId = getenv('EDU_KAKAO_CLIENT_ID') ?: $config['rest_api_key'];
$eduClientSecret = getenv('EDU_KAKAO_CLIENT_SECRET') ?: $config['client_secret'];

$tokenPostData = [
    'grant_type' => 'authorization_code',
    'client_id' => $eduClientId,
    'redirect_uri' => $eduRedirectUri,
    'code' => $code,
];
if (!empty($eduClientSecret)) {
    $tokenPostData['client_secret'] = $eduClientSecret;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['oauth']['token_url'] ?? 'https://kauth.kakao.com/oauth/token',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($tokenPostData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
]);
$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

eduKakaoLog('token_response', ['http' => $httpCode]);

if ($httpCode !== 200) {
    $err = json_decode($tokenResponse, true);
    eduKakaoError('token_error', $err['error_description'] ?? '토큰 발급 실패');
}

$tokenData = json_decode($tokenResponse, true);
$kakaoAccessToken = $tokenData['access_token'] ?? null;
if (empty($kakaoAccessToken)) {
    eduKakaoError('no_access_token', '액세스 토큰 없음');
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://kapi.kakao.com/v2/user/me',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $kakaoAccessToken],
]);
$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    eduKakaoError('user_info_failed', '사용자 정보 조회 실패');
}

$userData = json_decode($userResponse, true);
$kakaoId = (string) ($userData['id'] ?? '');
if ($kakaoId === '') {
    eduKakaoError('no_kakao_id', '카카오 ID 없음');
}

$kakaoAccount = $userData['kakao_account'] ?? [];
$profile = $kakaoAccount['profile'] ?? [];
$nickname = $profile['nickname'] ?? '학생';
$profileImage = $profile['profile_image_url'] ?? null;
$email = $kakaoAccount['email'] ?? null;

eduKakaoLog('user_info', ['kakao_id' => $kakaoId, 'nickname' => $nickname]);

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduKakaoError('supabase_error', 'EDU 서비스 미설정');
}

// invite/guest와 동일: raw hex 토큰 + SHA256 해시
$rawToken = bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $rawToken);

$existing = $supabase->select('edu_students', 'kakao_id=eq.' . rawurlencode($kakaoId), 1);
$studentId = null;
$isNewUser = false;

if (!empty($existing[0]['id'])) {
    $studentId = $existing[0]['id'];
    $supabase->update('edu_students', 'id=eq.' . $studentId, [
        'display_name' => $nickname,
        'profile_image' => $profileImage,
        'email' => $email,
        'access_token_hash' => $tokenHash,
        'last_active_at' => date('c'),
    ]);
} else {
    $isNewUser = true;
    $inviteCode = 'K-' . strtoupper(bin2hex(random_bytes(4)));

    $insertResult = $supabase->insert('edu_students', [
        'kakao_id' => $kakaoId,
        'display_name' => $nickname,
        'profile_image' => $profileImage,
        'email' => $email,
        'grade_band' => 'high',
        'invite_code' => $inviteCode,
        'access_token_hash' => $tokenHash,
        'status' => 'active',
    ]);
    $studentId = $insertResult[0]['id'] ?? null;

    if ($studentId) {
        $supabase->insert('edu_user_tier', [
            'student_id' => $studentId,
            'tier_id' => 'bronze',
            'xp_current' => 0,
            'streak_days' => 0,
            'streak_freeze_available' => 1,
        ]);
    }
}

if (empty($studentId)) {
    eduKakaoError('db_error', '학생 계정 생성 실패');
}

eduFetchTierRow($studentId);

$studentObj = [
    'id' => $studentId,
    'display_name' => $nickname,
    'grade_band' => $existing[0]['grade_band'] ?? 'high',
    'profile_image' => $profileImage,
    'email' => $email,
    'has_kakao' => true,
];

eduKakaoLog('success', ['student_id' => $studentId, 'is_new' => $isNewUser]);

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>로그인 처리 중...</title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#0D0D0D;color:#fff}.loading{text-align:center}.spinner{width:40px;height:40px;border:4px solid #333;border-top-color:#E8521C;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px}@keyframes spin{to{transform:rotate(360deg)}}</style>
</head>
<body>
<div class="loading"><div class="spinner"></div><p>GIST EDU 로그인 처리 중...</p></div>
<script>
try {
    localStorage.setItem("edu_access_token", ' . json_encode($rawToken) . ');
    localStorage.setItem("edu_student", JSON.stringify(' . json_encode($studentObj, JSON_UNESCAPED_UNICODE) . '));
    localStorage.setItem("edu_display_name", ' . json_encode($nickname) . ');
    localStorage.removeItem("edu_refresh_token");
} catch(e) { console.error(e); }
window.location.href = ' . json_encode($frontendBase . '/edu') . ';
</script>
</body>
</html>';
