<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

$pollId = (int) ($_GET['poll_id'] ?? 0);
if ($pollId < 1) {
    discoveryError('poll_id required');
}

$cursor = isset($_GET['cursor']) ? trim((string) $_GET['cursor']) : null;
if ($cursor === '') {
    $cursor = null;
}
$limit = (int) ($_GET['limit'] ?? 20);

try {
    discoveryJson([
        'success' => true,
        'data' => $public->listComments($pollId, $cursor, $limit),
    ]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
