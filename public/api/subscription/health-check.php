<?php
/**
 * GET /api/subscription/health-check
 * 
 * StepPay 연결 상태 및 DB 스키마 진단용 (관리자 전용)
 * 
 * 응답:
 * - steppay_api: StepPay API 연결 상태
 * - db_schema: users 테이블 필수 컬럼 존재 여부
 * - config: StepPay 설정 유효성
 */

require_once __DIR__ . '/../lib/cors.php';
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';

$pdo = getDb();

// 관리자 인증 체크 (선택적 - 프로덕션에서는 활성화 권장)
// $userId = getAuthUserId($pdo);
// if (!$userId) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
//     exit;
// }

$result = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => [],
];

// 1. DB 스키마 체크 - users 테이블 필수 컬럼
$requiredColumns = [
    'is_subscribed',
    'subscription_expires_at',
    'steppay_customer_id',
    'steppay_subscription_id',
    'steppay_order_code',
    'pending_checkout_plan_id',
    'pending_promotion_code_id',
    'last_payment_error',
    'last_payment_error_at',
];

$existingColumns = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    $result['checks']['db_schema'] = [
        'status' => empty($missingColumns) ? 'OK' : 'MISSING_COLUMNS',
        'required' => $requiredColumns,
        'missing' => array_values($missingColumns),
        'message' => empty($missingColumns) 
            ? '모든 필수 컬럼 존재'
            : '누락 컬럼: ' . implode(', ', $missingColumns) . ' - 마이그레이션 필요',
    ];
    
    if (!empty($missingColumns)) {
        $result['success'] = false;
    }
} catch (PDOException $e) {
    $result['checks']['db_schema'] = [
        'status' => 'ERROR',
        'message' => 'DB 연결 실패: ' . $e->getMessage(),
    ];
    $result['success'] = false;
}

// 2. StepPay 설정 체크
$cfg = getSteppayConfig();

$configCheck = [
    'secret_token_set' => !empty($cfg['secret_token']),
    'payment_key_set' => !empty($cfg['payment_key']),
    'product_code' => $cfg['product_code'] ?? 'NOT_SET',
    'plans_count' => count($cfg['plans'] ?? []),
    'api_url' => $cfg['api_url'] ?? 'NOT_SET',
];

$result['checks']['config'] = [
    'status' => ($configCheck['secret_token_set'] && $configCheck['payment_key_set']) ? 'OK' : 'MISSING',
    'details' => $configCheck,
    'message' => (!$configCheck['secret_token_set'] || !$configCheck['payment_key_set'])
        ? 'STEPPAY_SECRET_TOKEN 또는 STEPPAY_PAYMENT_KEY 미설정'
        : 'StepPay 설정 정상',
];

if (!$configCheck['secret_token_set'] || !$configCheck['payment_key_set']) {
    $result['success'] = false;
}

// 3. StepPay API 연결 테스트 (간단한 고객 검색)
try {
    $testResult = steppayRequest('GET', '/customers?size=1');
    
    $result['checks']['steppay_api'] = [
        'status' => $testResult['success'] ? 'OK' : 'FAILED',
        'http_code' => $testResult['http_code'] ?? 0,
        'message' => $testResult['success'] 
            ? 'StepPay API 연결 정상'
            : 'StepPay API 연결 실패: ' . ($testResult['error'] ?? 'HTTP ' . ($testResult['http_code'] ?? 'unknown')),
    ];
    
    if (!$testResult['success']) {
        $result['success'] = false;
        // 상세 오류 정보 (디버깅용)
        if (isset($testResult['data']['message'])) {
            $result['checks']['steppay_api']['api_message'] = $testResult['data']['message'];
        }
    }
} catch (Throwable $e) {
    $result['checks']['steppay_api'] = [
        'status' => 'ERROR',
        'message' => 'API 호출 예외: ' . $e->getMessage(),
    ];
    $result['success'] = false;
}

// 4. 플랜별 price_code 유효성 (참고용)
$plansInfo = [];
foreach (($cfg['plans'] ?? []) as $planId => $plan) {
    $plansInfo[$planId] = [
        'price_code' => $plan['price_code'] ?? 'NOT_SET',
        'amount' => $plan['amount'] ?? 0,
    ];
}
$result['checks']['plans'] = [
    'status' => 'INFO',
    'data' => $plansInfo,
];

// 5. 환경 변수 fallback 경고 로그 확인
$envWarningLog = dirname(__DIR__, 3) . '/storage/logs/env_warning.log';
if (file_exists($envWarningLog)) {
    $warnings = file_get_contents($envWarningLog);
    $recentWarnings = array_slice(explode("\n", trim($warnings)), -5);
    $result['checks']['env_warnings'] = [
        'status' => 'WARNING',
        'message' => '환경 변수 fallback 사용 중 - .env 설정 확인 필요',
        'recent' => $recentWarnings,
    ];
    $result['success'] = false;
} else {
    $result['checks']['env_warnings'] = [
        'status' => 'OK',
        'message' => '환경 변수 정상 로드',
    ];
}

// 6. 결제 로그 최근 에러 확인
$paymentLog = dirname(__DIR__, 3) . '/storage/logs/error_payment.log';
if (file_exists($paymentLog)) {
    $logContent = file_get_contents($paymentLog);
    $lines = array_filter(explode("\n", trim($logContent)));
    $recentLines = array_slice($lines, -10);
    
    $recentErrors = [];
    foreach ($recentLines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            $recentErrors[] = $entry;
        }
    }
    
    $result['checks']['recent_payment_logs'] = [
        'status' => 'INFO',
        'count' => count($lines),
        'recent' => $recentErrors,
    ];
} else {
    $result['checks']['recent_payment_logs'] = [
        'status' => 'INFO',
        'message' => '결제 로그 파일 없음 (정상일 수 있음)',
    ];
}

// 최종 진단 요약
if (!$result['success']) {
    $failedChecks = [];
    $actionItems = [];
    
    foreach ($result['checks'] as $name => $check) {
        if (isset($check['status']) && !in_array($check['status'], ['OK', 'INFO'])) {
            $failedChecks[] = $name . ': ' . ($check['message'] ?? $check['status']);
        }
    }
    
    // 필요한 마이그레이션 안내
    $dbCheck = $result['checks']['db_schema'] ?? [];
    if (!empty($dbCheck['missing'])) {
        $missing = $dbCheck['missing'];
        $actionItems[] = '=== 필요한 마이그레이션 ===';
        
        if (array_intersect(['is_subscribed', 'subscription_expires_at', 'steppay_customer_id', 'steppay_subscription_id', 'steppay_order_code'], $missing)) {
            $actionItems[] = '1. database/migrations/add_subscription_fields.sql 실행';
        }
        if (array_intersect(['pending_checkout_plan_id', 'pending_promotion_code_id'], $missing)) {
            $actionItems[] = '2. database/migrations/create_promotion_codes.sql 실행 (ALTER TABLE 부분)';
        }
        if (array_intersect(['last_payment_error', 'last_payment_error_at'], $missing)) {
            $actionItems[] = '3. database/migrations/add_last_payment_error.sql 실행';
        }
    }
    
    $result['diagnosis'] = '문제 발견: ' . implode('; ', $failedChecks);
    if (!empty($actionItems)) {
        $result['action_required'] = $actionItems;
    }
} else {
    $result['diagnosis'] = '모든 체크 통과 - StepPay 연동 정상';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
