<?php
declare(strict_types=1);

namespace Youtube;

use Discovery\Agents\LLMClient;
use Youtube\Agents\ScriptwriterAgent;
use Youtube\Agents\VisualAgent;
use Youtube\Agents\NarratorAgent;
use Youtube\Agents\CompositorAgent;
use Youtube\Contracts\Project;

/**
 * Main YouTube Shorts generation pipeline.
 * Orchestrates: Script → Visual → Audio → Video
 */
final class Pipeline
{
    private Reader $reader;
    private ScriptwriterAgent $scriptwriter;
    private VisualAgent $visual;
    private NarratorAgent $narrator;
    private CompositorAgent $compositor;
    private array $config;
    private ?\PDO $pdo;

    public function __construct(
        Reader $reader,
        LLMClient $llm,
        array $config,
        ?\PDO $pdo = null,
    ) {
        $this->reader = $reader;
        $this->scriptwriter = new ScriptwriterAgent($llm, $config);
        $this->visual = new VisualAgent($config);
        $this->narrator = new NarratorAgent($config);
        $this->compositor = new CompositorAgent($config);
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Run full pipeline for a single change.
     * @return array Result with video path and metadata
     */
    public function run(int $changeId): array
    {
        $startTime = microtime(true);
        $logs = [];

        try {
            $project = $this->reader->getChangeById($changeId);
            if ($project === null) {
                throw new \RuntimeException("Change not found: {$changeId}");
            }

            $projectId = $this->saveProject($project);
            $logs[] = ['stage' => 'init', 'status' => 'completed', 'project_id' => $projectId];

            $scenes = $this->scriptwriter->generate($project);
            $project = $project->withScenes($scenes)->withStatus('scripted');
            $this->saveScenes($projectId, $scenes);
            $logs[] = ['stage' => 'script', 'status' => 'completed', 'scenes' => count($scenes)];

            $scenesWithVisuals = $this->visual->generateAll($project);
            $project = $project->withScenes($scenesWithVisuals)->withStatus('visual_ready');
            $this->updateScenes($projectId, $scenesWithVisuals);
            $logs[] = ['stage' => 'visual', 'status' => 'completed'];

            $scenesWithAudio = $this->narrator->generateAll($project);
            $project = $project->withScenes($scenesWithAudio)->withStatus('audio_ready');
            $this->updateScenes($projectId, $scenesWithAudio);
            $logs[] = ['stage' => 'audio', 'status' => 'completed'];

            $videoPath = $this->compositor->compose($project);
            $project = $project->withVideoPath($videoPath)->withStatus('rendered');
            $videoInfo = $this->compositor->getVideoInfo($videoPath);
            $this->saveVideo($projectId, $videoPath, $videoInfo);
            $this->updateProjectStatus($projectId, 'rendered');
            $logs[] = ['stage' => 'render', 'status' => 'completed', 'video_path' => $videoPath];

            $elapsed = (int) ((microtime(true) - $startTime) * 1000);
            $this->logRun($projectId, 'complete', 'completed', $elapsed);

            return [
                'success' => true,
                'project_id' => $projectId,
                'change_id' => $changeId,
                'video_path' => $videoPath,
                'video_url' => $this->getPublicUrl($videoPath),
                'duration_sec' => $videoInfo['duration_sec'] ?? 0,
                'file_size_bytes' => $videoInfo['file_size_bytes'] ?? 0,
                'elapsed_ms' => $elapsed,
                'logs' => $logs,
                'project' => $project->toArray(),
            ];

        } catch (\Throwable $e) {
            $elapsed = (int) ((microtime(true) - $startTime) * 1000);
            $logs[] = ['stage' => 'error', 'message' => $e->getMessage()];

            if (isset($projectId)) {
                $this->updateProjectStatus($projectId, 'failed', $e->getMessage());
                $this->logRun($projectId, 'error', 'failed', $elapsed, $e->getMessage());
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'elapsed_ms' => $elapsed,
                'logs' => $logs,
            ];
        }
    }

    /**
     * Run pipeline for all changes of a date.
     * @return list<array>
     */
    public function runForDate(string $date): array
    {
        $projects = $this->reader->getChangesForDate($date);
        $results = [];

        foreach ($projects as $project) {
            $results[] = $this->run($project->changeId);
        }

        return $results;
    }

    private function saveProject(Project $project): int
    {
        if ($this->pdo === null) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO youtube_projects (change_id, edition_date, title, status) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $project->changeId,
            $project->editionDate,
            $project->title,
            'pending',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function saveScenes(int $projectId, array $scenes): void
    {
        if ($this->pdo === null || $projectId === 0) {
            return;
        }

        foreach ($scenes as $scene) {
            $this->pdo->prepare(
                'INSERT INTO youtube_scenes (project_id, scene_num, visual_type, narration, text_overlay, location) 
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $projectId,
                $scene->sceneNum,
                $scene->visualType,
                $scene->narration,
                json_encode($scene->textOverlay, JSON_UNESCAPED_UNICODE),
                $scene->location,
            ]);
        }
    }

    private function updateScenes(int $projectId, array $scenes): void
    {
        if ($this->pdo === null || $projectId === 0) {
            return;
        }

        foreach ($scenes as $scene) {
            $this->pdo->prepare(
                'UPDATE youtube_scenes SET visual_path = ?, audio_path = ?, duration_ms = ?, updated_at = NOW()
                 WHERE project_id = ? AND scene_num = ?'
            )->execute([
                $scene->visualPath,
                $scene->audioPath,
                $scene->durationMs,
                $projectId,
                $scene->sceneNum,
            ]);
        }
    }

    private function saveVideo(int $projectId, string $videoPath, array $videoInfo): void
    {
        if ($this->pdo === null || $projectId === 0) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO youtube_videos (project_id, version, video_path, duration_sec, file_size_bytes, status)
             VALUES (?, 1, ?, ?, ?, ?)'
        )->execute([
            $projectId,
            $videoPath,
            $videoInfo['duration_sec'] ?? 0,
            $videoInfo['file_size_bytes'] ?? 0,
            'ready',
        ]);
    }

    private function updateProjectStatus(int $projectId, string $status, ?string $error = null): void
    {
        if ($this->pdo === null || $projectId === 0) {
            return;
        }

        $this->pdo->prepare(
            'UPDATE youtube_projects SET status = ?, error_message = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$status, $error, $projectId]);
    }

    private function logRun(int $projectId, string $stage, string $status, int $durationMs, ?string $error = null): void
    {
        if ($this->pdo === null || $projectId === 0) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO youtube_runs (project_id, stage, status, duration_ms, error_message) VALUES (?, ?, ?, ?, ?)'
        )->execute([$projectId, $stage, $status, $durationMs, $error]);
    }

    private function getPublicUrl(string $path): string
    {
        $projectRoot = dirname(__DIR__, 2);
        $relativePath = str_replace($projectRoot, '', $path);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        return '/storage/youtube' . substr($relativePath, strrpos($relativePath, '/youtube') + 8);
    }
}
