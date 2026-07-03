<?php
/**
 * /api/edu/admin/operators.php
 * GET  — list operators (with org)
 * POST — create { email, password, display_name?, organization_id, role }
 * Header: X-Edu-Admin-Key
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';
require_once __DIR__ . '/../lib/eduOrganizations.php';

eduRequireAdminKey();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

$orgMap = eduFetchOrganizationsMap($supabase);

if ($method === 'GET') {
    $rows = $supabase->select('edu_operators', 'order=created_at.desc', 200) ?? [];
    $items = [];
    foreach ($rows as $row) {
        $orgId = $row['organization_id'] ?? null;
        $org = is_string($orgId) && $orgId !== '' ? ($orgMap[$orgId] ?? null) : null;
        $items[] = eduFormatOperatorRow($row, $org);
    }
    eduSendJson(['success' => true, 'operators' => $items, 'count' => count($items)]);
}

if ($method === 'POST') {
    $body = eduJsonBody();
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    $displayName = trim((string) ($body['display_name'] ?? ''));
    $orgId = trim((string) ($body['organization_id'] ?? ''));
    $role = trim((string) ($body['role'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        eduSendError('valid email required', 400);
    }
    if (strlen($password) < 8) {
        eduSendError('password must be at least 8 characters', 400);
    }
    if ($orgId === '') {
        eduSendError('organization_id required', 400);
    }
    if (!in_array($role, ['owner', 'teacher'], true)) {
        eduSendError('role must be owner or teacher', 400);
    }

    eduRequireOrganizationId($supabase, $orgId);

    $existing = $supabase->select('edu_operators', 'email=eq.' . rawurlencode($email), 1);
    if (!empty($existing[0]['id'])) {
        eduSendError('email already registered', 409);
    }

    if ($displayName === '') {
        $displayName = $email;
    }

    $inserted = $supabase->insert('edu_operators', [
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => $displayName,
        'status' => 'active',
        'organization_id' => $orgId,
        'role' => $role,
    ]);
    if ($inserted === null || empty($inserted[0])) {
        eduSendError('Failed to create operator', 500);
    }

    $org = $orgMap[$orgId] ?? eduRequireOrganizationId($supabase, $orgId);
    eduSendJson([
        'success' => true,
        'operator' => eduFormatOperatorRow($inserted[0], $org),
    ], 201);
}

eduSendError('Method not allowed', 405);
