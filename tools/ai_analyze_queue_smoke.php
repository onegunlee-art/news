<?php
declare(strict_types=1);

/**
 * Phase 1 smoke: 큐 등록 → 워커 claim → CLI 처리 (mock URL 없이 job 파일만 검증)
 * Usage: ENABLE_AI_ANALYZE_QUEUE=true php tools/ai_analyze_queue_smoke.php
 */

require_once __DIR__ . '/../public/api/lib/aiAnalyzeQueue.php';

putenv('ENABLE_AI_ANALYZE_QUEUE=true');
$_ENV['ENABLE_AI_ANALYZE_QUEUE'] = 'true';

$root = aiAnalyzeBootstrapEnv();
$jobId = 'job_smoke_' . bin2hex(random_bytes(4));

aiAnalyzeWriteJob($jobId, [
    'status' => 'pending',
    'action' => 'analyze',
    'url' => 'https://example.com/smoke-test',
    'options' => ['enable_tts' => false, 'enable_interpret' => false, 'enable_learning' => false],
    'created_at' => date('c'),
]);

echo "Created pending job: {$jobId}\n";

$claimed = aiAnalyzeClaimNextPendingJob($root, 2);
if ($claimed === null || ($claimed['job_id'] ?? '') !== $jobId) {
    echo "FAIL: claim did not return expected job\n";
    exit(1);
}

echo "Claimed OK: {$claimed['job_id']} status=" . ($claimed['data']['status'] ?? '?') . "\n";

$read = aiAnalyzeReadJob($jobId, $root);
if (($read['status'] ?? '') !== 'processing') {
    echo "FAIL: job file status after claim\n";
    exit(1);
}

@unlink(aiAnalyzeGetJobFilePath($jobId, $root));
echo "PASS: queue register + claim smoke\n";
