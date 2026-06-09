<?php
/**
 * GET — TierProgressCard JSON
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduTier.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduRequireStudent();
$tier = eduTierProgressPayload(eduFetchTierRow($student['id']));

eduSendJson([
    'success' => true,
    'tier' => $tier,
]);
