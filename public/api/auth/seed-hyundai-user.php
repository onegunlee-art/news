<?php
/**
 * 현대자동차 임원 테스트 계정 생성 (1회 실행용)
 * GET /api/auth/seed-hyundai-user.php?secret=YOUR_SEED_SECRET
 *
 * 계정 정보:
 * - 이메일: test@hyundai.com
 * - 비밀번호: hyundai2026
 * - 역할: user (admin 페이지 접근 불가)
 * - 구독: 활성화 (2099년까지)
 * - 프로필: 현대 로고
 *
 * 완료 후 보안을 위해 이 파일 삭제 권장
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$__envFile = dirname(__DIR__, 3) . '/.env';
if (is_file($__envFile) && is_readable($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__line) {
        $__line = trim($__line);
        if ($__line === '' || ($__line[0] ?? '') === '#') continue;
        if (strpos($__line, '=') !== false) {
            [$__n, $__v] = explode('=', $__line, 2);
            $__n = trim($__n);
            $__v = trim($__v, " \t\"'");
            if ($__n !== '') { putenv("$__n=$__v"); $_ENV[$__n] = $__v; }
        }
    }
}

$seedSecret = getenv('SEED_SECRET') ?: ($_ENV['SEED_SECRET'] ?? null) ?: null;
$reqSecret = $_GET['secret'] ?? ($_SERVER['HTTP_X_SEED_SECRET'] ?? null);
if (!$seedSecret || $reqSecret !== $seedSecret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = 'test@hyundai.com';
$password = 'hyundai2026';
$nickname = 'Hyundai Test';
$role = 'user';
$profileImage = '/images/partners/hyundai-logo.svg';
$isSubscribed = 1;
$subscriptionExpiresAt = '2099-12-31 23:59:59';
$subscriptionPlan = '12m';
$subscriptionStartDate = date('Y-m-d H:i:s');

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'romi4120!',
    'charset' => 'utf8mb4',
];

$configPaths = [
    __DIR__ . '/../../../config/database.php',
    __DIR__ . '/../../../../config/database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config/database.php',
];

foreach ($configPaths as $p) {
    if (is_file($p)) {
        $cfg = require $p;
        if (is_array($cfg)) {
            $dbConfig['host'] = $cfg['host'] ?? $dbConfig['host'];
            $dbConfig['dbname'] = $cfg['database'] ?? $cfg['dbname'] ?? $dbConfig['dbname'];
            $dbConfig['username'] = $cfg['username'] ?? $dbConfig['username'];
            $dbConfig['password'] = $cfg['password'] ?? $dbConfig['password'];
        }
        break;
    }
}

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB 연결 실패: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $db->prepare("SELECT id, role, is_subscribed FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $db->prepare("
        UPDATE users SET 
            role = ?,
            nickname = ?,
            profile_image = ?,
            is_subscribed = ?,
            subscription_expires_at = ?,
            subscription_plan = ?,
            subscription_start_date = ?
        WHERE id = ?
    ")->execute([
        $role,
        $nickname,
        $profileImage,
        $isSubscribed,
        $subscriptionExpiresAt,
        $subscriptionPlan,
        $subscriptionStartDate,
        $existing['id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '현대자동차 테스트 계정이 업데이트되었습니다.',
        'email' => $email,
        'password' => $password,
        'nickname' => $nickname,
        'role' => $role,
        'is_subscribed' => true,
        'subscription_expires_at' => $subscriptionExpiresAt,
        'profile_image' => $profileImage,
        'login_url' => '/login',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("
        INSERT INTO users (
            email, password_hash, nickname, role, status, 
            profile_image, is_subscribed, subscription_expires_at, 
            subscription_plan, subscription_start_date
        ) VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)
    ")->execute([
        $email, 
        $hash, 
        $nickname, 
        $role,
        $profileImage,
        $isSubscribed,
        $subscriptionExpiresAt,
        $subscriptionPlan,
        $subscriptionStartDate
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '현대자동차 테스트 계정이 생성되었습니다.',
        'email' => $email,
        'password' => $password,
        'nickname' => $nickname,
        'role' => $role,
        'is_subscribed' => true,
        'subscription_expires_at' => $subscriptionExpiresAt,
        'profile_image' => $profileImage,
        'login_url' => '/login',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
