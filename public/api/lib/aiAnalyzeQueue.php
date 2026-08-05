<?php
/**
 * GPT 분석 job 큐 (파일 기반 storage/jobs/*.json)
 * ENABLE_AI_ANALYZE_QUEUE=true 일 때 웹은 pending 등록만, CLI 워커가 처리.
 */
declare(strict_types=1);

function aiAnalyzeQueueEnabled(): bool
{
    $v = $_ENV['ENABLE_AI_ANALYZE_QUEUE'] ?? getenv('ENABLE_AI_ANALYZE_QUEUE');
    if ($v === false || $v === null || $v === '') {
        return false;
    }
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
}

function aiAnalyzeFindProjectRoot(): string
{
    $rawCandidates = [
        __DIR__ . '/../../../',
        __DIR__ . '/../../',
        __DIR__ . '/../',
    ];
    foreach ($rawCandidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    $dir = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        $dir = dirname($dir);
        if (file_exists($dir . '/src/agents/autoload.php')) {
            return rtrim($dir, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found for aiAnalyzeQueue');
}

function aiAnalyzeLoadEnvFile(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

function aiAnalyzeBootstrapEnv(?string $projectRoot = null): string
{
    $root = $projectRoot ?? aiAnalyzeFindProjectRoot();
    foreach ([
        $root . 'env.txt',
        $root . '.env',
        $root . '.env.production',
        dirname($root) . '/.env',
    ] as $f) {
        if (aiAnalyzeLoadEnvFile($f)) {
            break;
        }
    }
    return $root;
}

function aiAnalyzeGetJobsDir(?string $projectRoot = null): string
{
    $root = $projectRoot ?? aiAnalyzeFindProjectRoot();
    $dir = rtrim($root, '/\\') . '/storage/jobs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/';
}

function aiAnalyzeGetJobFilePath(string $jobId, ?string $projectRoot = null): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);
    if ($safe !== $jobId || strlen($jobId) > 64) {
        throw new InvalidArgumentException('Invalid job_id');
    }
    return aiAnalyzeGetJobsDir($projectRoot) . $jobId . '.json';
}

function aiAnalyzeWriteJob(string $jobId, array $data, ?string $projectRoot = null): void
{
    $path = aiAnalyzeGetJobFilePath($jobId, $projectRoot);
    $data['updated_at'] = date('c');
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function aiAnalyzeReadJob(string $jobId, ?string $projectRoot = null): ?array
{
    $path = aiAnalyzeGetJobFilePath($jobId, $projectRoot);
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : null;
}

/** @return array<string, int> */
function aiAnalyzeCountJobsByStatus(?string $projectRoot = null): array
{
    $counts = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];
    $dir = aiAnalyzeGetJobsDir($projectRoot);
    foreach (glob($dir . 'job_*.json') ?: [] as $path) {
        $raw = @file_get_contents($path);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            continue;
        }
        $st = (string) ($data['status'] ?? '');
        if (isset($counts[$st])) {
            $counts[$st]++;
        }
    }
    return $counts;
}

/**
 * pending job 1건 claim → processing. null if 없음.
 * @return array{job_id: string, data: array}|null
 */
function aiAnalyzeClaimNextPendingJob(?string $projectRoot = null, int $maxConcurrent = 2): ?array
{
    $root = $projectRoot ?? aiAnalyzeFindProjectRoot();
    $counts = aiAnalyzeCountJobsByStatus($root);
    if ($counts['processing'] >= $maxConcurrent) {
        return null;
    }

    $dir = aiAnalyzeGetJobsDir($root);
    $files = glob($dir . 'job_*.json') ?: [];
    usort($files, static fn($a, $b) => filemtime($a) <=> filemtime($b));

    foreach ($files as $path) {
        $fp = fopen($path, 'c+');
        if ($fp === false) {
            continue;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            continue;
        }
        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'pending') {
            flock($fp, LOCK_UN);
            fclose($fp);
            continue;
        }
        $data['status'] = 'processing';
        $data['started_at'] = date('c');
        $data['updated_at'] = date('c');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $encoded !== false ? $encoded : '{}');
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $jobId = basename($path, '.json');
        return ['job_id' => $jobId, 'data' => $data];
    }

    return null;
}
