<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../lib/steppay.php';

$cfg = getSteppayConfig();
$tokenLen = strlen($cfg['secret_token'] ?? '');

$result = steppayRequest('GET', '/products?pageNum=1&pageSize=1');

echo json_encode([
    'token_loaded' => $tokenLen > 0,
    'token_length' => $tokenLen,
    'token_preview' => $tokenLen > 8 ? substr($cfg['secret_token'], 0, 6) . '...' : '(empty)',
    'api_url' => $cfg['api_url'] ?? '(missing)',
    'api_call_success' => $result['success'],
    'api_http_code' => $result['http_code'],
    'api_response_preview' => isset($result['data']['content']) ? 'OK - products found' : ($result['error'] ?? 'unknown'),
], JSON_UNESCAPED_UNICODE);
