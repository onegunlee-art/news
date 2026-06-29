<?php
/**
 * POST /api/edu/operator/login.php { email, password }
 * EDU 운영자 전용 로그인 (본체 users 무관)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduOperatorAuth.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$body = eduJsonBody();
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

$result = eduOperatorLogin($email, $password);
if ($result === null) {
    eduSendError('이메일 또는 비밀번호가 올바르지 않습니다.', 401);
}

eduSendJson([
    'success' => true,
    'token' => $result['token'],
    'operator' => $result['operator'],
]);
