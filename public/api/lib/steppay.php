<?php
/**
 * StepPay API 헬퍼
 * Secret-Token 인증으로 StepPay V1 API를 호출한다.
 */

function getSteppayConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $tryPaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/config/app.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config/app.php',
        __DIR__ . '/../../../config/app.php',
        __DIR__ . '/../../../../config/app.php',
    ];
    foreach ($tryPaths as $p) {
        if (file_exists($p)) {
            $all = require $p;
            $cfg = $all['steppay'] ?? [];
            return $cfg;
        }
    }
    $cfg = [];
    return $cfg;
}

/**
 * StepPay API 호출 공통
 */
function steppayRequest(string $method, string $path, ?array $body = null): array {
    $cfg = getSteppayConfig();
    $url = $cfg['api_url'] . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Secret-Token: ' . ($cfg['secret_token'] ?? ''),
            'Content-Type: application/json',
            'Accept: */*',
        ],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => 0];
    }
    $data = json_decode($response, true) ?: [];
    return ['success' => $httpCode >= 200 && $httpCode < 300, 'data' => $data, 'http_code' => $httpCode];
}

/**
 * StepPay 고객 생성
 */
function steppayCreateCustomer(string $name, ?string $email = null, ?string $customCode = null): array {
    $body = ['name' => $name];
    if ($email) $body['email'] = $email;
    if ($customCode) $body['code'] = $customCode;
    return steppayRequest('POST', '/customers', $body);
}

/**
 * StepPay 고객 정보 갱신
 */
function steppayUpdateCustomer(int $customerId, string $name, ?string $email = null): array {
    $body = ['name' => $name];
    if ($email) $body['email'] = $email;
    return steppayRequest('PUT', '/customers/' . $customerId, $body);
}

/**
 * StepPay 고객 코드로 검색
 */
function steppaySearchCustomerByCode(string $code): array {
    return steppayRequest('GET', '/customers?code=' . urlencode($code));
}

/**
 * StepPay 주문 생성
 */
function steppayCreateOrder(int $customerId, string $priceCode, string $productCode): array {
    return steppayRequest('POST', '/orders', [
        'customerId' => $customerId,
        'items' => [
            [
                'currency' => 'KRW',
                'minimumQuantity' => 1,
                'maximumQuantity' => 1,
                'priceCode' => $priceCode,
                'productCode' => $productCode,
            ],
        ],
    ]);
}

/**
 * StepPay 주문 조회
 */
function steppayGetOrder(string $orderCode): array {
    return steppayRequest('GET', '/orders/' . $orderCode);
}

/**
 * StepPay 결제 URL 생성 (리다이렉트 방식)
 */
function steppayGetPaymentUrl(string $orderCode): string {
    $cfg = getSteppayConfig();
    $baseSuccessUrl = $cfg['success_url'] ?? 'https://www.thegist.co.kr/subscribe/success';
    $baseErrorUrl = $cfg['error_url'] ?? 'https://www.thegist.co.kr/subscribe/error';
    $successUrl = urlencode($baseSuccessUrl . '?order_code=' . $orderCode);
    $errorUrl = urlencode($baseErrorUrl . '?order_code=' . $orderCode);
    return ($cfg['public_api_url'] ?? 'https://api.steppay.kr/api/public')
        . '/orders/' . $orderCode . '/pay'
        . '?successUrl=' . $successUrl
        . '&errorUrl=' . $errorUrl;
}

/**
 * StepPay 구독 조회
 */
function steppayGetSubscription(int $subscriptionId): array {
    return steppayRequest('GET', '/subscriptions/' . $subscriptionId);
}

/**
 * StepPay 구독 취소
 * @param int $subscriptionId 구독 ID
 * @param string $whenToCancel NOW=즉시취소, END_OF_PERIOD=기간만료시취소
 */
function steppayCancelSubscription(int $subscriptionId, string $whenToCancel = 'END_OF_PERIOD'): array {
    return steppayRequest('POST', '/subscriptions/' . $subscriptionId . '/cancel', [
        'whenToCancel' => $whenToCancel,
    ]);
}

/**
 * StepPay 구독 일시정지
 */
function steppayPauseSubscription(int $subscriptionId): array {
    return steppayRequest('POST', '/subscriptions/' . $subscriptionId . '/pause', [
        'whenToPause' => 'IMMEDIATE',
        'whenToResume' => 'NOTHING',
    ]);
}

/**
 * StepPay 구독 활성화 (일시정지/취소대기 해제)
 */
function steppayResumeSubscription(int $subscriptionId): array {
    return steppayRequest('POST', '/subscriptions/' . $subscriptionId . '/active');
}
