<?php
/**
 * GIST EDU — student auth via X-Edu-Token (pilot invite redeem)
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function eduRequireStudent(): array
{
    $token = $_SERVER['HTTP_X_EDU_TOKEN'] ?? '';
    if ($token === '') {
        eduSendError('X-Edu-Token required', 401);
    }

    $hash = hash('sha256', $token);
    $supabase = eduSupabase();
    if (!$supabase->isConfigured()) {
        eduSendError('EDU service not configured', 503);
    }

    $rows = $supabase->select(
        'edu_students',
        'access_token_hash=eq.' . rawurlencode($hash) . '&status=eq.active',
        1
    );
    if (empty($rows[0]['id'])) {
        eduSendError('Invalid or inactive student token', 401);
    }

    $student = $rows[0];
    $supabase->update('edu_students', 'id=eq.' . $student['id'], [
        'last_active_at' => date('c'),
    ]);

    return $student;
}

function eduGetStudentOptional(): ?array
{
    $token = $_SERVER['HTTP_X_EDU_TOKEN'] ?? '';
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);
    $supabase = eduSupabase();
    if (!$supabase->isConfigured()) {
        return null;
    }

    $rows = $supabase->select(
        'edu_students',
        'access_token_hash=eq.' . rawurlencode($hash) . '&status=eq.active',
        1
    );
    if (empty($rows[0]['id'])) {
        return null;
    }

    $student = $rows[0];
    $supabase->update('edu_students', 'id=eq.' . $student['id'], [
        'last_active_at' => date('c'),
    ]);

    return $student;
}

function eduStudentTier(string $studentId): array
{
    require_once __DIR__ . '/eduTier.php';
    return eduFetchTierRow($studentId);
}
