<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../../src/discovery/bootstrap.php';
require_once __DIR__ . '/../../lib/admin_auth.php';

try {
    $root = discoveryFindProjectRoot();
    discoveryLoadEnv($root);
    discoveryEnsureEnabled($root);
    $pdo = discoveryGetDb($root);
    discoveryEnsureTables($pdo, $root);
    requireAdminApi($pdo);
} catch (Throwable $e) {
    discoveryError($e->getMessage(), str_contains($e->getMessage(), 'disabled') ? 403 : 500);
}

$config = discoveryConfig($root);
$repo = new Discovery\DiscoveryRepository($pdo);

function discoveryPipeline(Discovery\DiscoveryRepository $repo, array $config): Discovery\DiscoveryPipeline
{
    $llm = new Discovery\DiscoveryLLMClient(['model' => $config['model'] ?? 'gpt-4o']);
    $agent = new Discovery\DiscoveryAgent($llm, $config);
    $verifier = new Discovery\SourceVerifier();
    return new Discovery\DiscoveryPipeline($repo, $agent, $verifier, $config);
}
