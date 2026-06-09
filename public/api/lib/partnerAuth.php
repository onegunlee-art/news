<?php
declare(strict_types=1);

/**
 * Partner API 인증 — X-Partner-Key 헤더 검증
 * 환경변수: PARTNER_API_KEY
 */

function requirePartnerKey(): void
{
    $key = $_SERVER['HTTP_X_PARTNER_KEY'] ?? '';
    $expected = getenv('PARTNER_API_KEY') ?: ($_ENV['PARTNER_API_KEY'] ?? '');
    
    if ($expected === '') {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Partner API not configured'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($key === '' || !hash_equals($expected, $key)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Invalid partner key'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
