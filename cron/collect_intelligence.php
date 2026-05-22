<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/backend/bootstrap_intelligence.php';
require_once __DIR__ . '/../src/agents/autoload.php';
require_once __DIR__ . '/../src/backend/autoload.php';

use App\Services\IntelligenceCollectorService;

try {
    $root = intelligenceFindProjectRoot();
    intelligenceLoadEnv($root);
    $pdo = intelligenceGetDb($root);
    intelligenceEnsureTables($pdo);
    $collector = new IntelligenceCollectorService($pdo);
    $result = $collector->runDaily();
    echo json_encode([
        'success' => true,
        'env' => intelligenceEnvDiagnostics($root),
        'db' => intelligenceDbSnapshot($pdo),
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
