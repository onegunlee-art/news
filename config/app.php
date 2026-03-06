<?php
/**
 * 애플리케이션 설정 파일
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 애플리케이션 기본 설정
    |--------------------------------------------------------------------------
    */
    
    'name' => 'News 맥락 분석',
    'version' => '1.0.0',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => (bool) (getenv('APP_DEBUG') ?: false),
    'url' => getenv('APP_URL') ?: 'https://www.thegist.co.kr',
    'timezone' => 'Asia/Seoul',
    'locale' => 'ko',
    
    /*
    |--------------------------------------------------------------------------
    | API 설정
    |--------------------------------------------------------------------------
    */
    
    'api' => [
        'prefix' => '/api',
        'version' => 'v1',
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 100,
            'per_minutes' => 1,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 보안 설정
    |--------------------------------------------------------------------------
    */
    
    'security' => [
        'jwt_secret' => getenv('JWT_SECRET') ?: 'news-context-jwt-secret-key-2026',
        'jwt_expiry' => 3600 * 24, // 24시간
        'jwt_refresh_expiry' => 3600 * 24 * 7, // 7일
        'bcrypt_rounds' => 12,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | CORS 설정
    |--------------------------------------------------------------------------
    */
    
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'max_age' => 86400,
        'supports_credentials' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 캐시 설정
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        'driver' => 'file',
        'path' => dirname(__DIR__) . '/storage/cache',
        'default_ttl' => 3600,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 로깅 설정
    |--------------------------------------------------------------------------
    */
    
    'logging' => [
        'channel' => 'file',
        'path' => dirname(__DIR__) . '/storage/logs',
        'level' => 'debug',
    ],

    /*
    |--------------------------------------------------------------------------
    | StepPay 구독 결제 설정
    |--------------------------------------------------------------------------
    */

    'steppay' => [
        'secret_token' => getenv('STEPPAY_SECRET_TOKEN') ?: '17cab52d68451a765eaacf90c2390d739a0e99b4849c01f139431f266b18ee5e',
        'payment_key' => getenv('STEPPAY_PAYMENT_KEY') ?: 'p9up7apk1o1jxh4z',
        'api_url' => 'https://api.steppay.kr/api/v1',
        'public_api_url' => 'https://api.steppay.kr/api/public',
        'product_code' => 'product_FsBDgadkF',
        'plans' => [
            '1m'  => ['price_code' => 'price_NIXgpyLfN', 'amount' => 7700,  'months' => 1,  'label' => '1개월'],
            '3m'  => ['price_code' => 'price_BMMfkyJhl', 'amount' => 18480, 'months' => 3,  'label' => '3개월'],
            '6m'  => ['price_code' => 'price_6bZGMnvVg', 'amount' => 32340, 'months' => 6,  'label' => '6개월'],
            '12m' => ['price_code' => 'price_u8G79YOU8', 'amount' => 55400, 'months' => 12, 'label' => '12개월'],
        ],
        'success_url' => getenv('APP_URL') ? getenv('APP_URL') . '/subscribe/success' : 'https://www.thegist.co.kr/subscribe/success',
        'error_url'   => getenv('APP_URL') ? getenv('APP_URL') . '/subscribe/error'   : 'https://www.thegist.co.kr/subscribe/error',
    ],
];
