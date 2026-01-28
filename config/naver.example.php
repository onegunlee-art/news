<?php
/**
 * 네이버 API 설정 파일 (예제)
 * 
 * 이 파일을 naver.php로 복사하고 실제 값을 입력하세요.
 * https://developers.naver.com 에서 앱 등록 후 키 발급
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

return [
    'client_id' => 'YOUR_NAVER_CLIENT_ID',
    'client_secret' => 'YOUR_NAVER_CLIENT_SECRET',
    
    'news' => [
        'base_url' => 'https://openapi.naver.com/v1/search/news.json',
        'default_display' => 10,
        'default_start' => 1,
        'default_sort' => 'date',
        'max_display' => 100,
        'max_start' => 1000,
        'daily_limit' => 25000,
    ],
    
    'http' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retry_times' => 3,
        'retry_delay' => 1000,
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'prefix' => 'naver_news_',
    ],
];
