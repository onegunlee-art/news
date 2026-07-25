<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

try {
    $deviceKey = discoveryRequireDeviceKey();
    discoveryJson([
        'success' => true,
        'data' => $public->getMyStats($deviceKey),
    ]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
