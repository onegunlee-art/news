<?php
declare(strict_types=1);

/**
 * YouTube Admin API Bootstrap
 */

$projectRoot = dirname(__DIR__, 4);

require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/src/youtube/bootstrap.php';
require_once $projectRoot . '/src/discovery/bootstrap.php';
require_once __DIR__ . '/../../lib/admin_auth.php';

youtubeLoadEnv($projectRoot);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function youtubeAdminAuth(): void
{
    global $projectRoot;
    $pdo = youtubeGetDb($projectRoot);
    requireAdminApi($pdo);
}

function youtubeJsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function youtubeErrorResponse(string $message, int $status = 400): void
{
    youtubeJsonResponse(['error' => $message], $status);
}
