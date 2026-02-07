<?php
/**
 * Google Cloud Text-to-Speech Configuration
 *
 * @package Config
 */

return [
    'api_key' => getenv('GOOGLE_TTS_API_KEY') ?: '',
    'default_voice' => 'ko-KR-Standard-A',
    'language_code' => 'ko-KR',
    'audio_encoding' => 'MP3',
    'timeout' => 30,
    'audio_storage_path' => dirname(__DIR__) . '/storage/audio',
];
