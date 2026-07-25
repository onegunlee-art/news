<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    discoveryError('q required');
}

$limit = (int) ($_GET['limit'] ?? 50);

try {
    discoveryJson([
        'success' => true,
        'data' => [
            'results' => $repo->searchPublishedChanges($query, $limit, !empty($config['show_seed'])),
        ],
    ]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
