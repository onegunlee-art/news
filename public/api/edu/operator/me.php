<?php
/**
 * GET /api/edu/operator/me.php — 운영자 세션 확인
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduOperatorAuth.php';
require_once __DIR__ . '/../lib/eduOrganizations.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$operator = eduRequireOperator();

$orgId = $operator['organization_id'] ?? null;
$orgName = null;
if (is_string($orgId) && $orgId !== '') {
    $supabase = eduSupabase();
    if ($supabase->isConfigured()) {
        $orgRows = $supabase->select('edu_organizations', 'id=eq.' . rawurlencode($orgId), 1);
        if (!empty($orgRows[0]['name'])) {
            $orgName = (string) $orgRows[0]['name'];
        }
    }
}

eduSendJson([
    'success' => true,
    'operator' => [
        'id' => (string) ($operator['id'] ?? ''),
        'email' => (string) ($operator['email'] ?? ''),
        'display_name' => (string) ($operator['display_name'] ?? ''),
        'role' => $operator['role'] ?? null,
        'organization_id' => $orgId,
        'organization_name' => $orgName,
    ],
]);
