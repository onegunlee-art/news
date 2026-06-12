<?php
/**
 * GIST EDU — Quest Curator
 * 수, 토, 일 새벽 3시 실행 (crontab: 0 3 * * 0,3,6)
 *
 * MySQL published 기사 READ → EduQuestFactory → edu_daily_quests (draft)
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/agents/autoload.php';
require_once $projectRoot . '/public/api/edu/lib/bootstrap.php';
require_once $projectRoot . '/public/api/edu/lib/_llm.php';
require_once $projectRoot . '/public/api/edu/lib/eduMysql.php';
require_once $projectRoot . '/public/api/edu/lib/eduAgents.php';

use Services\Edu\EduQuestFactory;

function curatorLog(string $msg, $data = null): void
{
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($logDir . '/edu_quest_curator.log', $line . "\n", FILE_APPEND | LOCK_EX);
    echo $line . "\n";
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$maxCandidates = 5;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $maxCandidates = max(1, (int) substr($arg, 8));
    }
}

curatorLog('Quest Curator started', ['dry_run' => $dryRun, 'limit' => $maxCandidates]);

eduLoadAgents();

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    curatorLog('ERROR: Supabase not configured');
    exit(1);
}

try {
    $pdo = eduMysql();
} catch (Throwable $e) {
    curatorLog('ERROR: MySQL connection failed', ['error' => $e->getMessage()]);
    exit(1);
}

$llm = null;
try {
    $llm = eduLlm();
} catch (Throwable $e) {
    curatorLog('WARN: LLM unavailable, using arc meta only', ['error' => $e->getMessage()]);
}

$factory = new EduQuestFactory($pdo, $supabase, $llm);
$candidates = $factory->discoverCandidates($maxCandidates);

curatorLog('Candidates discovered', ['count' => count($candidates)]);

$created = 0;
foreach ($candidates as $draft) {
    $result = $factory->persistDraft($draft, $dryRun);
    if ($result === null) {
        curatorLog('Skip invalid draft', ['code' => $draft['quest_code'] ?? '']);
        continue;
    }
    curatorLog('Quest draft created', $result);
    $created++;
}

curatorLog('Quest Curator finished', ['created' => $created]);
