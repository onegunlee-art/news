<?php
/**
 * OpenAI Service
 * 
 * GPT-4.1 API 연동 서비스
 * Mock 모드 지원 - API 키 없이도 개발/테스트 가능
 * 
 * @package Agents\Services
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Services;

class OpenAIService
{
    private string $apiKey;
    private string $model;
    private array $config;
    private bool $mockMode;

    public function __construct(array $config = [])
    {
        $defaultConfig = require dirname(__DIR__, 3) . '/config/openai.php';
        $this->config = array_merge($defaultConfig, $config);
        
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'gpt-4.1';
        
        // API 키가 없으면 자동으로 Mock 모드
        $this->mockMode = empty($this->apiKey) || ($this->config['mock_mode'] ?? false);
    }

    /**
     * API 키 설정 여부 확인
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !$this->mockMode;
    }

    /**
     * Mock 모드 여부
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Mock 모드 설정
     */
    public function setMockMode(bool $enabled): void
    {
        $this->mockMode = $enabled;
    }

    /**
     * Chat Completion API 호출
     */
    public function chat(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        if ($this->mockMode) {
            return $this->mockChatResponse($systemPrompt, $userPrompt);
        }

        return $this->callChatAPI($systemPrompt, $userPrompt, $options);
    }

    /**
     * 실제 API 호출
     */
    private function callChatAPI(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens']
        ];

        $ch = curl_init($this->config['endpoints']['chat']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $this->config['timeout']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenAI API error: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Mock Chat 응답 생성
     */
    private function mockChatResponse(string $systemPrompt, string $userPrompt): string
    {
        // 프롬프트 내용을 분석하여 적절한 Mock 응답 반환
        $promptLower = strtolower($userPrompt);

        // JSON 응답이 필요한 경우 감지
        if (strpos($promptLower, 'json') !== false) {
            return $this->generateMockJsonResponse($promptLower);
        }

        // 번역 요청
        if (strpos($promptLower, '번역') !== false || strpos($promptLower, 'translate') !== false) {
            return $this->generateMockTranslation($userPrompt);
        }

        // 요약 요청
        if (strpos($promptLower, '요약') !== false || strpos($promptLower, 'summarize') !== false) {
            return $this->generateMockSummary();
        }

        // 기본 응답
        return $this->generateMockAnalysis();
    }

    /**
     * Mock JSON 응답 생성
     */
    private function generateMockJsonResponse(string $prompt): string
    {
        // full_analysis 요청
        if (strpos($prompt, 'translation_summary') !== false || strpos($prompt, 'key_points') !== false) {
            return json_encode([
                'translation_summary' => '[Mock] 이 기사는 글로벌 이슈에 대한 심층 분석을 담고 있습니다. 주요 국가들의 정책 변화와 그 영향을 다루며, 향후 전망을 제시합니다. 한국에 미치는 영향도 함께 분석되어 있습니다.',
                'key_points' => [
                    '[Mock] 주요 국가들의 정책 방향 전환이 감지됨',
                    '[Mock] 경제적 파급효과가 예상보다 클 것으로 분석',
                    '[Mock] 한국 기업과 정부의 대응 전략이 필요한 시점'
                ],
                'critical_analysis' => [
                    'why_important' => '[Mock] 이 이슈는 글로벌 공급망과 무역 질서에 직접적인 영향을 미칩니다. 특히 한국의 주력 산업인 반도체, 자동차 분야에 중대한 변화를 가져올 수 있어 주목해야 합니다.',
                    'future_prediction' => '[Mock] 향후 6개월 내 관련 정책 발표가 예상되며, 이에 따른 시장 변동성 확대가 예측됩니다. 선제적 대응 전략 수립이 권고됩니다.'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // validation 응답
        if (strpos($prompt, 'is_valid') !== false) {
            return json_encode([
                'is_valid' => true,
                'reason' => '[Mock] URL이 유효하고 기사 콘텐츠가 존재합니다.',
                'detected_language' => 'en',
                'article_type' => 'news',
                'confidence' => 0.95
            ], JSON_UNESCAPED_UNICODE);
        }

        // pattern 분석 응답
        if (strpos($prompt, 'style') !== false || strpos($prompt, 'pattern') !== false) {
            return json_encode([
                'style' => [
                    'formality' => 'formal',
                    'detail_level' => 'detailed',
                    'tone' => 'analytical'
                ],
                'common_patterns' => [
                    '[Mock] 두괄식 구성으로 핵심을 먼저 제시',
                    '[Mock] 데이터와 사례를 활용한 근거 제시',
                    '[Mock] 미래 전망으로 마무리'
                ],
                'emphasis' => [
                    '[Mock] 한국 관점에서의 시사점',
                    '[Mock] 실용적 대응 방안'
                ],
                'unique_expressions' => [
                    '[Mock] "주목할 점은..."',
                    '[Mock] "핵심은 바로..."'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // 기본 JSON 응답
        return json_encode([
            'result' => '[Mock] 분석이 완료되었습니다.',
            'confidence' => 0.9,
            'mock_mode' => true
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Mock 번역 생성
     */
    private function generateMockTranslation(string $text): string
    {
        return "[Mock 번역]\n\n이것은 Mock 모드에서 생성된 번역입니다. 실제 API 연동 시 정확한 번역이 제공됩니다.\n\n원문 길이: " . mb_strlen($text) . "자";
    }

    /**
     * Mock 요약 생성
     */
    private function generateMockSummary(): string
    {
        return "[Mock 요약]\n\n1. 이 기사는 주요 글로벌 이슈를 다루고 있습니다.\n2. 경제적, 정치적 영향이 분석되어 있습니다.\n3. 향후 전망과 대응 방안이 제시되어 있습니다.";
    }

    /**
     * Mock 분석 생성
     */
    private function generateMockAnalysis(): string
    {
        return "[Mock 분석]\n\n이것은 Mock 모드에서 생성된 분석입니다.\n\n실제 OpenAI API 키를 설정하면 GPT-4.1을 활용한 심층 분석이 제공됩니다.\n\n현재 시스템은 정상 작동 중입니다.";
    }

    /**
     * TTS (Text-to-Speech) 호출
     */
    public function textToSpeech(string $text, array $options = []): ?string
    {
        if ($this->mockMode) {
            return $this->generateMockAudio($text);
        }

        return $this->callTTSAPI($text, $options);
    }

    /**
     * 실제 TTS API 호출
     */
    private function callTTSAPI(string $text, array $options = []): string
    {
        $ttsConfig = $this->config['tts'];
        
        $payload = [
            'model' => $options['model'] ?? $ttsConfig['model'],
            'voice' => $options['voice'] ?? $ttsConfig['voice'],
            'input' => $text,
            'speed' => $options['speed'] ?? $ttsConfig['speed'],
            'response_format' => $options['format'] ?? $ttsConfig['response_format']
        ];

        $ch = curl_init($this->config['endpoints']['tts']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $this->config['timeout']
        ]);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenAI TTS API error: HTTP {$httpCode}");
        }

        // 오디오 파일 저장
        $filename = 'analysis_' . uniqid() . '.mp3';
        $storagePath = $this->config['output']['audio_storage_path'] ?? dirname(__DIR__, 3) . '/storage/audio';
        
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filePath = $storagePath . '/' . $filename;
        file_put_contents($filePath, $audioData);

        return '/storage/audio/' . $filename;
    }

    /**
     * Mock 오디오 생성 (placeholder)
     */
    private function generateMockAudio(string $text): string
    {
        // Mock 모드에서는 placeholder URL 반환
        $filename = 'mock_audio_' . substr(md5($text), 0, 8) . '.mp3';
        return '/storage/audio/' . $filename . '?mock=true';
    }

    /**
     * Embeddings 생성 (RAG용)
     */
    public function createEmbedding(string $text): array
    {
        if ($this->mockMode) {
            // Mock 임베딩 (1536차원 랜덤 벡터)
            return array_map(fn() => (mt_rand() / mt_getrandmax()) * 2 - 1, range(1, 1536));
        }

        $payload = [
            'model' => 'text-embedding-3-small',
            'input' => $text
        ];

        $ch = curl_init($this->config['endpoints']['embeddings']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $this->config['timeout']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenAI Embeddings API error: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        return $data['data'][0]['embedding'] ?? [];
    }
}
