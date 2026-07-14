<?php
/**
 * GIST EDU — active 학생 CSV export (CLI + 운영자 API 공통)
 *
 * kakao_id = 카카오 OAuth 내부 숫자 ID (친구추가용 카톡 아이디 아님)
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduOperatorScope.php';

/** @return list<array<string, mixed>> */
function eduStudentsExportFetchAll(\Agents\Services\SupabaseService $sb, string $table, string $query, int $pageSize = 500): array
{
    $all = [];
    $offset = 0;
    while (true) {
        $rows = $sb->select($table, $query . '&offset=' . $offset, $pageSize) ?? [];
        if ($rows === []) {
            break;
        }
        foreach ($rows as $row) {
            $all[] = $row;
        }
        if (count($rows) < $pageSize) {
            break;
        }
        $offset += $pageSize;
    }

    return $all;
}

/** Supabase filter for export (respects operator org scope). */
function eduStudentsExportSelectFilter(array $operator): string
{
    $fields = 'select=id,display_name,grade_band,email,kakao_id,invite_code,created_at,last_active_at,coach_level,organization_id';
    $order = 'order=created_at.asc';

    if (eduOperatorHasSuperScope($operator)) {
        return 'status=eq.active&' . $fields . '&' . $order;
    }

    $opOrg = trim((string) ($operator['organization_id'] ?? ''));
    if ($opOrg === '') {
        return 'status=eq.active&id=eq.00000000-0000-0000-0000-000000000000&' . $fields . '&' . $order;
    }

    return 'status=eq.active&organization_id=eq.' . rawurlencode($opOrg) . '&' . $fields . '&' . $order;
}

/** Full active students export (CLI — no org filter). */
function eduStudentsExportSelectFilterAll(): string
{
    return 'status=eq.active&select=id,display_name,grade_band,email,kakao_id,invite_code,created_at,last_active_at,coach_level,organization_id&order=created_at.asc';
}

function eduStudentsExportSourceType(string $inviteCode, ?string $kakaoId): string
{
    if (str_starts_with($inviteCode, 'SEED-GIST-')) {
        return 'gist_seed';
    }
    if (str_starts_with($inviteCode, 'SEED-SYNTH-')) {
        return 'synth_seed';
    }
    if (str_starts_with($inviteCode, 'GUEST-')) {
        return 'guest';
    }
    if (str_starts_with($inviteCode, 'K-') || ($kakaoId !== null && $kakaoId !== '')) {
        return 'kakao';
    }

    return 'other';
}

function eduStudentsExportHasContact(?string $email, ?string $kakaoId): string
{
    $email = trim((string) $email);
    $kakaoId = trim((string) $kakaoId);

    return ($email !== '' || $kakaoId !== '') ? 'Y' : 'N';
}

function eduStudentsExportCsvField(mixed $value): string
{
    $text = (string) $value;
    if (str_contains($text, '"') || str_contains($text, ',') || str_contains($text, "\n") || str_contains($text, "\r")) {
        return '"' . str_replace('"', '""', $text) . '"';
    }

    return $text;
}

/**
 * @return array{csv: string, summary: array<string, int>, filename: string}
 */
function eduStudentsExportBuild(\Agents\Services\SupabaseService $supabase, string $studentQuery): array
{
    $students = eduStudentsExportFetchAll($supabase, 'edu_students', $studentQuery, 500);

    $completedFilter = eduSessionStageFilterCompleted();
    $completedByStudent = [];
    foreach ($students as $student) {
        $sid = (string) ($student['id'] ?? '');
        if ($sid === '') {
            continue;
        }
        $rows = $supabase->select(
            'edu_quest_sessions',
            'student_id=eq.' . rawurlencode($sid) . '&' . $completedFilter,
            500
        ) ?? [];
        $completedByStudent[$sid] = count($rows);
    }

    $headers = [
        'no',
        'display_name',
        'grade_band',
        'source_type',
        'email',
        'kakao_id',
        'has_contact',
        'completed_count',
        'created_at',
        'last_active_at',
        'invite_code',
        'student_id',
    ];

    $lines = [implode(',', $headers)];
    $summary = [
        'active' => count($students),
        'email_filled' => 0,
        'kakao_filled' => 0,
        'has_contact' => 0,
        'synth_seed' => 0,
        'gist_seed' => 0,
        'guest' => 0,
        'kakao' => 0,
        'other' => 0,
    ];

    foreach ($students as $i => $student) {
        $sid = (string) ($student['id'] ?? '');
        $invite = (string) ($student['invite_code'] ?? '');
        $email = trim((string) ($student['email'] ?? ''));
        $kakaoId = trim((string) ($student['kakao_id'] ?? ''));
        $sourceType = eduStudentsExportSourceType($invite, $kakaoId !== '' ? $kakaoId : null);

        if ($email !== '') {
            $summary['email_filled']++;
        }
        if ($kakaoId !== '') {
            $summary['kakao_filled']++;
        }
        if (eduStudentsExportHasContact($email, $kakaoId) === 'Y') {
            $summary['has_contact']++;
        }
        if (isset($summary[$sourceType])) {
            $summary[$sourceType]++;
        } else {
            $summary['other']++;
        }

        $row = [
            (string) ($i + 1),
            (string) ($student['display_name'] ?? ''),
            (string) ($student['grade_band'] ?? ''),
            $sourceType,
            $email,
            $kakaoId,
            eduStudentsExportHasContact($email, $kakaoId),
            (string) ($completedByStudent[$sid] ?? 0),
            (string) ($student['created_at'] ?? ''),
            (string) ($student['last_active_at'] ?? ''),
            $invite,
            $sid,
        ];
        $lines[] = implode(',', array_map('eduStudentsExportCsvField', $row));
    }

    $csvBody = implode("\n", $lines) . "\n";
    $csv = "\xEF\xBB\xBF" . $csvBody;

    return [
        'csv' => $csv,
        'summary' => $summary,
        'filename' => 'gistudy-students-' . date('Ymd') . '.csv',
    ];
}

function eduStudentsExportSummaryLine(array $summary): string
{
    return 'active=' . ($summary['active'] ?? 0)
        . ' email_filled=' . ($summary['email_filled'] ?? 0)
        . ' kakao_filled=' . ($summary['kakao_filled'] ?? 0)
        . ' has_contact=' . ($summary['has_contact'] ?? 0)
        . ' synth_seed=' . ($summary['synth_seed'] ?? 0)
        . ' gist_seed=' . ($summary['gist_seed'] ?? 0)
        . ' guest=' . ($summary['guest'] ?? 0)
        . ' kakao=' . ($summary['kakao'] ?? 0)
        . ' other=' . ($summary['other'] ?? 0);
}
