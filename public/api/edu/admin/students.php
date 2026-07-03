<?php
/**
 * /api/edu/admin/students.php
 * GET   — student list (X-Edu-Admin-Key), includes organization_id
 * PATCH — assign org { student_id, organization_id } (null to unassign)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';
require_once __DIR__ . '/../lib/eduOrganizations.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';

eduRequireAdminKey();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

$orgMap = eduFetchOrganizationsMap($supabase);

if ($method === 'GET') {
    $limit = min(200, max(1, (int) ($_GET['limit'] ?? 100)));
    $query = 'status=eq.active&order=last_active_at.desc.nullslast,created_at.desc';

    if (isset($_GET['unassigned']) && $_GET['unassigned'] === '1') {
        $query = 'status=eq.active&organization_id=is.null&order=last_active_at.desc.nullslast,created_at.desc';
    } elseif (!empty($_GET['organization_id'])) {
        $oid = trim((string) $_GET['organization_id']);
        eduRequireOrganizationId($supabase, $oid);
        $query = 'status=eq.active&organization_id=eq.' . rawurlencode($oid)
            . '&order=last_active_at.desc.nullslast,created_at.desc';
    }

    $students = $supabase->select('edu_students', $query, $limit) ?? [];

    $items = [];
    foreach ($students as $student) {
        $studentId = (string) ($student['id'] ?? '');
        if ($studentId === '') {
            continue;
        }

        $completedRows = $supabase->select(
            'edu_quest_sessions',
            'student_id=eq.' . $studentId . '&' . eduSessionStageFilterCompleted(),
            500
        ) ?? [];

        $coachLevel = eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_L1));
        $coachPayload = eduCoachLevelProfilePayload($student);
        $tierRow = eduFetchTierRow($studentId);

        $orgId = $student['organization_id'] ?? null;
        $org = is_string($orgId) && $orgId !== '' ? ($orgMap[$orgId] ?? null) : null;

        $items[] = [
            'id' => $studentId,
            'display_name' => (string) ($student['display_name'] ?? ''),
            'grade_band' => (string) ($student['grade_band'] ?? ''),
            'coach_level' => $coachLevel,
            'coach_label_ko' => $coachPayload['label_ko'],
            'completed_count' => count($completedRows),
            'streak_days' => (int) ($tierRow['streak_days'] ?? 0),
            'last_active_at' => $student['last_active_at'] ?? null,
            'created_at' => $student['created_at'] ?? null,
            'organization_id' => $orgId,
            'organization_name' => $org['name'] ?? null,
        ];
    }

    eduSendJson([
        'success' => true,
        'students' => $items,
        'count' => count($items),
    ]);
}

if ($method === 'PATCH') {
    $body = eduJsonBody();
    $studentId = trim((string) ($body['student_id'] ?? ''));
    if ($studentId === '') {
        eduSendError('student_id required', 400);
    }
    if (!array_key_exists('organization_id', $body)) {
        eduSendError('organization_id required (use null to unassign)', 400);
    }

    $rows = $supabase->select('edu_students', 'id=eq.' . rawurlencode($studentId) . '&status=eq.active', 1);
    if (empty($rows[0]['id'])) {
        eduSendError('Student not found', 404);
    }

    $orgId = $body['organization_id'];
    if ($orgId === null || $orgId === '') {
        $patchOrg = null;
    } else {
        $orgId = trim((string) $orgId);
        eduRequireOrganizationId($supabase, $orgId);
        $patchOrg = $orgId;
    }

    $updated = $supabase->update(
        'edu_students',
        'id=eq.' . rawurlencode($studentId),
        ['organization_id' => $patchOrg]
    );
    if ($updated === null || empty($updated[0])) {
        eduSendError('Failed to assign organization', 500);
    }

    $student = $updated[0];
    $assignedOrgId = $student['organization_id'] ?? null;
    $org = is_string($assignedOrgId) && $assignedOrgId !== ''
        ? ($orgMap[$assignedOrgId] ?? eduRequireOrganizationId($supabase, $assignedOrgId))
        : null;

    eduSendJson([
        'success' => true,
        'student' => [
            'id' => $studentId,
            'display_name' => (string) ($student['display_name'] ?? ''),
            'organization_id' => $assignedOrgId,
            'organization_name' => $org['name'] ?? null,
        ],
    ]);
}

eduSendError('Method not allowed', 405);
