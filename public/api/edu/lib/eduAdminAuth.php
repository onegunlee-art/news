<?php
/**
 * GIST EDU Admin API key guard
 */
declare(strict_types=1);

function eduRequireAdminKey(): void
{
    $expected = getenv('EDU_ADMIN_API_KEY') ?: '';
    if ($expected === '') {
        eduSendError('EDU_ADMIN_API_KEY not configured', 503);
    }

    $provided = $_SERVER['HTTP_X_EDU_ADMIN_KEY'] ?? '';
    if ($provided === '' || !hash_equals($expected, $provided)) {
        eduSendError('Unauthorized', 401);
    }
}
