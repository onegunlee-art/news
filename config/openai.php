<?php
/**
 * OpenAI API Configuration
 * 
 * GPT-5.2 및 TTS 서비스 설정
 * 
 * @package Config
 */

// putenv()가 일부 서버(FastCGI 등)에서 getenv()에 반영되지 않을 수 있음 → $_ENV 우선
$openaiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!is_string($openaiKey)) {
    $openaiKey = '';
}
return [
    // API 인증
    'api_key' => $openaiKey,
    
    // 기본 모델 설정
    'model' => 'gpt-5.2',
    'fallback_model' => 'gpt-5',
    
    // 요청 제한
    'max_tokens' => 8000,
    'temperature' => 0.7,
    'timeout' => 120,
    
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
    
    // 엔드포인트 (Responses API)
    'endpoints' => [
        'chat' => 'https://api.openai.com/v1/responses',
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
