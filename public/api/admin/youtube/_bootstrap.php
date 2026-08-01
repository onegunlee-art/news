<?php
declare(strict_types=1);

/**
 * YouTube Admin API Bootstrap
 */

$projectRoot = dirname(__DIR__, 4);

require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/src/youtube/bootstrap.php';
require_once $projectRoot . '/src/discovery/bootstrap.php';

youtubeLoadEnv($projectRoot);

header('Content-Type: application/json; charset=utf-8');

function youtubeAdminAuth(): void
{
    session_start();
    if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
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
