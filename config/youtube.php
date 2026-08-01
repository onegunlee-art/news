<?php
declare(strict_types=1);

/**
 * YouTube Shorts Pipeline Configuration
 * 
 * Style: Dark cinematic with gold accents
 * Resolution: 1080x1920 (9:16 vertical shorts)
 */

$projectRoot = dirname(__DIR__);

return [
    'enabled' => filter_var($_ENV['ENABLE_YOUTUBE'] ?? getenv('ENABLE_YOUTUBE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    
    'resolution' => [
        'width' => 1080,
        'height' => 1920,
    ],
    
    'style' => [
        'bg_color' => '#0a0a0a',
        'bg_rgb' => [10, 10, 10],
        'primary_color' => '#d4af37',
        'primary_rgb' => [212, 175, 55],
        'text_color' => '#ffffff',
        'text_rgb' => [255, 255, 255],
        'secondary_text_color' => '#888888',
        'secondary_text_rgb' => [136, 136, 136],
    ],
    
    'fonts' => [
        'title' => $projectRoot . '/public/fonts/noto/noto_sans_kr_bold_b1d8ccaef03cabe0c50be6a406ebee03.ttf',
        'body' => $projectRoot . '/public/fonts/noto/noto_sans_kr_normal_f720aac0493f6f2cdc1ac7555480ae45.ttf',
        'fallback' => 'NotoSansKR',
    ],
    
    'scenes' => [
        1 => [
            'type' => 'fixed',
            'name' => 'opening',
            'duration_sec' => 3,
            'text' => 'THE WORLD CHANGED TODAY',
        ],
        2 => [
            'type' => 'map',
            'name' => 'headline',
            'duration_sec' => 9,
        ],
        3 => [
            'type' => 'text',
            'name' => 'why_important',
            'duration_sec' => 15,
            'points_count' => 3,
        ],
        4 => [
            'type' => 'text',
            'name' => 'future_impact',
            'duration_sec' => 13,
            'points_count' => 3,
        ],
        5 => [
            'type' => 'chart',
            'name' => 'key_numbers',
            'duration_sec' => 10,
        ],
        6 => [
            'type' => 'fixed',
            'name' => 'ending',
            'duration_sec' => 7,
            'text' => 'Essential truth. A clear view of the world.',
            'tagline' => 'the gist.',
        ],
    ],
    
    'total_duration_sec' => 57,
    
    'tts' => [
        'voice' => $_ENV['YOUTUBE_TTS_VOICE'] ?? getenv('YOUTUBE_TTS_VOICE') ?: 'ko-KR-Neural2-C',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
    ],
    
    'map' => [
        'provider' => 'carto',
        'style' => 'dark_all',
        'zoom' => 10,
        'marker_color' => '#d4af37',
    ],
    
    'storage_path' => $projectRoot . '/storage/youtube',
    'fixed_assets_path' => $projectRoot . '/storage/youtube/_fixed',
    
    'ffmpeg' => [
        'binary' => 'ffmpeg',
        'ffprobe' => 'ffprobe',
        'video_codec' => 'libx264',
        'audio_codec' => 'aac',
        'pixel_format' => 'yuv420p',
        'crf' => 23,
        'preset' => 'medium',
    ],
    
    'llm' => [
        'model' => $_ENV['YOUTUBE_LLM_MODEL'] ?? getenv('YOUTUBE_LLM_MODEL') ?: 'gpt-4o',
        'fallback' => 'gpt-4o',
        'max_tokens' => 4000,
    ],
];
