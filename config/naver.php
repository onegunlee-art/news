<?php
/**
 * 네이버 API 설정 파일
 * 
 * Naver Developers (https://developers.naver.com)에서 앱 등록 후
 * Client ID와 Client Secret을 발급받아 설정하세요.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 * @see https://developers.naver.com/docs/search/news/
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 네이버 앱 인증 정보
    |--------------------------------------------------------------------------
    |
    | Naver Developers 콘솔에서 발급받은 키를 입력하세요.
    |
    */
    
    'client_id' => getenv('NAVER_CLIENT_ID') ?: '',
    'client_secret' => getenv('NAVER_CLIENT_SECRET') ?: '',
    
    /*
    |--------------------------------------------------------------------------
    | 뉴스 검색 API 설정
    |--------------------------------------------------------------------------
    */
    
    'news' => [
        // API 엔드포인트
        'base_url' => 'https://openapi.naver.com/v1/search/news.json',
        
        // 기본 검색 설정
        'default_display' => 10,     // 한 번에 표시할 검색 결과 개수 (기본값: 10, 최대: 100)
        'default_start' => 1,        // 검색 시작 위치 (기본값: 1, 최대: 1000)
        'default_sort' => 'date',    // 정렬 기준 (sim: 정확도순, date: 날짜순)
        
        // 검색 제한
        'max_display' => 100,
        'max_start' => 1000,
        
        // API 호출 제한 (일 25,000건)
        'daily_limit' => 25000,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP 클라이언트 설정
    |--------------------------------------------------------------------------
    */
    
    'http' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retry_times' => 3,
        'retry_delay' => 1000, // ms
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 캐시 설정
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5분
        'prefix' => 'naver_news_',
    ],
];
