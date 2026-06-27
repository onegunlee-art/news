<?php
/**
 * GET — TierProgressCard JSON
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduCoachLevel.php';
require_once __DIR__ . '/../lib/eduConfig.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduRequireStudent();
$coachLevel = eduCoachLevelNormalize((int) ($student['coach_level'] ?? EDU_COACH_LEVEL_L1));
$tier = eduTierProgressPayload(eduFetchTierRow($student['id']), $coachLevel);

eduSendJson([
    'success' => true,
    'tier' => $tier,
    'coach_level' => eduCoachLevelProfilePayload($student),
    'level_debug_allowed' => eduLevelDebugAllowed($student),
]);
