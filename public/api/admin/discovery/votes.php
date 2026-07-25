<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    discoveryError('POST only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$pollId = (int) ($input['poll_id'] ?? 0);
$optionIdx = (int) ($input['option_idx'] ?? -1);
$userKey = trim((string) ($input['user_key'] ?? 'admin-preview'));

if ($pollId < 1 || $optionIdx < 0 || $optionIdx > 3) {
    discoveryError('poll_id and option_idx(0-3) required');
}

$repo->recordVote($pollId, $userKey, $optionIdx);
discoveryJson([
    'success' => true,
    'data' => $repo->getPollStats($pollId),
]);
