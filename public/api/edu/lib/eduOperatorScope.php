<?php
/**
 * GIST EDU — operator org scoping (Phase 4-A)
 *
 * Super scope (all students incl. org NULL):
 *   - operator.organization_id IS NULL (legacy, e.g. test@edu.com until 4-C backfill)
 *   - or email in EDU_OPERATOR_SUPER_EMAILS (comma-separated)
 *
 * Scoped operator (owner/teacher):
 *   - students where organization_id = operator.organization_id
 *   - org NULL students excluded (super only)
 */
declare(strict_types=1);

/** @param array<string, mixed> $operator */
function eduOperatorHasSuperScope(array $operator): bool
{
    $orgId = $operator['organization_id'] ?? null;
    if ($orgId === null || $orgId === '') {
        return true;
    }

    $email = strtolower(trim((string) ($operator['email'] ?? '')));
    if ($email === '') {
        return false;
    }

    $raw = getenv('EDU_OPERATOR_SUPER_EMAILS') ?: '';
    if ($raw === '') {
        return false;
    }

    foreach (explode(',', $raw) as $entry) {
        if (strtolower(trim($entry)) === $email) {
            return true;
        }
    }

    return false;
}

/** Supabase select filter for operator-visible active students. */
function eduOperatorStudentsSelectFilter(array $operator): string
{
    $order = 'order=last_active_at.desc.nullslast,created_at.desc';
    if (eduOperatorHasSuperScope($operator)) {
        return 'status=eq.active&' . $order;
    }

    $opOrg = trim((string) ($operator['organization_id'] ?? ''));
    if ($opOrg === '') {
        return 'status=eq.active&id=eq.00000000-0000-0000-0000-000000000000&' . $order;
    }

    return 'status=eq.active&organization_id=eq.' . rawurlencode($opOrg) . '&' . $order;
}

/** @param array<string, mixed> $operator */
/** @param array<string, mixed> $student */
function eduOperatorCanAccessStudent(array $operator, array $student): bool
{
    if (eduOperatorHasSuperScope($operator)) {
        return true;
    }

    $opOrg = trim((string) ($operator['organization_id'] ?? ''));
    if ($opOrg === '') {
        return false;
    }

    $studentOrg = $student['organization_id'] ?? null;
    if ($studentOrg === null || $studentOrg === '') {
        return false;
    }

    return $opOrg === (string) $studentOrg;
}

/**
 * Load active student or deny (404 — no cross-org leak).
 *
 * @return array<string, mixed>
 */
function eduOperatorRequireStudentAccess(
    \Agents\Services\SupabaseService $supabase,
    array $operator,
    string $studentId
): array {
    $studentId = trim($studentId);
    if ($studentId === '') {
        eduSendError('student_id required', 400);
    }

    $rows = $supabase->select(
        'edu_students',
        'id=eq.' . rawurlencode($studentId) . '&status=eq.active',
        1
    );
    if (empty($rows[0]['id'])) {
        eduSendError('Student not found', 404);
    }

    if (!eduOperatorCanAccessStudent($operator, $rows[0])) {
        eduSendError('Student not found', 404);
    }

    return $rows[0];
}
