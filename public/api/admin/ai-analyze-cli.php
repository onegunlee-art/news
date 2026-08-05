<?php
/**
 * GPT 분석 큐 job 1건 처리 (CLI 전용 — PHP-FPM 워커 미사용)
 * Usage: php public/api/admin/ai-analyze-cli.php <job_id>
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

define('AI_ANALYZE_CLI', true);

require __DIR__ . '/ai-analyze.php';

$jobId = $argv[1] ?? '';
if ($jobId === '') {
    fwrite(STDERR, "Usage: php ai-analyze-cli.php <job_id>\n");
    exit(1);
}

try {
    $job = readJobStatus($jobId);
} catch (Throwable $e) {
    fwrite(STDERR, 'Invalid job_id: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($job === null) {
    fwrite(STDERR, "Job not found: {$jobId}\n");
    exit(1);
}

$status = (string) ($job['status'] ?? '');
if ($status === 'pending') {
    $job['status'] = 'processing';
    $job['started_at'] = date('c');
    writeJobStatus($jobId, $job);
} elseif ($status !== 'processing') {
    fwrite(STDERR, "Job not runnable (status={$status}): {$jobId}\n");
    exit(0);
}

set_time_limit(2700);

$action = (string) ($job['action'] ?? 'analyze');
$options = is_array($job['options'] ?? null) ? $job['options'] : [];

try {
    if ($action === 'analyze_content') {
        $result = analyzeContent(
            (string) ($job['content'] ?? ''),
            (string) ($job['url'] ?? 'https://pasted-content.local/article'),
            (string) ($job['title'] ?? ''),
            $options
        );
    } else {
        $url = (string) ($job['url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Job missing url');
        }
        $result = analyzeUrl($url, $options);
    }

    $result['job_id'] = $jobId;
    $result['status'] = !empty($result['success']) ? 'done' : 'failed';
    writeJobStatus($jobId, $result);
    exit(!empty($result['success']) ? 0 : 1);
} catch (Throwable $e) {
    writeJobStatus($jobId, [
        'status' => 'failed',
        'job_id' => $jobId,
        'success' => false,
        'error' => 'Pipeline 예외: ' . $e->getMessage(),
        'analysis' => null,
    ]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
