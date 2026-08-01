<?php
declare(strict_types=1);

/**
 * POST /api/admin/youtube/generate.php
 * Trigger video generation for a change.
 */

require_once __DIR__ . '/_bootstrap.php';

youtubeAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    youtubeErrorResponse('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$changeId = (int) ($input['change_id'] ?? $_POST['change_id'] ?? 0);

if ($changeId <= 0) {
    youtubeErrorResponse('change_id is required');
}

$projectRoot = dirname(__DIR__, 4);

try {
    $pdo = youtubeGetDb($projectRoot);
    $pipeline = youtubeCreatePipeline($projectRoot, $pdo);
    
    $result = $pipeline->run($changeId);
    
    if ($result['success']) {
        youtubeJsonResponse([
            'success' => true,
            'message' => '영상 생성 완료',
            'project_id' => $result['project_id'],
            'video_url' => $result['video_url'] ?? null,
            'duration_sec' => $result['duration_sec'] ?? 0,
            'elapsed_ms' => $result['elapsed_ms'] ?? 0,
        ]);
    } else {
        youtubeErrorResponse($result['error'] ?? '영상 생성 실패', 500);
    }

} catch (\Throwable $e) {
    youtubeErrorResponse($e->getMessage(), 500);
}
