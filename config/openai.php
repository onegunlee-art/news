<?php
/**
 * OpenAI API Configuration
 * 
 * GPT-4.1 및 TTS 서비스 설정
 * 
 * @package Config
 */

return [
    // API 인증
    'api_key' => getenv('OPENAI_API_KEY') ?: '',
    
    // 기본 모델 설정
    'model' => 'gpt-4.1',
    'fallback_model' => 'gpt-4-turbo-preview',
    
    // 요청 제한
    'max_tokens' => 4000,
    'temperature' => 0.7,
    'timeout' => 60,
    
    // TTS 설정
    'tts' => [
        'model' => 'tts-1-hd',
        'voice' => 'alloy', // alloy, echo, fable, onyx, nova, shimmer
        'speed' => 1.0,
        'response_format' => 'mp3'
    ],
    
    // 재시도 설정
    'retry' => [
        'max_attempts' => 3,
        'delay_ms' => 1000,
        'multiplier' => 2
    ],
    
    // 엔드포인트
    'endpoints' => [
        'chat' => 'https://api.openai.com/v1/chat/completions',
        'tts' => 'https://api.openai.com/v1/audio/speech',
        'embeddings' => 'https://api.openai.com/v1/embeddings',
        'images' => 'https://api.openai.com/v1/images/generations'
    ],

    // 이미지 생성 (DALL·E 3) - 썸네일용
    'images' => [
        'model' => 'dall-e-3',
        'size' => '1024x1024',
        'quality' => 'standard',
        'response_format' => 'url',
        'style' => 'vivid',
        'storage_path' => null, // null이면 프로젝트 storage/thumbnails 사용
    ]
];
