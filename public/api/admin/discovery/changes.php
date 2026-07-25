<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $editionId = (int) ($_GET['edition_id'] ?? 0);
    if ($editionId < 1) {
        discoveryError('edition_id required');
    }
    $edition = $repo->findEditionById($editionId);
    if (!$edition) {
        discoveryError('Edition not found', 404);
    }
    discoveryJson([
        'success' => true,
        'data' => [
            'edition' => $edition,
            'changes' => $repo->getChangesForEdition($editionId),
        ],
    ]);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];

if ($method === 'PATCH') {
    $changeId = (int) ($input['id'] ?? 0);
    if ($changeId < 1) {
        discoveryError('id required');
    }
    $fields = [];
    foreach (['title', 'summary', 'category', 'rank', 'status'] as $key) {
        if (array_key_exists($key, $input)) {
            $fields[$key] = $input[$key];
        }
    }
    if (array_key_exists('briefing', $input) && is_array($input['briefing'])) {
        $fields['briefing'] = $input['briefing'];
    }
    $repo->updateChange($changeId, $fields);

    if (isset($input['poll']) && is_array($input['poll'])) {
        $repo->updatePoll(
            $changeId,
            (string) ($input['poll']['question'] ?? ''),
            (array) ($input['poll']['options'] ?? [])
        );
    }

    discoveryJson(['success' => true]);
}

if ($method === 'DELETE') {
    $changeId = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    if ($changeId < 1) {
        discoveryError('id required');
    }
    $repo->deleteChange($changeId);
    discoveryJson(['success' => true]);
}

discoveryError('Method not allowed', 405);
