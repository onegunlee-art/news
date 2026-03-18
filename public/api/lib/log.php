<?php
/**
 * 통합 로거 — 모든 로그를 storage/logs/ 에 통일
 */

function _getLogDir(): string {
    $root = dirname(__DIR__, 3);
    $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/**
 * API 요청 로그 (NDJSON)
 */
function api_log(string $path, string $method, ?int $status = null, ?string $detail = null): void {
    $dir = _getLogDir();
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

/**
 * 에러 로그 — 채널별 분리 (payment, auth, news, general)
 */
function app_error(string $channel, string $message, $data = null): void {
    $dir = _getLogDir();
    $file = $dir . DIRECTORY_SEPARATOR . "error_{$channel}.log";
    $line = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * 결제 관련 로그
 */
function payment_log(string $message, $data = null): void {
    app_error('payment', $message, $data);
}
