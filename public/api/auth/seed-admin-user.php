<?php
/**
 * 관리자 계정 생성/업그레이드 (1회 실행용)
 * GET /api/auth/seed-admin-user.php
 * GET /api/auth/seed-admin-user.php?email=your@email.com
 *
 * 완료 후 보안을 위해 이 파일 삭제 권장
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

$email = trim($_GET['email'] ?? 'test@test.com');
if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'email 파라미터가 필요합니다. 예: ?email=your@email.com',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$password = 'Test1234!';

// DB 설정 로드 (배포/로컬 경로 대응)
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

// password_hash 컬럼 확인
try {
    $db->query("SELECT password_hash FROM users LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $db->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
    } else {
        throw $e;
    }
}

$stmt = $db->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$existing['id']]);
    echo json_encode([
        'success' => true,
        'message' => $email . ' 계정이 관리자로 업그레이드되었습니다.',
        'email' => $email,
        'password' => $password,
        'admin_url' => '/admin',
        'login_url' => '/login',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (email, password_hash, nickname, role, status) VALUES (?, ?, '관리자', 'admin', 'active')")
        ->execute([$email, $hash]);
    echo json_encode([
        'success' => true,
        'message' => '관리자 계정이 생성되었습니다.',
        'email' => $email,
        'password' => $password,
        'admin_url' => '/admin',
        'login_url' => '/login',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
