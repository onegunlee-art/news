<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $date = $_GET['date'] ?? null;
    if ($date) {
        $edition = $repo->findEditionByDate($date);
        if (!$edition) {
            discoveryError('Edition not found', 404);
        }
        discoveryJson([
            'success' => true,
            'data' => [
                'edition' => $edition,
                'changes' => $repo->getChangesForEdition((int) $edition['id']),
            ],
        ]);
    }

    $editions = $repo->listEditions(30);
    $runs = $repo->listRuns(10);
    discoveryJson([
        'success' => true,
        'data' => [
            'editions' => $editions,
            'runs' => $runs,
        ],
    ]);
}

if ($method !== 'POST') {
    discoveryError('GET or POST only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$action = $input['action'] ?? $_POST['action'] ?? 'generate';

if ($action === 'generate') {
    $date = $input['date'] ?? date('Y-m-d');
    try {
        $result = discoveryPipeline($repo, $config)->run($date);
        discoveryJson([
            'success' => true,
            'data' => $result->toArray(),
        ]);
    } catch (Throwable $e) {
        discoveryError($e->getMessage(), 500);
    }
}

discoveryError('Unknown action');
