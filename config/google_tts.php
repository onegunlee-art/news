<?php
/**
 * Google Cloud Text-to-Speech Configuration
 *
 * @package Config
 */

$apiKey = ($_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY')) ?: '';

return [
    'api_key' => $apiKey,
    'default_voice' => 'ko-KR-Standard-A',
    'language_code' => 'ko-KR',
    'audio_encoding' => 'MP3',
    'timeout' => 120,
    'audio_storage_path' => dirname(__DIR__) . '/storage/audio',
];
