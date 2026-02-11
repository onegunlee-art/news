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
            'model' => 'gpt-5.2',
            'temperature' => 0.7,
            'max_tokens' => 4000,
            'summary_length' => 3, // 문장 수
            'key_points_count' => 3,
            'enable_tts' => true,
        ],
        
        'interpret' => [
            'enabled' => true,
            'model' => 'gpt-5.2',
            'temperature' => 0.5,
            'similarity_threshold' => 0.7,
            'max_context_items' => 5,
        ],
        
        'learning' => [
            'enabled' => true,
            'model' => 'gpt-5.2',
            'temperature' => 0.8,
            'pattern_storage_path' => __DIR__ . '/../src/data/patterns',
            'ask_clarification' => true,
        ],
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
