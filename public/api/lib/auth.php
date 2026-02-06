<?php
/**
 * API 공통: JWT 검증 및 사용자 ID 조회
 * token.php와 동일한 JWT secret 사용
 */

const JWT_SECRET = 'news-context-jwt-secret-key-2026';

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/', $header, $m)) {
        return $m[1];
    }
    return null;
}

function decodeJwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $payloadB64 = $parts[1];
    $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
    if ($payloadJson === false) return null;
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || empty($payload['exp']) || $payload['exp'] < time()) return null;
    $sig = hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true);
    $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    if (!hash_equals($sigB64, $parts[2])) return null;
    return $payload;
}

/**
 * JWT에서 user_id(kakao_id)를 구한 뒤 users.id로 변환. 없으면 사용자 생성 후 반환.
 */
function getAuthUserId(PDO $pdo): ?int {
    $token = getBearerToken();
    if (!$token) return null;
    $payload = decodeJwt($token);
    if (!$payload || !isset($payload['user_id'])) return null;
    $kakaoId = (string) $payload['user_id'];
    $nickname = $payload['nickname'] ?? '사용자';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE kakao_id = ? LIMIT 1");
    $stmt->execute([$kakaoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO users (kakao_id, nickname, role, status) VALUES (?, ?, 'user', 'active')")->execute([$kakaoId, $nickname]);
    return (int) $pdo->lastInsertId();
}

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $cfg = [
        'host' => 'localhost',
        'dbname' => 'ailand',
        'username' => 'ailand',
        'password' => 'romi4120!',
        'charset' => 'utf8mb4',
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
