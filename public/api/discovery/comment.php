<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    discoveryError('POST only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$pollId = (int) ($input['poll_id'] ?? 0);
$body = trim((string) ($input['body'] ?? ''));

if ($pollId < 1) {
    discoveryError('poll_id required');
}

try {
    $deviceKey = discoveryRequireDeviceKey();
    $result = $public->addComment($deviceKey, $pollId, $body);
    discoveryJson(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    discoveryPublicException($e);
}
