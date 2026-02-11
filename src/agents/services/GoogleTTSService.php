<?php
/**
 * Google Cloud Text-to-Speech Service
 *
 * Google TTS REST API 연동. 텍스트를 오디오로 변환 후 저장하고 URL 반환.
 * 긴 텍스트는 청크 분할 → LINEAR16(PCM) 수신 → 연결 → WAV 파일 저장.
 *
 * @package Agents\Services
 */

declare(strict_types=1);

namespace Agents\Services;

class GoogleTTSService
{
    private const SYNTHESIZE_URL = 'https://texttospeech.googleapis.com/v1/text:synthesize';

    /**
     * Google TTS 요청당 최대 텍스트 바이트 수.
     * Google 공식 제한 5000바이트, 여유 두고 4800.
     */
    private const MAX_INPUT_BYTES = 4800;

    /** LINEAR16 샘플 레이트 (Hz) */
    private const SAMPLE_RATE = 24000;
    /** LINEAR16 비트 깊이 */
    private const BITS_PER_SAMPLE = 16;
    /** 모노 채널 */
    private const NUM_CHANNELS = 1;

    private string $apiKey;
    private string $defaultVoice;
    private string $languageCode;
    private int $timeout;
    private string $audioStoragePath;
    /** 마지막 TTS 실패 시 원인 (API/저장 실패 시 메시지 저장) */
    private string $lastError = '';

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
        $this->timeout = (int) ($merged['timeout'] ?? 60);
        $this->audioStoragePath = $merged['audio_storage_path'] ?? $projectRoot . '/storage/audio';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** 마지막 실패 원인 (디버깅/API 응답용) */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * TTS 생성: 텍스트 → WAV 오디오 파일 URL
     */
    public function textToSpeech(string $text, array $options = []): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (!$this->isConfigured()) {
            $this->lastError = 'GOOGLE_TTS_API_KEY not set';
            error_log('Google TTS: API key not configured. getenv(GOOGLE_TTS_API_KEY)=' . (getenv('GOOGLE_TTS_API_KEY') ?: '(empty)'));
            return null;
        }

        try {
            $this->lastError = '';
            return $this->generateAudio($text, $options);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Google TTS error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * SSML로 TTS 생성 (pause 등 제어 가능). 단일 요청으로 합성.
     * Google 제한 5000바이트 초과 시 null 반환.
     */
    public function textToSpeechWithSsml(string $ssml, array $options = []): ?string
    {
        $ssml = trim($ssml);
        if ($ssml === '' || strlen($ssml) > self::MAX_INPUT_BYTES) {
            return null;
        }

        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $this->lastError = '';
            set_time_limit(300);
            $voice = $options['voice'] ?? $this->defaultVoice;
            $pcmData = $this->synthesizeSsmlLinear16($ssml, $voice);
            $wavData = $this->createWavFile($pcmData);
            return $this->saveFile($wavData, 'wav');
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Google TTS SSML error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * SSML 한 번에 LINEAR16(PCM)으로 합성
     */
    private function synthesizeSsmlLinear16(string $ssml, string $voiceName): string
    {
        $payload = [
            'input' => ['ssml' => $ssml],
            'voice' => [
                'languageCode' => $this->languageCode,
                'name' => $voiceName,
            ],
            'audioConfig' => [
                'audioEncoding' => 'LINEAR16',
                'sampleRateHertz' => self::SAMPLE_RATE,
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
            throw new \RuntimeException('Google TTS SSML API error: HTTP ' . $httpCode . ' ' . substr((string) $response, 0, 300));
        }

        $data = json_decode($response, true);
        $audioContent = $data['audioContent'] ?? null;
        if ($audioContent === null) {
            throw new \RuntimeException('Google TTS: missing audioContent');
        }

        $decoded = base64_decode($audioContent, true);
        if ($decoded === false) {
            throw new \RuntimeException('Google TTS: invalid base64');
        }

        if (strlen($decoded) > 44 && substr($decoded, 0, 4) === 'RIFF') {
            $dataPos = strpos($decoded, 'data');
            if ($dataPos !== false) {
                $decoded = substr($decoded, $dataPos + 8);
            }
        }

        return $decoded;
    }

    /**
     * 텍스트를 청크로 분할 → 각 청크를 LINEAR16(PCM)으로 합성 → 연결 → WAV 파일 저장
     */
    private function generateAudio(string $text, array $options): string
    {
        $pcmData = $this->generateAudioPcm($text, $options);
        return $this->saveFile($this->createWavFile($pcmData), 'wav');
    }

    /**
     * 텍스트 → PCM raw bytes (청크 합성 후 연결). 구조화 TTS에서 내레이션 부분 합치기용.
     */
    private function generateAudioPcm(string $text, array $options): string
    {
        set_time_limit(300);
        $voice = $options['voice'] ?? $this->defaultVoice;
        $chunks = $this->splitText($text);
        $pcmData = '';
        foreach ($chunks as $i => $chunk) {
            $pcmData .= $this->synthesizeChunkLinear16($chunk, $voice);
        }
        return $pcmData;
    }

    /**
     * 제목 → 1초 휴식 → 편집 문구(날짜·출처) → 1초 휴식 → 내레이션 순으로 한 WAV 생성 (SSML pause 사용)
     */
    public function textToSpeechStructured(string $title, string $meta, string $narration, array $options = []): ?string
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'GOOGLE_TTS_API_KEY not set';
            return null;
        }
        $voice = $options['voice'] ?? $this->defaultVoice;
        $escape = function (string $s): string {
            return htmlspecialchars(trim($s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };
        $title = $escape($title);
        $meta = $escape($meta);
        $narration = trim($narration);

        $ssmlIntro = '<speak>' . $title . '<break time="1s"/>' . $meta . '<break time="1s"/></speak>';
        try {
            $this->lastError = '';
            set_time_limit(300);
            $pcm1 = $this->synthesizeSsmlLinear16($ssmlIntro, $voice);
            $pcm2 = $narration !== '' ? $this->generateAudioPcm($narration, ['voice' => $voice]) : '';
            $pcm = $pcm1 . $pcm2;
            if ($pcm === '') {
                return null;
            }
            return $this->saveFile($this->createWavFile($pcm), 'wav');
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Google TTS structured error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 단일 청크를 LINEAR16(PCM)으로 합성하여 raw PCM 바이트 반환
     */
    private function synthesizeChunkLinear16(string $text, string $voiceName): string
    {
        $payload = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => $this->languageCode,
                'name' => $voiceName,
            ],
            'audioConfig' => [
                'audioEncoding' => 'LINEAR16',
                'sampleRateHertz' => self::SAMPLE_RATE,
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
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $msg = "Google TTS API error: HTTP {$httpCode}";
            if ($curlError) {
                $msg .= " curl: {$curlError}";
            }
            if (is_string($response)) {
                $msg .= " response: " . substr($response, 0, 500);
            }
            throw new \RuntimeException($msg);
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

        // LINEAR16 응답에는 WAV 헤더(44 bytes)가 포함될 수 있음 → 제거
        // WAV 헤더는 "RIFF"로 시작
        if (strlen($decoded) > 44 && substr($decoded, 0, 4) === 'RIFF') {
            // "data" 청크 위치 찾기
            $dataPos = strpos($decoded, 'data');
            if ($dataPos !== false) {
                // "data" + 4바이트(크기) 이후가 실제 PCM
                $decoded = substr($decoded, $dataPos + 8);
            }
        }

        return $decoded;
    }

    /**
     * Raw PCM 데이터로 WAV 파일 바이너리 생성
     *
     * WAV (RIFF) 형식:
     *   RIFF header (12 bytes)
     *   fmt  chunk  (24 bytes)
     *   data chunk  (8 + PCM data bytes)
     */
    private function createWavFile(string $pcmData): string
    {
        $dataSize = strlen($pcmData);
        $byteRate = self::SAMPLE_RATE * self::NUM_CHANNELS * (self::BITS_PER_SAMPLE / 8);
        $blockAlign = self::NUM_CHANNELS * (self::BITS_PER_SAMPLE / 8);

        $header = '';
        // RIFF header
        $header .= 'RIFF';
        $header .= pack('V', 36 + $dataSize);   // 파일 크기 - 8
        $header .= 'WAVE';
        // fmt sub-chunk
        $header .= 'fmt ';
        $header .= pack('V', 16);               // fmt chunk size
        $header .= pack('v', 1);                // PCM format (1)
        $header .= pack('v', self::NUM_CHANNELS);
        $header .= pack('V', self::SAMPLE_RATE);
        $header .= pack('V', (int) $byteRate);
        $header .= pack('v', (int) $blockAlign);
        $header .= pack('v', self::BITS_PER_SAMPLE);
        // data sub-chunk
        $header .= 'data';
        $header .= pack('V', $dataSize);

        return $header . $pcmData;
    }

    /**
     * 텍스트를 바이트 제한에 맞게 분할 (UTF-8 안전, 문장 경계 우선)
     */
    private function splitText(string $text): array
    {
        $textBytes = strlen($text);
        if ($textBytes <= self::MAX_INPUT_BYTES) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;

        while ($offset < $textBytes) {
            $remaining = $textBytes - $offset;

            if ($remaining <= self::MAX_INPUT_BYTES) {
                $chunks[] = substr($text, $offset);
                break;
            }

            // maxBytes 범위 내에서 문장 경계를 찾음
            $window = substr($text, $offset, self::MAX_INPUT_BYTES);

            // 문장 구분자 (한국어 마침표, 영어 마침표, 느낌표, 물음표, 줄바꿈)
            $bestCut = -1;
            foreach (['. ', '? ', '! ', ".\n", "?\n", "!\n", "\n"] as $sep) {
                $p = strrpos($window, $sep);
                if ($p !== false && $p > $bestCut) {
                    $bestCut = $p + strlen($sep);
                }
            }

            // 문장 경계를 못 찾으면 쉼표/공백에서 자르기
            if ($bestCut < (int) (self::MAX_INPUT_BYTES * 0.3)) {
                foreach ([', ', ' '] as $sep) {
                    $p = strrpos($window, $sep);
                    if ($p !== false && $p > $bestCut) {
                        $bestCut = $p + strlen($sep);
                    }
                }
            }

            // 그래도 못 찾으면 UTF-8 안전하게 자르기
            if ($bestCut <= 0) {
                $bestCut = $this->utf8SafeCut($window, self::MAX_INPUT_BYTES);
            }

            $chunk = substr($text, $offset, $bestCut);
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        return $chunks;
    }

    /**
     * UTF-8 멀티바이트 문자 중간에서 자르지 않도록 바이트 위치 조정
     */
    private function utf8SafeCut(string $str, int $maxBytes): int
    {
        if (strlen($str) <= $maxBytes) {
            return strlen($str);
        }

        // maxBytes 위치에서 뒤로 내려가면서 유효한 UTF-8 문자 경계 찾기
        $cut = $maxBytes;
        while ($cut > 0) {
            $byte = ord($str[$cut]);
            // UTF-8 시작 바이트 또는 ASCII: 0xxxxxxx 또는 11xxxxxx
            if (($byte & 0x80) === 0 || ($byte & 0xC0) === 0xC0) {
                break;
            }
            $cut--;
        }

        return $cut > 0 ? $cut : $maxBytes;
    }

    /**
     * 오디오 파일 저장 후 URL 반환
     */
    private function saveFile(string $data, string $ext): string
    {
        $filename = 'tts_' . uniqid() . '.' . $ext;
        if (!is_dir($this->audioStoragePath)) {
            mkdir($this->audioStoragePath, 0755, true);
        }
        $filePath = $this->audioStoragePath . '/' . $filename;
        file_put_contents($filePath, $data);

        $sizeMB = round(strlen($data) / 1024 / 1024, 2);
        error_log("[GoogleTTS] Saved {$filename} ({$sizeMB} MB)");

        return '/storage/audio/' . $filename;
    }
}
