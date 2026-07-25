<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    discoveryError('POST only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$editionId = (int) ($input['edition_id'] ?? 0);
$date = $input['date'] ?? null;

if ($editionId > 0) {
    $edition = $repo->findEditionById($editionId);
} elseif ($date) {
    $edition = $repo->findEditionByDate($date);
} else {
    discoveryError('edition_id or date required');
}

if (!$edition) {
    discoveryError('Edition not found', 404);
}

$repo->publishEdition((int) $edition['id']);
discoveryJson([
    'success' => true,
    'data' => $repo->findEditionById((int) $edition['id']),
]);
