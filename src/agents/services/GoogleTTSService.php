<?php
/**
 * Google Cloud Text-to-Speech Service
 *
 * Google TTS REST API 연동. 텍스트를 MP3 오디오로 변환 후 저장하고 URL 반환.
 *
 * @package Agents\Services
 */

declare(strict_types=1);

namespace Agents\Services;

class GoogleTTSService
{
    private const SYNTHESIZE_URL = 'https://texttospeech.googleapis.com/v1/text:synthesize';
    /** 요청당 최대 텍스트 바이트 수 (Google 제한 5000, 여유 두고 4500) */
    private const MAX_INPUT_BYTES = 4500;

    private string $apiKey;
    private string $defaultVoice;
    private string $languageCode;
    private string $audioEncoding;
    private int $timeout;
    private string $audioStoragePath;

    public function __construct(array $config = [])
    {
        $projectRoot = dirname(__DIR__, 3);
        $baseConfig = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];
        $merged = array_merge($baseConfig, $config);

        $this->apiKey = $merged['api_key'] ?? '';
        $this->defaultVoice = $merged['default_voice'] ?? 'ko-KR-Standard-A';
        $this->languageCode = $merged['language_code'] ?? 'ko-KR';
        $this->audioEncoding = $merged['audio_encoding'] ?? 'MP3';
        $this->timeout = (int) ($merged['timeout'] ?? 30);
        $this->audioStoragePath = $merged['audio_storage_path'] ?? $projectRoot . '/storage/audio';
    }

    /**
     * API 키 설정 여부
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * TTS 생성: 텍스트를 오디오로 변환 후 저장하고 URL 반환.
     *
     * @param string $text 변환할 텍스트
     * @param array $options ['voice' => string (보이스명)]
     * @return string|null 오디오 URL 또는 실패 시 null
     */
    public function textToSpeech(string $text, array $options = []): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (!$this->isConfigured()) {
            return $this->generateMockAudioUrl($text);
        }

        try {
            return $this->callTTSApi($text, $options);
        } catch (\Throwable $e) {
            error_log('Google TTS error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * API 호출 (긴 텍스트는 청크 분할 후 병합)
     */
    private function callTTSApi(string $text, array $options): string
    {
        $voice = $options['voice'] ?? $this->defaultVoice;
        $lenBytes = strlen($text);

        if ($lenBytes <= self::MAX_INPUT_BYTES) {
            $audioData = $this->synthesizeChunk($text, $voice);
            return $this->saveAudioFile($audioData);
        }

        $chunks = $this->splitTextForTTS($text, self::MAX_INPUT_BYTES);
        $audioParts = [];
        foreach ($chunks as $chunk) {
            $audioParts[] = $this->synthesizeChunk($chunk, $voice);
        }
        return $this->saveAudioFile(implode('', $audioParts));
    }

    /**
     * 단일 청크 synthesize 요청 후 바이너리 오디오 반환
     */
    private function synthesizeChunk(string $text, string $voiceName): string
    {
        $payload = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => $this->languageCode,
                'name' => $voiceName,
            ],
            'audioConfig' => [
                'audioEncoding' => $this->audioEncoding,
            ],
        ];

        $url = self::SYNTHESIZE_URL . '?key=' . urlencode($this->apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("Google TTS API error: HTTP {$httpCode}. " . (is_string($response) ? $response : ''));
        }

        $data = json_decode($response, true);
        $audioContent = $data['audioContent'] ?? null;
        if ($audioContent === null) {
            throw new \RuntimeException('Google TTS: missing audioContent in response');
        }

        $decoded = base64_decode($audioContent, true);
        if ($decoded === false) {
            throw new \RuntimeException('Google TTS: invalid base64 audioContent');
        }

        return $decoded;
    }

    /**
     * 텍스트를 바이트 제한에 맞게 분할 (문장 경계 우선)
     */
    private function splitTextForTTS(string $text, int $maxBytes): array
    {
        $chunks = [];
        $offset = 0;
        $len = strlen($text);

        while ($offset < $len) {
            $remain = substr($text, $offset, $maxBytes + 1);
            $take = strlen($remain);
            if ($take <= $maxBytes) {
                $chunks[] = $remain;
                $offset += $take;
                continue;
            }
            $search = substr($remain, 0, $maxBytes);
            $lastSep = -1;
            foreach (['。', '.', '!', '?', "\n"] as $sep) {
                $p = strrpos($search, $sep);
                if ($p !== false && $p > $lastSep) {
                    $lastSep = $p;
                }
            }
            if ($lastSep >= (int) ($maxBytes * 0.5)) {
                $chunk = substr($remain, 0, $lastSep + 1);
                $chunks[] = $chunk;
                $offset += strlen($chunk);
            } else {
                $chunks[] = substr($remain, 0, $maxBytes);
                $offset += $maxBytes;
            }
        }

        return $chunks;
    }

    private function saveAudioFile(string $audioData): string
    {
        $filename = 'analysis_' . uniqid() . '.mp3';
        if (!is_dir($this->audioStoragePath)) {
            mkdir($this->audioStoragePath, 0755, true);
        }
        $filePath = $this->audioStoragePath . '/' . $filename;
        file_put_contents($filePath, $audioData);
        return '/storage/audio/' . $filename;
    }

    private function generateMockAudioUrl(string $text): string
    {
        $filename = 'mock_audio_' . substr(md5($text), 0, 8) . '.mp3';
        return '/storage/audio/' . $filename . '?mock=true';
    }
}
