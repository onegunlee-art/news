<?php
/**
 * GET — today's quest for authenticated student
 * URL: /api/edu/quests/today.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduTier.php';

handleOptionsRequest();
setCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    eduSendError('GET only', 405);
}

$student = eduRequireStudent();
$code = eduTodayQuestCode($student);
$quest = eduLoadQuestByCode($code);
if ($quest === null) {
    eduSendError('Quest not found: ' . $code, 404);
}

$session = eduActiveSession($student['id']);
$tier = eduTierProgressPayload(eduFetchTierRow($student['id']));

eduSendJson([
    'success' => true,
    'quest' => eduPublicQuestPayload($quest),
    'active_session' => $session ? [
        'session_id' => $session['id'],
        'stage' => $session['stage'],
        'stance' => $session['stance'],
    ] : null,
    'tier' => $tier,
    'ui_steps' => ['찬반 선택', '반론 읽기', '5문장 쓰기', 'XP·티어'],
]);
