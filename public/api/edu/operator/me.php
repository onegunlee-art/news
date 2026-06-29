<?php
/**
 * GET /api/edu/operator/me.php — 운영자 세션 확인
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduOperatorAuth.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$operator = eduRequireOperator();

eduSendJson([
    'success' => true,
    'operator' => [
        'id' => (string) ($operator['id'] ?? ''),
        'email' => (string) ($operator['email'] ?? ''),
        'display_name' => (string) ($operator['display_name'] ?? ''),
    ],
]);
