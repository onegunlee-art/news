<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    discoveryError('id required');
}

try {
    $deviceKey = discoveryDeviceKey();
    $data = $public->getPoll($id, $deviceKey);
    if (!$data) {
        discoveryError('Poll not found', 404);
    }
    discoveryJson(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
