<?php
/**
 * New York Times API 설정 파일
 * 
 * NYT Developer Portal에서 API 키를 발급받아 설정하세요.
 * https://developer.nytimes.com
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | NYT API 인증 정보
    |--------------------------------------------------------------------------
    |
    | NYT Developer Portal에서 앱을 등록하고 API 키를 발급받으세요.
    | https://developer.nytimes.com/get-started
    |
    */
    
    'api_key' => getenv('NYT_API_KEY') ?: 'YOUR_NYT_API_KEY_HERE',
    
    /*
    |--------------------------------------------------------------------------
    | API 엔드포인트
    |--------------------------------------------------------------------------
    */
    
    'endpoints' => [
        // 기사 검색 API
        'article_search' => 'https://api.nytimes.com/svc/search/v2/articlesearch.json',
        
        // 인기 기사 API
        'top_stories' => 'https://api.nytimes.com/svc/topstories/v2/{section}.json',
        
        // 가장 많이 본 기사
        'most_popular' => 'https://api.nytimes.com/svc/mostpopular/v2/{type}/{period}.json',
        
        // 아카이브 API
        'archive' => 'https://api.nytimes.com/svc/archive/v1/{year}/{month}.json',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Top Stories 섹션 목록
    |--------------------------------------------------------------------------
    */
    
    'sections' => [
        'home',        // 홈
        'world',       // 세계
        'us',          // 미국
        'politics',    // 정치
        'business',    // 비즈니스
        'technology',  // 기술
        'science',     // 과학
        'health',      // 건강
        'sports',      // 스포츠
        'arts',        // 예술
        'books',       // 책
        'style',       // 스타일
        'food',        // 음식
        'travel',      // 여행
        'opinion',     // 오피니언
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Most Popular 타입 및 기간
    |--------------------------------------------------------------------------
    */
    
    'most_popular' => [
        'types' => ['viewed', 'shared', 'emailed'],
        'periods' => [1, 7, 30], // 일 단위
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API 제한 설정
    |--------------------------------------------------------------------------
    |
    | NYT API 무료 티어: 500 요청/일, 5 요청/분
    |
    */
    
    'rate_limits' => [
        'requests_per_day' => 500,
        'requests_per_minute' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 캐시 설정
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5분 캐시
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 기본 설정
    |--------------------------------------------------------------------------
    */
    
    'defaults' => [
        'section' => 'home',
        'page_size' => 10,
        'language' => 'en',
    ],
];
