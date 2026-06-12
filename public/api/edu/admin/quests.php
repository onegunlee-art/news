<?php
/**
 * GIST EDU — Admin Quest Management API
 * 
 * GET  /api/edu/admin/quests          — 퀘스트 목록 (draft/approved)
 * POST /api/edu/admin/quests/approve  — 퀘스트 승인
 * POST /api/edu/admin/quests/reject   — 퀘스트 거절
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    eduSendError('Service not configured', 503);
}

if ($method === 'GET') {
    $status = $_GET['status'] ?? 'draft';
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    
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
        $priority = $input['priority'] ?? 'B';
        if (!in_array($priority, ['A', 'B', 'C'], true)) {
            $priority = 'B';
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
            ]);
        } catch (Throwable $e) {
            eduSendError('Approve failed: ' . $e->getMessage(), 500);
        }
    }
    
    if ($action === 'reject') {
        $reason = $input['reason'] ?? '';
        
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
        
        if (isset($input['quest_title'])) $updates['quest_title'] = $input['quest_title'];
        if (isset($input['pro_line'])) $updates['pro_line'] = $input['pro_line'];
        if (isset($input['con_line'])) $updates['con_line'] = $input['con_line'];
        if (isset($input['conflict_summary'])) $updates['conflict_summary'] = $input['conflict_summary'];
        if (isset($input['alignment_summary'])) $updates['alignment_summary'] = $input['alignment_summary'];
        if (isset($input['grade_band'])) $updates['grade_band'] = $input['grade_band'];
        if (isset($input['pilot_priority'])) $updates['pilot_priority'] = $input['pilot_priority'];
        
        if (empty($updates)) {
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
