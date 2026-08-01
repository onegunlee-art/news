<?php
declare(strict_types=1);

namespace Youtube\Agents;

use Agents\Services\GoogleTTSService;
use Youtube\Contracts\Project;
use Youtube\Contracts\Scene;

/**
 * Generates TTS audio for scene narrations using Google TTS.
 * Reuses existing GoogleTTSService from the gist pipeline.
 */
final class NarratorAgent
{
    private GoogleTTSService $tts;
    private string $voice;
    private string $storagePath;

    public function __construct(array $config)
    {
        $this->tts = new GoogleTTSService($config);
        $this->voice = $config['tts']['voice'] ?? 'ko-KR-Neural2-C';
        $this->storagePath = $config['storage_path'] ?? 'storage/youtube';
    }

    /**
     * Generate audio for all scenes with narration.
     * @param Project $project
     * @return list<Scene> Scenes with audio paths and durations filled in
     */
    public function generateAll(Project $project): array
    {
        $projectPath = $this->getProjectPath($project);
        $this->ensureDirectory($projectPath);

        $scenesWithAudio = [];

        foreach ($project->scenes as $scene) {
            if (empty(trim($scene->narration))) {
                $defaultDuration = $this->getDefaultDuration($scene->sceneNum);
                $scenesWithAudio[] = $scene->withAudioPath('', $defaultDuration);
                continue;
            }

            $audioPath = $this->generateAudio($scene, $projectPath);
            $durationMs = $this->getAudioDuration($audioPath);
            $scenesWithAudio[] = $scene->withAudioPath($audioPath, $durationMs);
        }

        return $scenesWithAudio;
    }

    private function generateAudio(Scene $scene, string $projectPath): string
    {
        $text = trim($scene->narration);
        $outputFilename = "scene_{$scene->sceneNum}_audio.wav";
        $outputPath = $projectPath . '/' . $outputFilename;

        if (!$this->tts->isConfigured()) {
            $this->createSilentAudio($outputPath, 5000);
            return $outputPath;
        }

        $url = $this->tts->textToSpeech($text, [
            'voice' => $this->voice,
        ]);

        if ($url === null) {
            $this->createSilentAudio($outputPath, 5000);
            return $outputPath;
        }

        $this->copyAudioFile($url, $outputPath);
        
        return $outputPath;
    }

    private function copyAudioFile(string $sourceUrl, string $destPath): void
    {
        if (str_starts_with($sourceUrl, 'http')) {
            $content = @file_get_contents($sourceUrl);
            if ($content !== false) {
                file_put_contents($destPath, $content);
            }
        } else {
            $projectRoot = dirname(__DIR__, 3);
            $sourcePath = $projectRoot . '/public' . $sourceUrl;
            if (file_exists($sourcePath)) {
                copy($sourcePath, $destPath);
            } elseif (file_exists($sourceUrl)) {
                copy($sourceUrl, $destPath);
            }
        }
    }

    private function getAudioDuration(string $path): int
    {
        if (!file_exists($path)) {
            return 5000;
        }

        $ffprobe = 'ffprobe';
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of csv=p=0 %s 2>&1',
            escapeshellcmd($ffprobe),
            escapeshellarg($path)
        );

        $output = @shell_exec($cmd);
        
        if ($output !== null && is_numeric(trim($output))) {
            return (int) (floatval(trim($output)) * 1000);
        }

        $size = filesize($path);
        return (int) ($size / 48);
    }

    private function createSilentAudio(string $path, int $durationMs): void
    {
        $ffmpeg = 'ffmpeg';
        $duration = $durationMs / 1000;
        
        $cmd = sprintf(
            '%s -y -f lavfi -i anullsrc=r=24000:cl=mono -t %s %s 2>&1',
            escapeshellcmd($ffmpeg),
            $duration,
            escapeshellarg($path)
        );

        @shell_exec($cmd);
    }

    private function getDefaultDuration(int $sceneNum): int
    {
        $defaults = [
            1 => 3000,
            2 => 9000,
            3 => 15000,
            4 => 13000,
            5 => 10000,
            6 => 7000,
        ];

        return $defaults[$sceneNum] ?? 5000;
    }

    private function getProjectPath(Project $project): string
    {
        $dateDir = str_replace('-', '', $project->editionDate);
        return "{$this->storagePath}/{$dateDir}/{$project->changeId}";
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
