<?php
/**
 * Agent System Configuration
 * 
 * 4개 Agent의 설정 및 파이프라인 구성
 * 
 * @package Config
 */

return [
    // 글로벌 설정
    'global' => [
        'language' => 'ko', // 기본 출력 언어
        'debug' => getenv('APP_DEBUG') === 'true',
        'cache_enabled' => true,
        'cache_ttl' => 3600, // 1시간
    ],

    // Agent 개별 설정
    'agents' => [
        'validation' => [
            'enabled' => true,
            'timeout' => 30,
            'allowed_domains' => [], // 빈 배열 = 모든 도메인 허용
            'blocked_domains' => ['localhost', '127.0.0.1'],
            'user_agent' => 'TheGist-NewsBot/1.0',
            'verify_ssl' => true,
        ],
        
        'analysis' => [
            'enabled' => true,
            'model' => 'gpt-5.4',
            'temperature' => 0.35,
            'max_tokens' => 8000,
            'timeout' => 180,
            'summary_length' => 3,
            'key_points_count' => 3,
            'enable_tts' => true,
        ],
        
        'interpret' => [
            'enabled' => true,
            'model' => 'gpt-5.4',
            'temperature' => 0.5,
            'similarity_threshold' => 0.7,
            'max_context_items' => 5,
        ],
        
        'learning' => [
            'enabled' => true,
            'model' => 'gpt-5.4',
            'temperature' => 0.8,
            'pattern_storage_path' => __DIR__ . '/../src/data/patterns',
            'ask_clarification' => true,
        ],

        'narration' => [
            'enabled' => true,
            'model' => 'gpt-5.4',
            'timeout' => 180,
            'max_tokens' => 4096,
            'temperature' => 0.5,
        ],

        'editing' => [
            'enabled' => true,
            'model' => 'gpt-5.4',
            'timeout' => 120,
            'max_tokens' => 4096,
            'temperature' => 0.3,
        ],
    ],

    // WebScraperService (URL 접근 검사·스크래핑). HEAD를 막는 사이트는 skip_head_domains에 추가
    'scraper' => [
        'timeout' => 60,
        'skip_head_domains' => ['www.economist.com', 'economist.com'],
        'jina_fallback' => true,  // cURL 실패 시 Jina AI Reader로 우회
    ],

    // 파이프라인 순서
    'pipeline' => [
        'validation',
        'analysis',
        'interpret',
        'learning'
    ],

    // 출력 형식
    'output' => [
        'format' => 'json',
        'include_metadata' => true,
        'include_processing_time' => true,
        'audio_storage_path' => __DIR__ . '/../storage/audio',
    ]
];
