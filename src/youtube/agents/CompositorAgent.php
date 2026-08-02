<?php
declare(strict_types=1);

namespace Youtube\Agents;

use Youtube\Contracts\Project;
use Youtube\Contracts\Scene;

/**
 * Composes final video from scene images and audio using FFmpeg.
 * Outputs 1080x1920 vertical video for YouTube Shorts.
 */
final class CompositorAgent
{
    private string $ffmpeg;
    private string $ffprobe;
    private int $width;
    private int $height;
    private string $storagePath;
    private array $ffmpegConfig;

    public function __construct(array $config)
    {
        $this->ffmpeg = $config['ffmpeg']['binary'] ?? 'ffmpeg';
        $this->ffprobe = $config['ffmpeg']['ffprobe'] ?? 'ffprobe';
        $this->width = (int) ($config['resolution']['width'] ?? 1080);
        $this->height = (int) ($config['resolution']['height'] ?? 1920);
        $this->storagePath = $config['storage_path'] ?? 'storage/youtube';
        $this->ffmpegConfig = $config['ffmpeg'] ?? [];
    }

    /**
     * Compose final video from scenes.
     * @return string Path to final video
     */
    public function compose(Project $project): string
    {
        $projectPath = $this->getProjectPath($project);
        $this->ensureDirectory($projectPath);

        $sceneClips = $this->createSceneClips($project, $projectPath);
        $concatPath = $this->concatenateClips($sceneClips, $projectPath);
        $finalPath = $this->addSubtitles($concatPath, $project, $projectPath);

        $this->cleanupTempFiles($sceneClips, $concatPath, $finalPath);

        return $finalPath;
    }

    /**
     * @return list<string> Paths to scene clip files
     */
    private function createSceneClips(Project $project, string $projectPath): array
    {
        $clips = [];

        foreach ($project->scenes as $scene) {
            $clipPath = $this->createSceneClip($scene, $projectPath);
            $clips[] = $clipPath;
        }

        return $clips;
    }

    private function createSceneClip(Scene $scene, string $projectPath): string
    {
        $clipPath = $projectPath . "/clip_{$scene->sceneNum}.mp4";
        $imagePath = $scene->visualPath;
        $audioPath = $scene->audioPath;
        $durationSec = ($scene->durationMs ?? 5000) / 1000;

        if (!file_exists($imagePath)) {
            throw new \RuntimeException("CompositorAgent: Missing visual for scene {$scene->sceneNum}: {$imagePath}");
        }

        if (!empty($audioPath) && file_exists($audioPath)) {
            $cmd = sprintf(
                '%s -y -loop 1 -i %s -i %s -c:v %s -tune stillimage -c:a %s -b:a 192k ' .
                '-pix_fmt %s -shortest -t %s %s 2>&1',
                escapeshellcmd($this->ffmpeg),
                escapeshellarg($imagePath),
                escapeshellarg($audioPath),
                $this->ffmpegConfig['video_codec'] ?? 'libx264',
                $this->ffmpegConfig['audio_codec'] ?? 'aac',
                $this->ffmpegConfig['pixel_format'] ?? 'yuv420p',
                $durationSec,
                escapeshellarg($clipPath)
            );
        } else {
            $cmd = sprintf(
                '%s -y -loop 1 -i %s -f lavfi -i anullsrc=r=24000:cl=mono -c:v %s -tune stillimage ' .
                '-c:a %s -b:a 192k -pix_fmt %s -t %s %s 2>&1',
                escapeshellcmd($this->ffmpeg),
                escapeshellarg($imagePath),
                $this->ffmpegConfig['video_codec'] ?? 'libx264',
                $this->ffmpegConfig['audio_codec'] ?? 'aac',
                $this->ffmpegConfig['pixel_format'] ?? 'yuv420p',
                $durationSec,
                escapeshellarg($clipPath)
            );
        }

        $output = shell_exec($cmd);

        if (!file_exists($clipPath)) {
            throw new \RuntimeException("CompositorAgent: Failed to create clip for scene {$scene->sceneNum}. Output: " . ($output ?? 'none'));
        }

        return $clipPath;
    }

    private function concatenateClips(array $clips, string $projectPath): string
    {
        $listPath = $projectPath . '/concat_list.txt';
        $concatPath = $projectPath . '/concat.mp4';

        $listContent = '';
        foreach ($clips as $clip) {
            $listContent .= "file '" . basename($clip) . "'\n";
        }
        file_put_contents($listPath, $listContent);

        $cmd = sprintf(
            'cd %s && %s -y -f concat -safe 0 -i %s -c copy %s 2>&1',
            escapeshellarg($projectPath),
            escapeshellcmd($this->ffmpeg),
            escapeshellarg(basename($listPath)),
            escapeshellarg(basename($concatPath))
        );

        $output = shell_exec($cmd);

        if (!file_exists($concatPath)) {
            throw new \RuntimeException("CompositorAgent: Failed to concatenate clips. Output: " . ($output ?? 'none'));
        }

        return $concatPath;
    }

    private function addSubtitles(string $inputPath, Project $project, string $projectPath): string
    {
        $srtPath = $this->generateSrt($project, $projectPath);
        $finalPath = $projectPath . '/final.mp4';

        $projectRoot = dirname(__DIR__, 3);
        require_once $projectRoot . '/src/youtube/bootstrap.php';
        $fontPath = youtubeResolveFontPath($projectRoot, 'regular');
        $subtitleSize = (int) ($this->ffmpegConfig['subtitle_size'] ?? 52);

        if ($fontPath === '' || !file_exists($fontPath)) {
            copy($inputPath, $finalPath);
            return $finalPath;
        }

        $subtitlesFilter = sprintf(
            "subtitles=%s:force_style='FontName=Noto Sans CJK KR,FontSize=%d,PrimaryColour=&Hffffff,OutlineColour=&H000000,BorderStyle=3,Outline=3,Shadow=1,MarginV=120'",
            str_replace(['\\', ':'], ['\\\\', '\\:'], $srtPath),
            $subtitleSize
        );

        $cmd = sprintf(
            '%s -y -i %s -vf %s -c:a copy %s 2>&1',
            escapeshellcmd($this->ffmpeg),
            escapeshellarg($inputPath),
            escapeshellarg($subtitlesFilter),
            escapeshellarg($finalPath)
        );

        $output = shell_exec($cmd);

        if (!file_exists($finalPath)) {
            copy($inputPath, $finalPath);
        }

        return $finalPath;
    }

    private function generateSrt(Project $project, string $projectPath): string
    {
        $srtPath = $projectPath . '/subtitles.srt';
        $content = '';
        $index = 1;
        $currentTime = 0;

        foreach ($project->scenes as $scene) {
            if (empty(trim($scene->narration))) {
                $currentTime += ($scene->durationMs ?? 5000);
                continue;
            }

            $durationMs = $scene->durationMs ?? 5000;
            $startTime = $this->formatSrtTime($currentTime);
            $endTime = $this->formatSrtTime($currentTime + $durationMs);

            $content .= "{$index}\n";
            $content .= "{$startTime} --> {$endTime}\n";
            $content .= trim($scene->narration) . "\n\n";

            $index++;
            $currentTime += $durationMs;
        }

        file_put_contents($srtPath, $content);
        return $srtPath;
    }

    private function formatSrtTime(int $ms): string
    {
        $hours = (int) floor($ms / 3600000);
        $minutes = (int) floor(($ms % 3600000) / 60000);
        $seconds = (int) floor(($ms % 60000) / 1000);
        $milliseconds = $ms % 1000;

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $seconds, $milliseconds);
    }

    private function cleanupTempFiles(array $clips, string $concatPath, string $finalPath): void
    {
        foreach ($clips as $clip) {
            if ($clip !== $finalPath && file_exists($clip)) {
                @unlink($clip);
            }
        }

        if ($concatPath !== $finalPath && file_exists($concatPath)) {
            @unlink($concatPath);
        }
    }

    /**
     * Get video metadata (duration, file size).
     */
    public function getVideoInfo(string $videoPath): array
    {
        $cmd = sprintf(
            '%s -v error -show_entries format=duration,size -of json %s 2>&1',
            escapeshellcmd($this->ffprobe),
            escapeshellarg($videoPath)
        );

        $output = shell_exec($cmd);
        $data = json_decode($output ?? '{}', true);

        return [
            'duration_sec' => (int) ($data['format']['duration'] ?? 0),
            'file_size_bytes' => (int) ($data['format']['size'] ?? 0),
        ];
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
