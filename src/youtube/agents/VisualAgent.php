<?php
declare(strict_types=1);

namespace Youtube\Agents;

use Youtube\Agents\Visuals\MapGenerator;
use Youtube\Agents\Visuals\ChartGenerator;
use Youtube\Agents\Visuals\TextOverlayGenerator;
use Youtube\Agents\Visuals\FixedAssetManager;
use Youtube\Contracts\Project;
use Youtube\Contracts\Scene;

/**
 * Orchestrates visual generation for all 6 scenes.
 * Delegates to specialized generators based on scene type.
 */
final class VisualAgent
{
    private MapGenerator $mapGenerator;
    private ChartGenerator $chartGenerator;
    private TextOverlayGenerator $textGenerator;
    private FixedAssetManager $fixedAssets;
    private string $storagePath;

    public function __construct(array $config)
    {
        $this->mapGenerator = new MapGenerator($config);
        $this->chartGenerator = new ChartGenerator($config);
        $this->textGenerator = new TextOverlayGenerator($config);
        $this->fixedAssets = new FixedAssetManager($config);
        $this->storagePath = $config['storage_path'] ?? 'storage/youtube';
    }

    /**
     * Generate visuals for all scenes in a project.
     * @param Project $project
     * @return list<Scene> Scenes with visual paths filled in
     */
    public function generateAll(Project $project): array
    {
        $projectPath = $this->getProjectPath($project);
        $this->ensureDirectory($projectPath);

        $scenesWithVisuals = [];

        foreach ($project->scenes as $scene) {
            $visualPath = $this->generateForScene($scene, $project, $projectPath);
            $scenesWithVisuals[] = $scene->withVisualPath($visualPath);
        }

        return $scenesWithVisuals;
    }

    private function generateForScene(Scene $scene, Project $project, string $projectPath): string
    {
        return match ($scene->visualType) {
            'fixed' => $this->generateFixed($scene),
            'map' => $this->generateMap($scene, $projectPath),
            'text' => $this->generateText($scene, $project, $projectPath),
            'chart' => $this->generateChart($scene, $projectPath),
            default => $this->generateDefault($scene, $projectPath),
        };
    }

    private function generateFixed(Scene $scene): string
    {
        if ($scene->sceneNum === 1) {
            return $this->fixedAssets->getOpeningScreen();
        }
        
        return $this->fixedAssets->getEndingScreen();
    }

    private function generateMap(Scene $scene, string $projectPath): string
    {
        $location = $scene->location ?? 'Unknown';
        return $this->mapGenerator->generate($location, $projectPath);
    }

    private function generateText(Scene $scene, Project $project, string $projectPath): string
    {
        $title = match ($scene->sceneNum) {
            3 => '왜 중요한가',
            4 => '앞으로의 전망',
            default => '핵심 포인트',
        };
        
        return $this->textGenerator->generate(
            $scene->sceneNum,
            $scene->textOverlay,
            $title,
            $projectPath
        );
    }

    private function generateChart(Scene $scene, string $projectPath): string
    {
        return $this->chartGenerator->generate($scene->textOverlay, $projectPath);
    }

    private function generateDefault(Scene $scene, string $projectPath): string
    {
        return $this->textGenerator->generate(
            $scene->sceneNum,
            $scene->textOverlay,
            '정보',
            $projectPath
        );
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
