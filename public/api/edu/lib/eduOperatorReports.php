<?php
/**
 * GIST EDU — 부모 리포트 운영 API 공통 핸들러
 */
declare(strict_types=1);

require_once __DIR__ . '/eduParentReportData.php';
require_once __DIR__ . '/eduParentReportPdf.php';
require_once __DIR__ . '/eduParentReportShare.php';
require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduTier.php';
require_once __DIR__ . '/eduCoachLevel.php';
require_once __DIR__ . '/eduOperatorScope.php';

function eduOperatorReportsHandle(
    \Agents\Services\SupabaseService $supabase,
    array $operator,
    string $method,
    string $action,
    string $studentId,
    ?array $body = null
): void {
    if ($action === 'students' && $method === 'GET') {
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
        $students = $supabase->select(
            'edu_students',
            eduOperatorStudentsSelectFilter($operator),
            $limit
        ) ?? [];

        $items = [];
        foreach ($students as $student) {
            $sid = (string) ($student['id'] ?? '');
            if ($sid === '') {
                continue;
            }
            $completedRows = $supabase->select(
                'edu_quest_sessions',
                'student_id=eq.' . $sid . '&' . eduSessionStageFilterCompleted(),
                500
            ) ?? [];
            $coachLevel = eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_L1));
            $coachPayload = eduCoachLevelProfilePayload($student);
            $tierRow = eduFetchTierRow($sid);
            $items[] = [
                'id' => $sid,
                'display_name' => (string) ($student['display_name'] ?? ''),
                'grade_band' => (string) ($student['grade_band'] ?? ''),
                'coach_level' => $coachLevel,
                'coach_label_ko' => $coachPayload['label_ko'],
                'completed_count' => count($completedRows),
                'streak_days' => (int) ($tierRow['streak_days'] ?? 0),
                'last_active_at' => $student['last_active_at'] ?? null,
            ];
        }

        eduSendJson(['success' => true, 'students' => $items, 'count' => count($items)]);
    }

    if ($studentId === '' && in_array($action, ['preview', 'pdf', 'share_link'], true)) {
        eduSendError('student_id required');
    }

    if ($action === 'preview') {
        eduOperatorRequireStudentAccess($supabase, $operator, $studentId);
        try {
            $report = eduParentReportBuildPayload($supabase, $studentId, true);
        } catch (InvalidArgumentException $e) {
            eduSendError($e->getMessage(), 404);
        } catch (Throwable $e) {
            error_log('edu operator report preview: ' . $e->getMessage());
            eduSendError('Report preview failed', 500);
        }
        eduSendJson(['success' => true, 'report' => $report]);
    }

    if ($action === 'share_link' && $method === 'POST') {
        try {
            $share = eduParentReportShareCreateOrGet($supabase, $operator, $studentId);
        } catch (InvalidArgumentException $e) {
            eduSendError($e->getMessage(), 404);
        } catch (Throwable $e) {
            error_log('edu operator report share_link: ' . $e->getMessage());
            eduSendError('Share link failed', 500);
        }

        eduSendJson([
            'success' => true,
            'share_url' => $share['share_url'],
            'share_token' => $share['share_token'],
            'created' => $share['created'],
        ]);
    }

    if ($action === 'pdf' && $method === 'POST') {
        eduOperatorRequireStudentAccess($supabase, $operator, $studentId);
        try {
            $report = eduParentReportBuildPayload($supabase, $studentId, true);
            $pdf = eduParentReportRenderPdf($report);
        } catch (InvalidArgumentException $e) {
            eduSendError($e->getMessage(), 404);
        } catch (Throwable $e) {
            error_log('edu operator report pdf: ' . $e->getMessage());
            eduSendError('PDF generation failed', 500);
        }

        $filename = eduParentReportPdfFilename($report);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }

    eduSendError('Unknown action', 404);
}
