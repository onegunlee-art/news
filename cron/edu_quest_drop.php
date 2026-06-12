<?php
/**
 * GIST EDU — Quest Drop Scheduler
 * 매분 실행 (crontab: * * * * *)
 * 
 * 오후 4시가 되면 approved 퀘스트를 live로 전환
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/agents/autoload.php';
require_once $projectRoot . '/public/api/edu/lib/bootstrap.php';

function dropLog(string $msg, $data = null): void {
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($logDir . '/edu_quest_drop.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

date_default_timezone_set('Asia/Seoul');

$now = new DateTime();
$dayOfWeek = (int)$now->format('w');
$hour = (int)$now->format('G');
$minute = (int)$now->format('i');

$dropDays = [0, 3, 6];
$dropHour = 16;
$dropMinute = 0;

if (!in_array($dayOfWeek, $dropDays, true)) {
    exit(0);
}

if ($hour !== $dropHour || $minute !== $dropMinute) {
    exit(0);
}

dropLog('Drop check triggered', ['day' => $dayOfWeek, 'hour' => $hour]);

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    dropLog('ERROR: Supabase not configured');
    exit(1);
}

$approved = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&pilot_priority=in.(A,B)&live_at=is.null&order=pilot_priority.asc,created_at.asc',
    1
);

if (empty($approved)) {
    dropLog('No approved quests to drop');
    exit(0);
}

$quest = $approved[0];
$questId = $quest['id'];

$liveAt = $now->format('c');
$expiresAt = (clone $now)->modify('+2 days')->format('c');

try {
    $supabase->update('edu_daily_quests', 'id=eq.' . $questId, [
        'live_at' => $liveAt,
        'expires_at' => $expiresAt,
    ]);
    
    dropLog('Quest dropped!', [
        'quest_id' => $questId,
        'code' => $quest['quest_code'],
        'title' => $quest['quest_title'],
        'live_at' => $liveAt,
        'expires_at' => $expiresAt,
    ]);
} catch (Throwable $e) {
    dropLog('Drop error', ['error' => $e->getMessage()]);
    exit(1);
}

dropLog('Drop completed');
