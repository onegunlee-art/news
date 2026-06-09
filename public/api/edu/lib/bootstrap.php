<?php
/**
 * GIST EDU BFF — shared bootstrap (WRITE: edu_* only)
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../lib/cors.php';
require_once __DIR__ . '/../../lib/env_bootstrap.php';

function eduFindProjectRoot(): string
{
    $candidates = [__DIR__ . '/../../../../', __DIR__ . '/../../../../../'];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found');
}

function eduSendJson(array $data, int $code = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function eduSendError(string $msg, int $code = 400): void
{
    eduSendJson(['success' => false, 'error' => $msg], $code);
}

function eduSupabase(): \Agents\Services\SupabaseService
{
    static $svc = null;
    if ($svc === null) {
        $root = eduFindProjectRoot();
        require_once $root . 'src/agents/autoload.php';
        $svc = new \Agents\Services\SupabaseService([]);
    }
    return $svc;
}

function eduRequirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        eduSendError('POST only', 405);
    }
}

function eduJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    return is_array($input) ? $input : [];
}
