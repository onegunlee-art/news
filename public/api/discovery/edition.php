<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

$date = trim((string) ($_GET['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    discoveryError('date=YYYY-MM-DD required');
}

try {
    $deviceKey = discoveryDeviceKey();
    $data = $public->getEditionByDate($date, $deviceKey);
    if (!$data) {
        discoveryError('Edition not found', 404);
    }
    discoveryJson(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
