<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../lib/steppay.php';

$cfg = getSteppayConfig();

$tokenLen = strlen($cfg['secret_token'] ?? '');
$paymentKeyLen = strlen($cfg['payment_key'] ?? '');

echo json_encode([
    'secret_token_length' => $tokenLen,
    'secret_token_set' => $tokenLen > 0,
    'secret_token_preview' => $tokenLen > 4 ? substr($cfg['secret_token'], 0, 4) . '***' : '(empty)',
    'payment_key_length' => $paymentKeyLen,
    'payment_key_set' => $paymentKeyLen > 0,
    'api_url' => $cfg['api_url'] ?? '',
    'product_code' => $cfg['product_code'] ?? '',
    'getenv_test' => getenv('STEPPAY_SECRET_TOKEN') ?: '(getenv returned empty)',
], JSON_UNESCAPED_UNICODE);
