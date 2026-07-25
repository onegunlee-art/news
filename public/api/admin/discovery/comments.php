<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'DELETE') {
    discoveryError('DELETE only', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$commentId = (int) ($input['id'] ?? 0);
if ($commentId < 1) {
    discoveryError('id required');
}

if (!$repo->softDeleteComment($commentId)) {
    discoveryError('Comment not found', 404);
}

discoveryJson(['success' => true]);
