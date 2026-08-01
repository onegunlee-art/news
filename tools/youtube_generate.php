<?php
declare(strict_types=1);

/**
 * YouTube Shorts Generation CLI
 * 
 * Usage:
 *   php tools/youtube_generate.php --change-id=123
 *   php tools/youtube_generate.php --date=2026-08-01
 *   php tools/youtube_generate.php --date=2026-08-01 --all
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/src/youtube/bootstrap.php';

echo "=== YouTube Shorts Generator ===\n\n";

youtubeLoadEnv($projectRoot);

$opts = getopt('', ['change-id:', 'date:', 'all', 'help', 'dry-run']);

if (isset($opts['help'])) {
    echo <<<HELP
Usage:
  php tools/youtube_generate.php --change-id=123
    Generate video for a specific change ID

  php tools/youtube_generate.php --date=2026-08-01
    Generate video for the first change of the date

  php tools/youtube_generate.php --date=2026-08-01 --all
    Generate videos for all changes of the date

  php tools/youtube_generate.php --dry-run --change-id=123
    Show what would be generated without actually generating

Options:
  --change-id=N   Specific change ID from discovery_changes
  --date=YYYY-MM-DD   Date to process
  --all           Process all changes for the date
  --dry-run       Preview only, don't generate
  --help          Show this help

HELP;
    exit(0);
}

$changeId = isset($opts['change-id']) ? (int) $opts['change-id'] : 0;
$date = isset($opts['date']) ? (string) $opts['date'] : '';
$all = isset($opts['all']);
$dryRun = isset($opts['dry-run']);

if ($changeId === 0 && $date === '') {
    echo "Error: Either --change-id or --date is required.\n";
    echo "Use --help for usage information.\n";
    exit(1);
}

try {
    echo "Connecting to database...\n";
    $pdo = youtubeGetDb($projectRoot);
    echo "✓ Database connected\n";

    echo "Loading configuration...\n";
    $config = youtubeGetConfig($projectRoot);
    echo "✓ Configuration loaded\n";

    echo "Initializing pipeline...\n";
    $pipeline = youtubeCreatePipeline($projectRoot, $pdo);
    echo "✓ Pipeline ready\n\n";

    if ($changeId > 0) {
        echo "=== Generating video for change ID: {$changeId} ===\n\n";
        
        if ($dryRun) {
            $discoveryRepo = new Discovery\DiscoveryRepository($pdo);
            $reader = new Youtube\Reader($discoveryRepo);
            $project = $reader->getChangeById($changeId);
            
            if ($project === null) {
                echo "Error: Change not found: {$changeId}\n";
                exit(1);
            }
            
            echo "Title: {$project->title}\n";
            echo "Date: {$project->editionDate}\n";
            echo "Briefing fields:\n";
            foreach (['what_changed', 'why_changed', 'why_important', 'future_impact'] as $field) {
                $value = $project->getBriefingField($field);
                $preview = mb_substr($value, 0, 80);
                echo "  - {$field}: {$preview}...\n";
            }
            echo "\n[DRY RUN] Would generate video for this change.\n";
        } else {
            $result = $pipeline->run($changeId);
            printResult($result);
        }
    } elseif ($date !== '') {
        $discoveryRepo = new Discovery\DiscoveryRepository($pdo);
        $reader = new Youtube\Reader($discoveryRepo);
        $projects = $reader->getChangesForDate($date);
        
        if (empty($projects)) {
            echo "No changes found for date: {$date}\n";
            exit(1);
        }
        
        echo "Found " . count($projects) . " changes for {$date}\n\n";
        
        $toProcess = $all ? $projects : [reset($projects)];
        
        foreach ($toProcess as $i => $project) {
            echo "=== [" . ($i + 1) . "/" . count($toProcess) . "] {$project->title} ===\n";
            echo "Change ID: {$project->changeId}\n\n";
            
            if ($dryRun) {
                echo "[DRY RUN] Would generate video.\n\n";
            } else {
                $result = $pipeline->run($project->changeId);
                printResult($result);
                echo "\n";
            }
        }
    }

    echo "\n=== Done ===\n";

} catch (Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if (getenv('DEBUG')) {
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}

function printResult(array $result): void
{
    if ($result['success']) {
        echo "✓ Video generated successfully!\n";
        echo "  Project ID: " . ($result['project_id'] ?? 'N/A') . "\n";
        echo "  Video path: " . ($result['video_path'] ?? 'N/A') . "\n";
        echo "  Duration: " . ($result['duration_sec'] ?? 0) . " seconds\n";
        echo "  File size: " . formatBytes($result['file_size_bytes'] ?? 0) . "\n";
        echo "  Elapsed: " . ($result['elapsed_ms'] ?? 0) . " ms\n";
        
        if (!empty($result['logs'])) {
            echo "\n  Pipeline stages:\n";
            foreach ($result['logs'] as $log) {
                $status = $log['status'] ?? 'unknown';
                $stage = $log['stage'] ?? 'unknown';
                $icon = $status === 'completed' ? '✓' : '✗';
                echo "    {$icon} {$stage}\n";
            }
        }
    } else {
        echo "✗ Video generation failed!\n";
        echo "  Error: " . ($result['error'] ?? 'Unknown error') . "\n";
        echo "  Elapsed: " . ($result['elapsed_ms'] ?? 0) . " ms\n";
    }
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
