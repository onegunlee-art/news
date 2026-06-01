<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/backend/bootstrap_intelligence.php';
require_once __DIR__ . '/../src/agents/autoload.php';
require_once __DIR__ . '/../src/backend/autoload.php';

use App\Services\StrategicReportService;

try {
    $root = intelligenceFindProjectRoot();
    intelligenceLoadEnv($root);
    $pdo = intelligenceGetDb($root);
    intelligenceEnsureTables($pdo);
    intelligenceEnsureMoatTables($pdo);
    $week = $argv[1] ?? null;
    $service = intelligenceCreateStrategicReportService($pdo);
    $result = $service->generateForWeek($week);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    if (!($result['success'] ?? false)) {
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
