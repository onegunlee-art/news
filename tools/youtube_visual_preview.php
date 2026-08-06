<?php
declare(strict_types=1);

/**
 * Quick preview for YouTube map/chart visuals without full pipeline.
 *
 * Usage:
 *   php tools/youtube_visual_preview.php --location="Kyiv, Ukraine"
 *   php tools/youtube_visual_preview.php --location="Moscow, Russia" --chart
 *   php tools/youtube_visual_preview.php --fallback-test
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/youtube/bootstrap.php';

$opts = getopt('', ['location:', 'chart', 'fallback-test', 'output::', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php tools/youtube_visual_preview.php --location=\"Kyiv, Ukraine\"\n";
    exit(0);
}

$config = youtubeGetConfig($projectRoot);
$outputDir = $opts['output'] ?? ($projectRoot . '/storage/youtube/_preview');
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$mapGenerator = new Youtube\Agents\Visuals\MapGenerator($config);
$chartGenerator = new Youtube\Agents\Visuals\ChartGenerator($config);

if (isset($opts['fallback-test'])) {
    $path = $mapGenerator->generate('Unknown Country XYZ', $outputDir);
    echo "Fallback map: {$path}\n";
    exit(0);
}

$location = $opts['location'] ?? 'Kyiv, Ukraine';
$mapPath = $mapGenerator->generate($location, $outputDir);
echo "Map: {$mapPath} (" . filesize($mapPath) . " bytes)\n";

if (isset($opts['chart'])) {
    $chartData = [
        'numbers' => [
            ['value' => '9', 'label' => '명 사망'],
            ['value' => '30+', 'label' => '명 부상'],
        ],
    ];
    $chartPath = $chartGenerator->generate($chartData, $outputDir);
    echo "Chart: {$chartPath} (" . filesize($chartPath) . " bytes)\n";
}
