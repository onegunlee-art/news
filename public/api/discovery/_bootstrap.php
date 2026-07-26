<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Discovery-Device-Key');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../src/discovery/bootstrap.php';

try {
    $root = discoveryFindProjectRoot();
    discoveryLoadEnv($root);
    discoveryEnsurePublicEnabled($root);
    $pdo = discoveryGetDb($root);
    discoveryEnsureTables($pdo, $root);
    $config = discoveryConfig($root);
    $repo = new Discovery\DiscoveryRepository($pdo);
    $rateLimiter = new Discovery\DiscoveryRateLimiter($pdo);
    $public = new Discovery\DiscoveryPublicService($repo, $rateLimiter, $config);
} catch (Throwable $e) {
    $code = str_contains($e->getMessage(), 'disabled') ? 503 : 500;
    discoveryError($e->getMessage(), $code);
}
