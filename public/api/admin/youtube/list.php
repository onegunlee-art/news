<?php
declare(strict_types=1);

/**
 * GET /api/admin/youtube/list.php
 * List Discovery changes available for video generation.
 */

require_once __DIR__ . '/_bootstrap.php';

youtubeAdminAuth();

$projectRoot = dirname(__DIR__, 4);

try {
    $pdo = youtubeGetDb($projectRoot);
    $discoveryRepo = new Discovery\DiscoveryRepository($pdo);
    $reader = new Youtube\Reader($discoveryRepo);

    $date = $_GET['date'] ?? date('Y-m-d');
    
    $changes = $reader->getChangesForDate($date);
    
    if (empty($changes)) {
        $changes = $reader->getLatestChanges();
    }

    $existingProjects = [];
    try {
        $stmt = $pdo->query('SELECT change_id, id, status, video_path FROM youtube_projects ORDER BY created_at DESC');
        foreach ($stmt->fetchAll() as $row) {
            $existingProjects[(int) $row['change_id']] = $row;
        }
    } catch (\Throwable $e) {
    }

    $items = [];
    foreach ($changes as $project) {
        $existing = $existingProjects[$project->changeId] ?? null;
        $items[] = [
            'change_id' => $project->changeId,
            'title' => $project->title,
            'edition_date' => $project->editionDate,
            'category' => $project->briefing['category'] ?? 'unknown',
            'has_video' => $existing !== null && !empty($existing['video_path']),
            'video_status' => $existing['status'] ?? null,
            'project_id' => $existing['id'] ?? null,
        ];
    }

    youtubeJsonResponse([
        'success' => true,
        'date' => $date,
        'count' => count($items),
        'items' => $items,
    ]);

} catch (\Throwable $e) {
    youtubeErrorResponse($e->getMessage(), 500);
}
