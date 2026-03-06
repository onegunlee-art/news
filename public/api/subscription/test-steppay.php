<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';

$checks = [];

$pdo = getDb();

// 1) DB 컬럼 존재 확인
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_subscribed'");
    $checks['db_is_subscribed'] = $stmt->rowCount() > 0 ? 'EXISTS' : 'MISSING';
} catch (Throwable $e) {
    $checks['db_is_subscribed'] = 'ERROR: ' . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'steppay_customer_id'");
    $checks['db_steppay_customer_id'] = $stmt->rowCount() > 0 ? 'EXISTS' : 'MISSING';
} catch (Throwable $e) {
    $checks['db_steppay_customer_id'] = 'ERROR: ' . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'steppay_order_code'");
    $checks['db_steppay_order_code'] = $stmt->rowCount() > 0 ? 'EXISTS' : 'MISSING';
} catch (Throwable $e) {
    $checks['db_steppay_order_code'] = 'ERROR: ' . $e->getMessage();
}

// 2) StepPay API 연결
$cfg = getSteppayConfig();
$checks['token_length'] = strlen($cfg['secret_token'] ?? '');
$apiResult = steppayRequest('GET', '/products?pageNum=1&pageSize=1');
$checks['steppay_api'] = $apiResult['success'] ? 'OK' : 'FAIL (HTTP ' . $apiResult['http_code'] . ')';

// 3) 고객 생성 테스트 (dry run)
$custTest = steppayCreateCustomer('테스트', 'test@test.com', 'test_debug_' . time());
$checks['customer_create'] = $custTest['success'] ? 'OK (id: ' . ($custTest['data']['id'] ?? '?') . ')' : 'FAIL: ' . json_encode($custTest);

echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
