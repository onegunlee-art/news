<?php
/**
 * API 요청 로그 (원격 서버용)
 * storage/logs/api.log 에 NDJSON 한 줄씩 추가
 */
function api_log(string $path, string $method, ?int $status = null, ?string $detail = null): void {
    $root = dirname(__DIR__, 3);
    $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;
    $file = $dir . DIRECTORY_SEPARATOR . 'api.log';
    $line = json_encode([
        'ts' => date('Y-m-d\TH:i:sP'),
        'path' => $path,
        'method' => $method,
        'status' => $status,
        'detail' => $detail,
    ], JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
