<?php
/**
 * OpenAI Service
 * 
 * GPT-5.2 API 연동 서비스
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
        $this->model = $this->config['model'] ?? 'gpt-5.2';
        
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
     * Streaming Chat – Responses API (stream: true).
     * $onToken(string $text) is called for every text delta.
     * $onDone(string $fullText) is called when the stream ends.
     */
    public function chatStream(string $systemPrompt, string $userPrompt, callable $onToken, ?callable $onDone = null, array $options = []): void
    {
        if ($this->mockMode) {
            $mock = $this->mockChatResponse($systemPrompt, $userPrompt);
            // Simulate streaming by splitting into words
            $words = explode(' ', $mock);
            $full = '';
            foreach ($words as $i => $word) {
                $chunk = ($i > 0 ? ' ' : '') . $word;
                $full .= $chunk;
                $onToken($chunk);
            }
            if ($onDone) {
                $onDone($full);
            }
            return;
        }

        $model = $options['model'] ?? $this->model;
        $payload = [
            'model' => $model,
            'instructions' => $systemPrompt,
            'input' => $userPrompt,
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'max_output_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
            'stream' => true,
        ];

        $timeout = (int) ($options['timeout'] ?? $this->config['timeout'] ?? 120);
        $endpoint = $this->config['endpoints']['chat'] ?? 'https://api.openai.com/v1/responses';

        $fullText = '';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => false,
            // Process SSE chunks as they arrive
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use ($onToken, &$fullText): int {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line === 'data: [DONE]') {
                        continue;
                    }
                    if (strpos($line, 'data: ') === 0) {
                        $json = substr($line, 6);
                        $parsed = json_decode($json, true);
                        if ($parsed === null) {
                            continue;
                        }
                        // Responses API streaming: type=response.output_text.delta → delta field
                        $type = $parsed['type'] ?? '';
                        if ($type === 'response.output_text.delta') {
                            $delta = $parsed['delta'] ?? '';
                            if ($delta !== '') {
                                $fullText .= $delta;
                                $onToken($delta);
                            }
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            error_log("OpenAI Stream curl error: {$curlError}");
        }
        if ($httpCode !== 200 && $httpCode !== 0) {
            error_log("OpenAI Stream HTTP error: {$httpCode}");
        }

        if ($onDone) {
            $onDone($fullText);
        }
    }

    /**
     * 실제 API 호출 (에러 상세 로깅, 타임아웃 확장, 429 재시도)
     */
    private function callChatAPI(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;
        $payload = [
            'model' => $model,
            'instructions' => $systemPrompt,
            'input' => $userPrompt,
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'max_output_tokens' => $options['max_tokens'] ?? $this->config['max_tokens']
        ];

        $timeout = (int)($options['timeout'] ?? $this->config['timeout'] ?? 60);
        $endpoint = $this->config['endpoints']['chat'] ?? 'https://api.openai.com/v1/responses';
        $maxRetries = (int)($options['max_retries'] ?? 3);
        $attempt = 0;

        while (true) {
            $attempt++;
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            // curl 자체 에러
            if ($curlErrno !== 0) {
                $errMsg = "OpenAI API curl error (errno={$curlErrno}): {$curlError}";
                if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                    $errMsg = "OpenAI API 요청 시간 초과 ({$timeout}초). 기사가 너무 길거나 서버가 느립니다.";
                }
                error_log($errMsg);
                throw new \RuntimeException($errMsg);
            }

            // 429 Rate Limit → 재시도
            if ($httpCode === 429 && $attempt <= $maxRetries) {
                $retryAfter = $this->parseRetryAfter($response);
                $waitSec = $retryAfter > 0 ? min($retryAfter, 60) : min(pow(2, $attempt), 30);
                error_log("OpenAI 429 rate limit hit (attempt {$attempt}/{$maxRetries}). Waiting {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            // 5xx 서버 에러 → 재시도
            if ($httpCode >= 500 && $httpCode < 600 && $attempt <= $maxRetries) {
                $waitSec = min(pow(2, $attempt), 30);
                error_log("OpenAI {$httpCode} server error (attempt {$attempt}/{$maxRetries}). Retrying in {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            // HTTP 에러 (재시도 소진 후 또는 다른 에러)
            if ($httpCode !== 200) {
                $errorDetail = '';
                if ($response) {
                    $errData = json_decode($response, true);
                    if (isset($errData['error']['message'])) {
                        $errorDetail = $errData['error']['message'];
                    } else {
                        $errorDetail = mb_substr((string)$response, 0, 500);
                    }
                }
                $errMsg = "OpenAI API error (HTTP {$httpCode}, model={$model}): {$errorDetail}";
                error_log($errMsg);
                throw new \RuntimeException($errMsg);
            }

            // 성공
            break;
        }

        $data = json_decode($response, true);
        $text = $this->extractTextFromResponsesOutput($data);
        if ($text === null) {
            error_log("OpenAI API: unexpected response structure: " . mb_substr((string)$response, 0, 500));
            throw new \RuntimeException("OpenAI API: 응답에 content가 없습니다.");
        }

        return $text;
    }

    /**
     * Retry-After 헤더 또는 에러 body에서 대기 시간 추출
     */
    private function parseRetryAfter($response): float
    {
        if (!is_string($response) || $response === '') return 0;
        $data = @json_decode($response, true);
        // OpenAI 에러 응답에서 retry-after 힌트 파싱
        $msg = $data['error']['message'] ?? '';
        if (preg_match('/try again in ([\d.]+)s/i', $msg, $m)) {
            return (float)$m[1];
        }
        if (preg_match('/retry.after[:\s]+([\d.]+)/i', $msg, $m)) {
            return (float)$m[1];
        }
        return 0;
    }

    /**
     * Responses API 응답에서 출력 텍스트 추출 (output[].content[].text)
     */
    private function extractTextFromResponsesOutput(array $data): ?string
    {
        $output = $data['output'] ?? null;
        if (!is_array($output)) {
            return null;
        }
        $parts = [];
        foreach ($output as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            $content = $item['content'] ?? [];
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'output_text' && isset($block['text'])) {
                    $parts[] = $block['text'];
                }
            }
        }
        return $parts === [] ? null : implode('', $parts);
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
     * Mock JSON 응답 생성 (v3 스키마: content_summary, key_points 4+, narration 900자+, Critique 미사용)
     */
    private function generateMockJsonResponse(string $prompt): string
    {
        // full_analysis 요청 (v3 스키마: content_summary, narration 장문, critical_analysis 비움)
        if (strpos($prompt, 'key_points') !== false || strpos($prompt, 'narration') !== false || strpos($prompt, 'news_title') !== false || strpos($prompt, 'content_summary') !== false || strpos($prompt, 'translation_summary') !== false) {
            $longNarration = '[Mock 내레이션] 오늘 주목할 뉴스입니다. 글로벌 정책의 대전환이 시작되었습니다. 주요 국가들이 잇따라 새로운 경제 정책을 발표하면서 세계 경제 질서에 큰 변화가 예고되고 있습니다. 특히 이번 정책 변화는 한국의 반도체, 자동차 등 주력 산업에 직접적인 영향을 미칠 것으로 보입니다. 전문가들은 한국 기업들이 선제적으로 대응 전략을 마련해야 한다고 조언합니다. 구체적으로 살펴보면, 먼저 유럽 연합을 중심으로 한 탄소 국경조정메커니즘 도입이 예정되어 있으며, 이는 수출 의존도가 높은 한국 기업에 추가 비용을 부담시키게 됩니다. 둘째, 미국의 인플레이션 감축법(IRA)과 반도체법(CHIPS)에 따른 보조금 경쟁이 격화되면서 한국 기업의 해외 투자 결정에 영향을 주고 있습니다. 셋째, 중국의 산업 정책 변화와 수요 둔화가 글로벌 공급망 재편을 가속하고 있어, 한국의 중간재 수출 구조 조정이 필요하다는 지적이 나옵니다. 마지막으로, 국제 에너지 기구(IEA) 등은 2030년까지 재생에너지 비중이 크게 높아질 것으로 전망하며, 한국의 에너지 다각화와 원자력·수소 투자가 중요한 변수가 될 것으로 보입니다. (이것은 Mock 모드 응답입니다. 실제 OpenAI API 키를 설정하면 GPT-5.2 기반 정밀 분석이 제공됩니다.)';
            return json_encode([
                'news_title' => '[Mock] 글로벌 정책 변화가 한국 경제에 미치는 영향',
                'translation_summary' => '[Mock] 이 기사는 글로벌 이슈에 대한 심층 분석을 담고 있습니다. 주요 국가들의 정책 변화와 그 영향을 다루며, 향후 전망을 제시합니다.',
                'content_summary' => "## 개요\n[Mock] 본 기사는 글로벌 경제 정책의 전환과 한국 경제에 대한 영향을 다룹니다.\n\n## 도입\n주요 선진국들이 잇따라 새로운 산업·에너지 정책을 발표하면서 세계 경제 질서 재편이 본격화되고 있습니다.\n\n## 전개\n- 유럽의 탄소국경조정제도(CBAM) 도입 시한이 다가오고 있으며, 수출 기업의 탄소 발자국 보고 의무가 강화됩니다.\n- 미국 IRA·CHIPS 법에 따른 보조금 경쟁이 치열해지며, 한국의 배터리·반도체 기업의 현지 투자 결정에 영향을 주고 있습니다.\n- 중국의 산업 구조 조정과 내수 둔화가 글로벌 공급망에 변수를 만들고 있습니다.\n\n## 결론\n전문가들은 한국이 에너지 다각화와 기술 경쟁력 강화에 선제 투자할 필요가 있다고 조언합니다. (Mock 응답)",
                'key_points' => [
                    '[Mock] 주요 국가들의 정책 방향 전환이 감지되었으며 이는 글로벌 경제 질서를 변화시킬 수 있습니다.',
                    '[Mock] 경제적 파급효과가 예상보다 크게 나타날 것으로 분석되었습니다.',
                    '[Mock] 한국 기업과 정부의 대응 전략이 시급히 필요한 시점입니다.',
                    '[Mock] 전문가들은 이번 변화가 향후 3~5년간 산업 구조에 영향을 줄 것으로 전망합니다.'
                ],
                'narration' => $longNarration,
                'critical_analysis' => []
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
        return "[Mock 분석]\n\n이것은 Mock 모드에서 생성된 분석입니다.\n\n실제 OpenAI API 키를 설정하면 GPT-5.2를 활용한 심층 분석이 제공됩니다.\n\n현재 시스템은 정상 작동 중입니다.";
    }

    /** OpenAI TTS API 요청당 최대 문자 수 (문서 기준 4096, 여유 두고 4000) */
    private const TTS_MAX_CHARS = 4000;

    /**
     * TTS (Text-to-Speech) 호출
     * 4096자 초과 시 청크로 나누어 요청 후 오디오를 이어붙여 전체 재생 가능하게 함.
     */
    public function textToSpeech(string $text, array $options = []): ?string
    {
        if ($this->mockMode) {
            return $this->generateMockAudio($text);
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return $this->callTTSAPI($text, $options);
    }

    /**
     * 실제 TTS API 호출 (전체 텍스트 지원: 초과 시 청크 분할 후 병합)
     */
    private function callTTSAPI(string $text, array $options = []): string
    {
        $len = mb_strlen($text);
        if ($len <= self::TTS_MAX_CHARS) {
            $audioData = $this->callTTSAPISingleRaw($text, $options);
            return $this->saveAudioFile($audioData);
        }

        // 청크 분할 (문장 경계 우선, 그 다음 4000자 단위)
        $chunks = $this->splitTextForTTS($text, self::TTS_MAX_CHARS);
        $audioParts = [];
        foreach ($chunks as $chunk) {
            $audioParts[] = $this->callTTSAPISingleRaw($chunk, $options);
        }
        $combined = implode('', $audioParts);
        return $this->saveAudioFile($combined);
    }

    /**
     * TTS용 텍스트를 최대 길이 이하로 분할 (문장 경계 우선)
     */
    private function splitTextForTTS(string $text, int $maxLen): array
    {
        $chunks = [];
        $offset = 0;
        $len = mb_strlen($text);

        while ($offset < $len) {
            $remain = mb_substr($text, $offset, $maxLen + 1);
            $take = mb_strlen($remain);
            if ($take <= $maxLen) {
                $chunks[] = $remain;
                $offset += $take;
                continue;
            }
            // 문장 끝(. ! ?) 찾기 (위치는 0일 수 있으므로 !== false 로 비교)
            $search = mb_substr($remain, 0, $maxLen);
            $lastDot = -1;
            foreach (['。', '.', '!', '?'] as $sep) {
                $p = mb_strrpos($search, $sep);
                if ($p !== false && $p > $lastDot) {
                    $lastDot = $p;
                }
            }
            if ($lastDot >= (int)($maxLen * 0.5)) {
                $chunk = mb_substr($remain, 0, $lastDot + 1);
                $chunks[] = $chunk;
                $offset += mb_strlen($chunk);
            } else {
                $chunks[] = mb_substr($remain, 0, $maxLen);
                $offset += $maxLen;
            }
        }

        return $chunks;
    }

    /**
     * 단일 TTS API 요청 후 오디오 바이너리 반환
     */
    private function callTTSAPISingleRaw(string $text, array $options = []): string
    {
        $ttsConfig = $this->config['tts'];
        $payload = [
            'model' => $options['model'] ?? $ttsConfig['model'],
            'voice' => $options['voice'] ?? $ttsConfig['voice'],
            'input' => $text,
            'speed' => $options['speed'] ?? $ttsConfig['speed'],
            'response_format' => $options['format'] ?? $ttsConfig['response_format']
        ];

        $timeout = (int) ($options['timeout'] ?? $this->config['timeout'] ?? 120);
        $ch = curl_init($this->config['endpoints']['tts']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $audioData === false) {
            $msg = "OpenAI TTS API error: HTTP {$httpCode}";
            if ($curlErr !== '') {
                $msg .= " curl: {$curlErr}";
            }
            if (is_string($audioData) && strlen($audioData) > 0) {
                $decoded = json_decode($audioData, true);
                $errDetail = $decoded['error']['message'] ?? substr($audioData, 0, 200);
                $msg .= " | " . (is_string($errDetail) ? $errDetail : json_encode($errDetail));
            }
            throw new \RuntimeException($msg);
        }

        return $audioData;
    }

    /**
     * 오디오 바이너리를 파일로 저장하고 URL 반환
     */
    private function saveAudioFile(string $audioData): string
    {
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

        $endpoint = $this->config['endpoints']['embeddings'] ?? 'https://api.openai.com/v1/embeddings';
        $maxRetries = 3;
        $attempt = 0;

        while (true) {
            $attempt++;
            $ch = curl_init($endpoint);
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

            // 429/5xx → 재시도
            if (($httpCode === 429 || $httpCode >= 500) && $attempt <= $maxRetries) {
                $waitSec = ($httpCode === 429)
                    ? max($this->parseRetryAfter($response), min(pow(2, $attempt), 30))
                    : min(pow(2, $attempt), 30);
                error_log("OpenAI Embeddings {$httpCode} (attempt {$attempt}/{$maxRetries}). Waiting {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            if ($httpCode !== 200) {
                throw new \RuntimeException("OpenAI Embeddings API error: HTTP {$httpCode}");
            }

            break;
        }

        $data = json_decode($response, true);
        return $data['data'][0]['embedding'] ?? [];
    }

    /**
     * DALL·E 3로 썸네일 이미지 생성. 생성된 이미지는 저장 후 URL 반환.
     * API URL은 만료되므로 다운로드해 storage에 저장한다.
     *
     * @param string $prompt 영문 이미지 설명 (예: "Editorial illustration for news about...")
     * @param array $options model, size, quality 등 오버라이드
     * @return string|null 저장된 이미지 URL 경로 (/storage/thumbnails/xxx.png) 또는 실패 시 null
     */
    public function createImage(string $prompt, array $options = []): ?string
    {
        if ($this->mockMode || $prompt === '') {
            return null;
        }

        $imgConfig = $this->config['images'] ?? [];
        $payload = [
            'model' => $options['model'] ?? $imgConfig['model'] ?? 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $options['size'] ?? $imgConfig['size'] ?? '1024x1024',
            'quality' => $options['quality'] ?? $imgConfig['quality'] ?? 'standard',
            'response_format' => $options['response_format'] ?? $imgConfig['response_format'] ?? 'url',
            'style' => $options['style'] ?? $imgConfig['style'] ?? 'vivid',
        ];

        $endpoint = $this->config['endpoints']['images'] ?? 'https://api.openai.com/v1/images/generations';
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? $this->config['timeout'] ?? 60),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('OpenAI Images API error: HTTP ' . $httpCode . ' ' . substr($response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);
        $imageUrl = $data['data'][0]['url'] ?? null;
        if ($imageUrl === null) {
            return null;
        }

        // 만료되는 URL에서 다운로드 후 저장
        $storagePath = $imgConfig['storage_path'] ?? dirname(__DIR__, 3) . '/storage/thumbnails';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        $filename = 'dalle_' . uniqid() . '.png';
        $filePath = $storagePath . '/' . $filename;

        $imgData = @file_get_contents($imageUrl);
        if ($imgData === false || strlen($imgData) === 0) {
            error_log('OpenAI Images: failed to download generated image from URL');
            return null;
        }
        file_put_contents($filePath, $imgData);

        return '/storage/thumbnails/' . $filename;
    }
}
