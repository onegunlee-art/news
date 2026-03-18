<?php
/**
 * 시장 테스트용 프로모션 계정 1개 생성 (1회 실행 권장)
 * GET /api/auth/seed-promo-user.php
 *
 * - 이메일/비밀번호로 로그인 시 구독자와 동일하게 전체 기사 열람 가능
 * - 구독 만료: 실행 시점 기준 1달 후
 * - 완료 후 보안을 위해 이 파일 삭제 권장
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$promoEmail = 'promo@thegist.co.kr';
$promoPassword = 'ThegistPromo2026!';
$expiresAt = (new DateTime('now'))->modify('+1 month')->format('Y-m-d H:i:s');

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => '',
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

// password_hash 컬럼 확인
try {
    $db->query('SELECT password_hash FROM users LIMIT 1');
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $db->exec('ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL');
    } else {
        throw $e;
    }
}

$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$promoEmail]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $db->prepare('UPDATE users SET is_subscribed = 1, subscription_expires_at = ? WHERE id = ?')
        ->execute([$expiresAt, $existing['id']]);
    echo json_encode([
        'success' => true,
        'message' => '프로모션 계정 구독이 1달 연장되었습니다.',
        'email' => $promoEmail,
        'password' => $promoPassword,
        'expires_at' => $expiresAt,
        'login_url' => '/login',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $hash = password_hash($promoPassword, PASSWORD_DEFAULT);
    $db->prepare(
        'INSERT INTO users (email, password_hash, nickname, role, status, is_subscribed, subscription_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$promoEmail, $hash, '프로모션 테스트', 'user', 'active', 1, $expiresAt]);
    echo json_encode([
        'success' => true,
        'message' => '프로모션 계정이 생성되었습니다. 로그인 후 모든 기사를 열람할 수 있으며, 1달 후 만료됩니다.',
        'email' => $promoEmail,
        'password' => $promoPassword,
        'expires_at' => $expiresAt,
        'login_url' => '/login',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
