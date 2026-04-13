<?php
/**
 * API 공통: JWT 검증 및 사용자 ID 조회
 * config/app.php의 jwt_secret 사용 (callback.php, AuthController와 동일)
 */

require_once __DIR__ . '/env_bootstrap.php';

function getJwtSecret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $tryPaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/config/app.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config/app.php',
        __DIR__ . '/../../../config/app.php',
        __DIR__ . '/../../../../config/app.php',
    ];
    foreach ($tryPaths as $p) {
        if (file_exists($p)) {
            $config = require $p;
            $secret = $config['security']['jwt_secret'] ?? '';
            if ($secret !== '') {
                return $secret;
            }
            break;
        }
    }
    $secret = getenv('JWT_SECRET') ?: '';
    if ($secret !== '') {
        return $secret;
    }
    throw new RuntimeException('JWT secret is not configured. Set JWT_SECRET in .env.');
}

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
    $sig = hash_hmac('sha256', $parts[0] . '.' . $parts[1], getJwtSecret(), true);
    $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    if (!hash_equals($sigB64, $parts[2])) return null;
    return $payload;
}

/**
 * JWT에서 user_id를 구해 users.id로 반환.
 * user_id는 DB users.id (callback.php/token.php 발급 JWT와 동일). 없으면 kakao_id로 조회/생성 시도.
 */
function getAuthUserId(PDO $pdo): ?int {
    $token = getBearerToken();
    if (!$token) return null;
    $payload = decodeJwt($token);
    if (!$payload || !isset($payload['user_id'])) return null;
    $userId = (int) $payload['user_id'];
    $nickname = $payload['nickname'] ?? '사용자';
    // 1) user_id를 DB users.id로 직접 사용 (카카오 콜백 JWT와 일치)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int) $row['id'];
    // 2) fallback: payload에 kakao_id가 있으면 기존 방식으로 조회/생성
    $kakaoId = isset($payload['kakao_id']) ? (string) $payload['kakao_id'] : null;
    if ($kakaoId !== null) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE kakao_id = ? LIMIT 1");
        $stmt->execute([$kakaoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int) $row['id'];
        $pdo->prepare("INSERT INTO users (kakao_id, nickname, role, status) VALUES (?, ?, 'user', 'active')")->execute([$kakaoId, $nickname]);
        return (int) $pdo->lastInsertId();
    }
    return null;
}

/**
 * config/app.php를 한 번만 로드해 캐싱 (카카오 PHP 등에서도 사용)
 */
function getAppConfig(): array {
    static $cfg;
    if ($cfg !== null) return $cfg;
    $tryPaths = [
        __DIR__ . '/../../../config/app.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config/app.php',
        $_SERVER['DOCUMENT_ROOT'] . '/config/app.php',
    ];
    foreach ($tryPaths as $p) {
        if (is_file($p)) { $cfg = require $p; return $cfg; }
    }
    $cfg = [];
    return $cfg;
}

/**
 * 리프레시 토큰 TTL(초). config/app.php jwt_refresh_expiry 기준.
 */
function getRefreshTtlSeconds(): int {
    return (int)(getAppConfig()['security']['jwt_refresh_expiry'] ?? 15552000);
}

/**
 * HS256 JWT 생성 — callback.php/token.php 수동 조립 통합용
 */
function createJwtToken(string $secret, array $payload): string {
    $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
    $body   = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $sig    = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $secret, true)), '+/', '-_'), '=');
    return "$header.$body.$sig";
}

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    
    $tryPaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config/database.php',
        __DIR__ . '/../../../config/database.php',
        __DIR__ . '/../../../../config/database.php',
    ];
    
    $cfg = null;
    foreach ($tryPaths as $p) {
        if (file_exists($p)) {
            $cfg = require $p;
            break;
        }
    }
    
    if (!$cfg) {
        $cfg = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'database' => getenv('DB_DATABASE') ?: 'ailand',
            'username' => getenv('DB_USERNAME') ?: 'ailand',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ];
    }
    
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        $cfg['host'] ?? 'localhost',
        $cfg['database'] ?? $cfg['dbname'] ?? 'ailand',
        $cfg['charset'] ?? 'utf8mb4'
    );
    
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
