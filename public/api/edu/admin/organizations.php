<?php
/**
 * /api/edu/admin/organizations.php
 * GET  — list organizations
 * POST — create { name, type, slug?, metadata? }
 * PATCH — update { id, name?, type?, slug?, metadata?, is_active? }
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

if ($method === 'GET') {
    $rows = $supabase->select('edu_organizations', 'order=created_at.desc', 500) ?? [];
    $items = array_map('eduFormatOrganization', $rows);
    eduSendJson(['success' => true, 'organizations' => $items, 'count' => count($items)]);
}

if ($method === 'POST') {
    $body = eduJsonBody();
    $name = trim((string) ($body['name'] ?? ''));
    $type = trim((string) ($body['type'] ?? ''));
    $slug = eduNormalizeOrgSlug((string) ($body['slug'] ?? $name));
    $metadata = $body['metadata'] ?? [];
    if (!is_array($metadata)) {
        eduSendError('metadata must be object', 400);
    }
    if ($name === '') {
        eduSendError('name required', 400);
    }
    if (!eduValidateOrgType($type)) {
        eduSendError('type must be academy or school', 400);
    }
    if ($slug === '') {
        eduSendError('slug required', 400);
    }

    $dup = $supabase->select('edu_organizations', 'slug=eq.' . rawurlencode($slug), 1);
    if (!empty($dup[0]['id'])) {
        eduSendError('slug already exists', 409);
    }

    $inserted = $supabase->insert('edu_organizations', [
        'name' => $name,
        'type' => $type,
        'slug' => $slug,
        'metadata' => $metadata,
        'is_active' => true,
    ]);
    if ($inserted === null || empty($inserted[0])) {
        eduSendError('Failed to create organization', 500);
    }
    eduSendJson(['success' => true, 'organization' => eduFormatOrganization($inserted[0])], 201);
}

if ($method === 'PATCH') {
    $body = eduJsonBody();
    $id = trim((string) ($body['id'] ?? ''));
    if ($id === '') {
        eduSendError('id required', 400);
    }

    eduRequireOrganizationId($supabase, $id);

    $patch = [];
    if (array_key_exists('name', $body)) {
        $name = trim((string) $body['name']);
        if ($name === '') {
            eduSendError('name cannot be empty', 400);
        }
        $patch['name'] = $name;
    }
    if (array_key_exists('type', $body)) {
        $type = trim((string) $body['type']);
        if (!eduValidateOrgType($type)) {
            eduSendError('type must be academy or school', 400);
        }
        $patch['type'] = $type;
    }
    if (array_key_exists('slug', $body)) {
        $slug = eduNormalizeOrgSlug((string) $body['slug']);
        if ($slug === '') {
            eduSendError('slug cannot be empty', 400);
        }
        $dup = $supabase->select(
            'edu_organizations',
            'slug=eq.' . rawurlencode($slug) . '&id=neq.' . rawurlencode($id),
            1
        );
        if (!empty($dup[0]['id'])) {
            eduSendError('slug already exists', 409);
        }
        $patch['slug'] = $slug;
    }
    if (array_key_exists('metadata', $body)) {
        if (!is_array($body['metadata'])) {
            eduSendError('metadata must be object', 400);
        }
        $patch['metadata'] = $body['metadata'];
    }
    if (array_key_exists('is_active', $body)) {
        $patch['is_active'] = (bool) $body['is_active'];
    }

    if ($patch === []) {
        eduSendError('no fields to update', 400);
    }

    $updated = $supabase->update('edu_organizations', 'id=eq.' . rawurlencode($id), $patch);
    if ($updated === null || empty($updated[0])) {
        eduSendError('Failed to update organization', 500);
    }
    eduSendJson(['success' => true, 'organization' => eduFormatOrganization($updated[0])]);
}

eduSendError('Method not allowed', 405);
