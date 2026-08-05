<?php
declare(strict_types=1);

/**
 * GPT 분석 큐 워커 (cron — 매분 실행)
 * ENABLE_AI_ANALYZE_QUEUE=true 일 때 pending job을 CLI로 처리.
 *
 * Usage: php cron/ai_analyze_worker.php
 */

require_once __DIR__ . '/../public/api/lib/aiAnalyzeQueue.php';

try {
    $projectRoot = aiAnalyzeBootstrapEnv();
} catch (Throwable $e) {
    fwrite(STDERR, 'Bootstrap failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if (!aiAnalyzeQueueEnabled()) {
    echo "SKIP: ENABLE_AI_ANALYZE_QUEUE is off\n";
    exit(0);
}

$maxConcurrent = (int) ($_ENV['AI_ANALYZE_MAX_CONCURRENT'] ?? getenv('AI_ANALYZE_MAX_CONCURRENT') ?: 2);
if ($maxConcurrent < 1) {
    $maxConcurrent = 1;
}
if ($maxConcurrent > 3) {
    $maxConcurrent = 3;
}

$counts = aiAnalyzeCountJobsByStatus($projectRoot);
echo 'counts=' . json_encode($counts, JSON_UNESCAPED_UNICODE) . PHP_EOL;

if ($counts['processing'] >= $maxConcurrent) {
    echo "SKIP: max concurrent processing ({$counts['processing']}/{$maxConcurrent})\n";
    exit(0);
}

$claimed = aiAnalyzeClaimNextPendingJob($projectRoot, $maxConcurrent);
if ($claimed === null) {
    echo "NO pending jobs\n";
    exit(0);
}

$jobId = $claimed['job_id'];
$cli = $projectRoot . 'public/api/admin/ai-analyze-cli.php';
$logDir = $projectRoot . 'storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$log = $logDir . '/ai_analyze_worker.log';

$cmd = sprintf(
    'cd %s && nohup php %s %s >> %s 2>&1 &',
    escapeshellarg(rtrim($projectRoot, '/\\')),
    escapeshellarg($cli),
    escapeshellarg($jobId),
    escapeshellarg($log)
);

exec($cmd);
echo "Spawned CLI for job {$jobId}\n";
exit(0);
