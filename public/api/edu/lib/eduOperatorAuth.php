<?php
/**
 * GIST EDU — 운영자 인증 (edu_operators, 본체 users와 분리)
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function eduOperatorTokenHeader(): string
{
    return trim((string) ($_SERVER['HTTP_X_EDU_OPERATOR_TOKEN'] ?? ''));
}

/** @return array<string, mixed> */
function eduRequireOperator(): array
{
    $token = eduOperatorTokenHeader();
    if ($token === '') {
        eduSendError('X-Edu-Operator-Token required', 401);
    }

    $hash = hash('sha256', $token);
    $supabase = eduSupabase();
    if (!$supabase->isConfigured()) {
        eduSendError('EDU service not configured', 503);
    }

    $rows = $supabase->select(
        'edu_operators',
        'access_token_hash=eq.' . rawurlencode($hash) . '&status=eq.active',
        1
    );
    if (empty($rows[0]['id'])) {
        eduSendError('Invalid or inactive operator token', 401);
    }

    return $rows[0];
}

/** @return array<string, mixed>|null */
function eduGetOperatorOptional(): ?array
{
    $token = eduOperatorTokenHeader();
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);
    $supabase = eduSupabase();
    if (!$supabase->isConfigured()) {
        return null;
    }

    $rows = $supabase->select(
        'edu_operators',
        'access_token_hash=eq.' . rawurlencode($hash) . '&status=eq.active',
        1
    );

    return $rows[0] ?? null;
}

/** @return array{token: string, operator: array<string, mixed>}|null */
function eduOperatorLogin(string $email, string $password): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return null;
    }

    $supabase = eduSupabase();
    if (!$supabase->isConfigured()) {
        return null;
    }

    $rows = $supabase->select(
        'edu_operators',
        'email=eq.' . rawurlencode($email) . '&status=eq.active',
        1
    );
    if (empty($rows[0]['id'])) {
        return null;
    }

    $operator = $rows[0];
    $hash = (string) ($operator['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return null;
    }

    $token = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $token);
    $operatorId = (string) $operator['id'];

    $supabase->update('edu_operators', 'id=eq.' . $operatorId, [
        'access_token_hash' => $tokenHash,
        'last_login_at' => date('c'),
    ]);

    return [
        'token' => $token,
        'operator' => [
            'id' => $operatorId,
            'email' => (string) ($operator['email'] ?? ''),
            'display_name' => (string) ($operator['display_name'] ?? ''),
        ],
    ];
}
