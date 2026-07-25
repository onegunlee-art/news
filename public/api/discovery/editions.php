<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

$cursor = isset($_GET['cursor']) ? trim((string) $_GET['cursor']) : null;
if ($cursor === '') {
    $cursor = null;
}
$limit = (int) ($_GET['limit'] ?? 20);

try {
    discoveryJson([
        'success' => true,
        'data' => $public->listEditions($cursor, $limit),
    ]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
