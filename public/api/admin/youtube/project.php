<?php
declare(strict_types=1);

/**
 * GET /api/admin/youtube/project.php?id=123
 * Get project details including scenes.
 */

require_once __DIR__ . '/_bootstrap.php';

youtubeAdminAuth();

$projectId = (int) ($_GET['id'] ?? 0);
$changeId = (int) ($_GET['change_id'] ?? 0);

if ($projectId <= 0 && $changeId <= 0) {
    youtubeErrorResponse('id or change_id is required');
}

$projectRoot = dirname(__DIR__, 4);

try {
    $pdo = youtubeGetDb($projectRoot);
    
    if ($projectId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM youtube_projects WHERE id = ?');
        $stmt->execute([$projectId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM youtube_projects WHERE change_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$changeId]);
    }
    
    $project = $stmt->fetch();
    
    if (!$project) {
        youtubeErrorResponse('Project not found', 404);
    }
    
    $projectId = (int) $project['id'];
    
    $sceneStmt = $pdo->prepare('SELECT * FROM youtube_scenes WHERE project_id = ? ORDER BY scene_num');
    $sceneStmt->execute([$projectId]);
    $scenes = $sceneStmt->fetchAll() ?: [];
    
    foreach ($scenes as &$scene) {
        $scene['text_overlay'] = json_decode($scene['text_overlay'] ?? '{}', true);
        if (!empty($scene['visual_path'])) {
            $scene['visual_url'] = str_replace($projectRoot, '', $scene['visual_path']);
        }
        if (!empty($scene['audio_path'])) {
            $scene['audio_url'] = str_replace($projectRoot, '', $scene['audio_path']);
        }
    }
    unset($scene);
    
    $videoStmt = $pdo->prepare('SELECT * FROM youtube_videos WHERE project_id = ? ORDER BY version DESC LIMIT 1');
    $videoStmt->execute([$projectId]);
    $video = $videoStmt->fetch() ?: null;
    
    if ($video && !empty($video['video_path'])) {
        $video['video_url'] = str_replace($projectRoot, '', $video['video_path']);
    }
    
    youtubeJsonResponse([
        'success' => true,
        'project' => $project,
        'scenes' => $scenes,
        'video' => $video,
    ]);

} catch (\Throwable $e) {
    youtubeErrorResponse($e->getMessage(), 500);
}
