<?php
/**
 * GIST EDU — 부모 리포트 공개 URL (토큰)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduParentReportData.php';
require_once __DIR__ . '/eduOperatorScope.php';

function eduParentReportShareBaseUrl(): string
{
    $base = rtrim(getenv('EDU_FRONTEND_BASE') ?: 'https://edu.thegist.co.kr', '/');

    return $base;
}

function eduParentReportSharePublicUrl(string $token): string
{
    return eduParentReportShareBaseUrl() . '/report/' . rawurlencode($token);
}

function eduParentReportShareGenerateToken(): string
{
    return bin2hex(random_bytes(16));
}

function eduParentReportShareSanitizeToken(string $token): string
{
    return preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
}

/** @return array{share_token: string, share_url: string, created: bool} */
function eduParentReportShareCreateOrGet(
    \Agents\Services\SupabaseService $supabase,
    array $operator,
    string $studentId
): array {
    eduOperatorRequireStudentAccess($supabase, $operator, $studentId);

    $existing = $supabase->select(
        'edu_parent_report_shares',
        'student_id=eq.' . $studentId . '&is_active=eq.true&order=created_at.desc',
        1
    );
    if (!empty($existing[0]['share_token'])) {
        $token = (string) $existing[0]['share_token'];

        return [
            'share_token' => $token,
            'share_url' => eduParentReportSharePublicUrl($token),
            'created' => false,
        ];
    }

    $report = eduParentReportBuildPayload($supabase, $studentId, true);
    $token = eduParentReportShareGenerateToken();
    $operatorId = (string) ($operator['id'] ?? '');

    $row = [
        'student_id' => $studentId,
        'operator_id' => $operatorId !== '' ? $operatorId : null,
        'share_token' => $token,
        'report_snapshot' => $report,
        'is_active' => true,
    ];

    $inserted = $supabase->insert('edu_parent_report_shares', $row);
    if (empty($inserted[0]['share_token'])) {
        throw new RuntimeException('Failed to create share link');
    }

    return [
        'share_token' => $token,
        'share_url' => eduParentReportSharePublicUrl($token),
        'created' => true,
    ];
}

/** @return array<string, mixed>|null */
function eduParentReportShareFetchPublic(
    \Agents\Services\SupabaseService $supabase,
    string $token
): ?array {
    $token = eduParentReportShareSanitizeToken($token);
    if (strlen($token) < 16) {
        return null;
    }

    $rows = $supabase->select(
        'edu_parent_report_shares',
        'share_token=eq.' . $token . '&is_active=eq.true',
        1
    );
    if (empty($rows[0])) {
        return null;
    }

    $row = $rows[0];
    if (!empty($row['expires_at'])) {
        $expires = strtotime((string) $row['expires_at']);
        if ($expires !== false && $expires < time()) {
            return null;
        }
    }

    $snapshot = $row['report_snapshot'] ?? null;
    if (!is_array($snapshot) || $snapshot === []) {
        return null;
    }

    $supabase->update('edu_parent_report_shares', 'id=eq.' . $row['id'], [
        'views_count' => ((int) ($row['views_count'] ?? 0)) + 1,
    ]);

    return $snapshot;
}
