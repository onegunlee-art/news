<?php
/**
 * Claude (Anthropic) Service
 * 
 * Claude Sonnet 4.6 API 연동 서비스
 * 
 * @package Agents\Services
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Services;

class ClaudeService
{
    private string $apiKey;
    private string $model;
    private array $config;
    private bool $mockMode;
    private ?string $lastError = null;

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(array $config = [])
    {
        $configPath = dirname(__DIR__, 3) . '/config/claude.php';
        $defaultConfig = file_exists($configPath) ? require $configPath : [];
        $this->config = array_merge($defaultConfig, $config);
        
        $this->apiKey = $this->config['api_key'] 
            ?? $_ENV['ANTHROPIC_API_KEY'] 
            ?? getenv('ANTHROPIC_API_KEY') 
            ?? '';
        $this->model = $this->config['model'] ?? 'claude-sonnet-4-6';
        
        $this->mockMode = empty($this->apiKey) || ($this->config['mock_mode'] ?? false);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !$this->mockMode;
    }

    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setMockMode(bool $enabled): void
    {
        $this->mockMode = $enabled;
    }

    /**
     * Chat API 호출
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
        $model = $options['model'] ?? $this->model;
        $maxTokens = (int)($options['max_tokens'] ?? $this->config['max_tokens'] ?? 8192);
        $temperature = (float)($options['temperature'] ?? $this->config['temperature'] ?? 0.7);
        $timeout = (int)($options['timeout'] ?? $this->config['timeout'] ?? 180);

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];

        if (!empty($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        if ($temperature > 0) {
            $payload['temperature'] = $temperature;
        }

        $maxRetries = (int)($options['max_retries'] ?? 3);
        $attempt = 0;

        while (true) {
            $attempt++;
            
            $ch = curl_init(self::API_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $this->apiKey,
                    'anthropic-version: ' . self::API_VERSION,
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            if ($curlErrno !== 0) {
                $errMsg = "Claude API curl error (errno={$curlErrno}): {$curlError}";
                if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                    $errMsg = "Claude API 요청 시간 초과 ({$timeout}초)";
                }
                $this->lastError = $errMsg;
                error_log($errMsg);
                throw new \RuntimeException($errMsg);
            }

            // 429 Rate Limit → 재시도
            if ($httpCode === 429 && $attempt <= $maxRetries) {
                $retryAfter = $this->parseRetryAfter($response);
                $waitSec = $retryAfter > 0 ? min($retryAfter, 60) : min(pow(2, $attempt), 30);
                error_log("Claude 429 rate limit (attempt {$attempt}/{$maxRetries}). Waiting {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            // 5xx 서버 에러 → 재시도
            if ($httpCode >= 500 && $httpCode < 600 && $attempt <= $maxRetries) {
                $waitSec = min(pow(2, $attempt), 30);
                error_log("Claude {$httpCode} server error (attempt {$attempt}/{$maxRetries}). Retrying in {$waitSec}s...");
                sleep((int)$waitSec);
                continue;
            }

            // HTTP 에러
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
                $errMsg = "Claude API HTTP {$httpCode}: {$errorDetail}";
                $this->lastError = $errMsg;
                error_log($errMsg);
                throw new \RuntimeException($errMsg);
            }

            // 정상 응답 파싱
            $data = json_decode($response, true);
            if (!$data) {
                $errMsg = "Claude API: JSON 파싱 실패";
                $this->lastError = $errMsg;
                throw new \RuntimeException($errMsg);
            }

            // Claude 응답 구조: content[0].text
            $content = $data['content'] ?? [];
            if (!empty($content) && isset($content[0]['text'])) {
                $this->lastError = null;
                return $content[0]['text'];
            }

            // stop_reason 확인
            $stopReason = $data['stop_reason'] ?? null;
            if ($stopReason === 'max_tokens') {
                error_log("Claude API: max_tokens에 도달하여 응답이 잘렸습니다.");
            }

            $errMsg = "Claude API: 응답에서 텍스트를 찾을 수 없습니다. Response: " . mb_substr($response, 0, 500);
            $this->lastError = $errMsg;
            throw new \RuntimeException($errMsg);
        }
    }

    /**
     * Retry-After 헤더 파싱
     */
    private function parseRetryAfter(string $response): int
    {
        $data = json_decode($response, true);
        if (isset($data['error']['retry_after'])) {
            return (int)$data['error']['retry_after'];
        }
        return 0;
    }

    /**
     * Mock 응답 (개발/테스트용)
     */
    private function mockChatResponse(string $systemPrompt, string $userPrompt): string
    {
        return json_encode([
            'narration_paragraphs' => [
                '이것은 Claude Mock 모드의 테스트 응답입니다.',
                '실제 API 키가 설정되면 진짜 분석 결과가 반환됩니다.',
                'ANTHROPIC_API_KEY 환경 변수를 확인하세요.'
            ],
            'content_summary_paragraphs' => [
                'Mock 모드 요약 문단 1',
                'Mock 모드 요약 문단 2'
            ],
            'key_points' => [
                'Mock 키포인트 1',
                'Mock 키포인트 2'
            ],
            'critical_analysis' => [
                'why_important_paragraphs' => [
                    'Mock 중요성 문단 1',
                    'Mock 중요성 문단 2'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 연동 테스트용 간단한 호출
     */
    public function testConnection(): array
    {
        $debug = [
            'api_key_set' => !empty($this->apiKey),
            'api_key_prefix' => !empty($this->apiKey) ? substr($this->apiKey, 0, 12) . '...' : '(empty)',
            'model' => $this->model,
            'mock_mode' => $this->mockMode,
        ];

        if ($this->mockMode) {
            return [
                'success' => false,
                'message' => 'Mock 모드. ANTHROPIC_API_KEY가 설정되지 않았습니다.',
                'debug' => $debug,
            ];
        }

        try {
            $start = microtime(true);
            $response = $this->chat(
                'You are a test assistant. Reply briefly in Korean.',
                '연결 테스트입니다. "Claude 연결 성공"이라고만 대답하세요.',
                ['max_tokens' => 50, 'timeout' => 30]
            );
            $ms = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'success' => true,
                'message' => 'Claude API 연결 성공',
                'response' => $response,
                'duration_ms' => $ms,
                'debug' => $debug,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Claude API 연결 실패: ' . $e->getMessage(),
                'debug' => $debug,
                'error' => $e->getMessage(),
            ];
        }
    }
}
