<?php
/**
 * 통합 로거 — 모든 로그를 storage/logs/ 에 통일
 * request_id: 요청 단위 고유 ID로 프론트-백엔드 로그를 관통 추적
 */

function _getLogDir(): string {
    $root = dirname(__DIR__, 3);
    $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/** 요청 단위 고유 ID (같은 요청 내에서는 동일 값 반환) */
function get_request_id(): string {
    static $id = null;
    if ($id === null) {
        $id = $_SERVER['HTTP_X_REQUEST_ID'] ?? substr(bin2hex(random_bytes(8)), 0, 16);
    }
    return $id;
}

/**
 * API 요청 로그 (NDJSON)
 */
function api_log(string $path, string $method, ?int $status = null, ?string $detail = null, ?int $userId = null): void {
    $dir = _getLogDir();
    $file = $dir . DIRECTORY_SEPARATOR . 'api.log';
    $entry = [
        'ts' => date('Y-m-d\TH:i:sP'),
        'rid' => get_request_id(),
        'path' => $path,
        'method' => $method,
        'status' => $status,
    ];
    if ($userId !== null) $entry['uid'] = $userId;
    if ($detail !== null) $entry['detail'] = $detail;
    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * 에러 로그 — 채널별 분리 (payment, auth, news, general), NDJSON
 */
function app_error(string $channel, string $message, $data = null, ?int $userId = null): void {
    $dir = _getLogDir();
    $file = $dir . DIRECTORY_SEPARATOR . "error_{$channel}.log";
    $entry = [
        'ts' => date('Y-m-d\TH:i:sP'),
        'rid' => get_request_id(),
        'msg' => $message,
    ];
    if ($userId !== null) $entry['uid'] = $userId;
    if ($data !== null) $entry['data'] = $data;
    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * 결제 관련 로그
 */
function payment_log(string $message, $data = null, ?int $userId = null): void {
    app_error('payment', $message, $data, $userId);
}
