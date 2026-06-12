<?php
/**
 * GIST EDU — Admin Quest Management API
 *
 * GET  /api/edu/admin/quests.php          — 퀘스트 목록 (draft/approved)
 * POST /api/edu/admin/quests.php          — approve / reject / update
 * Header: X-Edu-Admin-Key
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';

eduRequireAdminKey();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

function eduValidateQuestArticles(array $articles): ?string
{
    if (count($articles) < 3) {
        return 'Quest must have at least 3 linked articles';
    }
    $hasPrimary = false;
    foreach ($articles as $a) {
        if (($a['role'] ?? '') === 'primary') {
            $hasPrimary = true;
            break;
        }
    }
    if (!$hasPrimary) {
        return 'Quest must have one primary article';
    }
    return null;
}

if ($method === 'GET') {
    $status = $_GET['status'] ?? 'draft';
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

    $quests = $supabase->select(
        'edu_daily_quests',
        'status=eq.' . rawurlencode($status) . '&order=created_at.desc',
        $limit
    );

    $result = [];
    foreach ($quests as $q) {
        $articles = $supabase->select(
            'edu_quest_articles',
            'quest_id=eq.' . $q['id'] . '&order=sort_order.asc',
            10
        );
        $q['articles'] = $articles;
        $q['article_count'] = count($articles);
        $q['validation'] = eduValidateQuestArticles($articles);
        $result[] = $q;
    }

    eduSendJson([
        'success' => true,
        'quests' => $result,
        'count' => count($result),
    ]);
}

if ($method === 'POST') {
    eduRequirePost();
    $input = eduJsonBody();

    $questId = $input['quest_id'] ?? null;
    if (empty($questId)) {
        eduSendError('quest_id required');
    }

    $action = $input['action'] ?? '';

    if ($action === 'approve') {
        $articles = $supabase->select(
            'edu_quest_articles',
            'quest_id=eq.' . $questId . '&order=sort_order.asc',
            20
        ) ?? [];
        $validationError = eduValidateQuestArticles($articles);
        if ($validationError !== null) {
            eduSendError($validationError);
        }

        $priority = $input['priority'] ?? 'B';
        if (!in_array($priority, ['A', 'B', 'C'], true)) {
            $priority = 'B';
        }

        $quests = $supabase->select('edu_daily_quests', 'id=eq.' . $questId, 1);
        $quest = $quests[0] ?? [];
        if (empty(trim((string) ($quest['conflict_summary'] ?? '')))) {
            eduSendError('conflict_summary is required before approve');
        }

        try {
            $supabase->update('edu_daily_quests', 'id=eq.' . $questId, [
                'status' => 'approved',
                'pilot_priority' => $priority,
                'updated_at' => date('c'),
            ]);

            eduSendJson([
                'success' => true,
                'message' => 'Quest approved',
                'quest_id' => $questId,
                'priority' => $priority,
                'article_count' => count($articles),
            ]);
        } catch (Throwable $e) {
            eduSendError('Approve failed: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'reject') {
        try {
            $supabase->update('edu_daily_quests', 'id=eq.' . $questId, [
                'status' => 'archived',
                'updated_at' => date('c'),
            ]);

            eduSendJson([
                'success' => true,
                'message' => 'Quest rejected',
                'quest_id' => $questId,
            ]);
        } catch (Throwable $e) {
            eduSendError('Reject failed: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'update') {
        $updates = [];

        if (isset($input['quest_title'])) {
            $updates['quest_title'] = $input['quest_title'];
        }
        if (isset($input['pro_line'])) {
            $updates['pro_line'] = $input['pro_line'];
        }
        if (isset($input['con_line'])) {
            $updates['con_line'] = $input['con_line'];
        }
        if (isset($input['conflict_summary'])) {
            $updates['conflict_summary'] = $input['conflict_summary'];
        }
        if (isset($input['alignment_summary'])) {
            $updates['alignment_summary'] = $input['alignment_summary'];
        }
        if (isset($input['grade_band'])) {
            $updates['grade_band'] = $input['grade_band'];
        }
        if (isset($input['pilot_priority'])) {
            $updates['pilot_priority'] = $input['pilot_priority'];
        }

        if ($updates === []) {
            eduSendError('No fields to update');
        }

        $updates['updated_at'] = date('c');

        try {
            $supabase->update('edu_daily_quests', 'id=eq.' . $questId, $updates);

            eduSendJson([
                'success' => true,
                'message' => 'Quest updated',
                'quest_id' => $questId,
            ]);
        } catch (Throwable $e) {
            eduSendError('Update failed: ' . $e->getMessage(), 500);
        }
    }

    eduSendError('Invalid action');
}

eduSendError('Method not allowed', 405);
