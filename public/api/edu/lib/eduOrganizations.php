<?php
/**
 * GIST EDU — organization helpers (edu_organizations)
 */
declare(strict_types=1);

function eduNormalizeOrgSlug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function eduValidateOrgType(string $type): bool
{
    return in_array($type, ['academy', 'school'], true);
}

/** @param array<string, mixed> $row */
function eduFormatOrganization(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'type' => (string) ($row['type'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
        'metadata' => is_array($row['metadata'] ?? null) ? $row['metadata'] : (json_decode((string) ($row['metadata'] ?? '{}'), true) ?: []),
        'is_active' => (bool) ($row['is_active'] ?? true),
        'created_at' => $row['created_at'] ?? null,
    ];
}

/** @return array<string, array<string, mixed>> */
function eduFetchOrganizationsMap(\Agents\Services\SupabaseService $supabase): array
{
    $rows = $supabase->select('edu_organizations', 'order=name.asc', 500) ?? [];
    $map = [];
    foreach ($rows as $row) {
        $id = (string) ($row['id'] ?? '');
        if ($id !== '') {
            $map[$id] = eduFormatOrganization($row);
        }
    }
    return $map;
}

function eduRequireOrganizationId(\Agents\Services\SupabaseService $supabase, string $orgId): array
{
    $orgId = trim($orgId);
    if ($orgId === '') {
        eduSendError('organization_id required', 400);
    }
    $rows = $supabase->select('edu_organizations', 'id=eq.' . rawurlencode($orgId), 1);
    if (empty($rows[0]['id'])) {
        eduSendError('Organization not found', 404);
    }
    return eduFormatOrganization($rows[0]);
}

/** @param array<string, mixed> $operator */
function eduFormatOperatorRow(array $operator, ?array $org = null): array
{
    return [
        'id' => (string) ($operator['id'] ?? ''),
        'email' => (string) ($operator['email'] ?? ''),
        'display_name' => (string) ($operator['display_name'] ?? ''),
        'status' => (string) ($operator['status'] ?? ''),
        'organization_id' => $operator['organization_id'] ?? null,
        'role' => $operator['role'] ?? null,
        'organization' => $org,
        'last_login_at' => $operator['last_login_at'] ?? null,
        'created_at' => $operator['created_at'] ?? null,
    ];
}
