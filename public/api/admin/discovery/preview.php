<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    discoveryError('GET only', 405);
}

$query = trim((string) ($_GET['q'] ?? ''));
$days = (int) ($_GET['days'] ?? 30);
$date = $_GET['date'] ?? date('Y-m-d');

if ($query !== '') {
    discoveryJson([
        'success' => true,
        'data' => [
            'results' => $repo->searchChanges($query, max(1, min(30, $days))),
        ],
    ]);
}

$edition = $repo->findEditionByDate($date);
if (!$edition) {
    discoveryJson([
        'success' => true,
        'data' => [
            'edition' => null,
            'changes' => [],
            'editions' => $repo->listEditions(30),
        ],
    ]);
}

discoveryJson([
    'success' => true,
    'data' => [
        'edition' => $edition,
        'changes' => $repo->getChangesForEdition((int) $edition['id']),
        'editions' => $repo->listEditions(30),
    ],
]);
