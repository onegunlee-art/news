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
    'url' => getenv('APP_URL') ?: 'https://www.thegist.com',
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
        'jwt_secret' => getenv('JWT_SECRET') ?: 'your-super-secret-jwt-key-change-in-production',
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
];
