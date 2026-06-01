<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Admin API: JWT + users.role = admin */
function requireAdminApi(PDO $pdo): array
{
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $jwt = decodeJwt($token);
    if (!$jwt || empty($jwt['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '유효하지 않은 토큰입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $adminStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $adminStmt->execute([(int) $jwt['user_id']]);
    $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if (!$adminRow || ($adminRow['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '관리자 권한이 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $jwt;
}
