<?php
/**
 * GIST EDU — Anthropic LLM 클라이언트
 * 코어 quota와 분리된 EDU 전용 API 키 사용
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

class EduLlmClient
{
    private string $apiKey;
    private string $model;
    private int $dailyCap;
    private string $logDir;

    public function __construct(?string $apiKey = null, string $model = 'claude-sonnet-4-20250514')
    {
        $this->apiKey = $apiKey ?: (getenv('EDU_ANTHROPIC_API_KEY') ?: getenv('ANTHROPIC_API_KEY'));
        if (empty($this->apiKey)) {
            throw new RuntimeException('EDU_ANTHROPIC_API_KEY or ANTHROPIC_API_KEY required');
        }
        $this->model = $model;
        $this->dailyCap = (int)(getenv('EDU_DAILY_LLM_CAP') ?: 1000);
        $this->logDir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($this->logDir)) @mkdir($this->logDir, 0755, true);
    }

    public function chat(
        string $systemPrompt,
        array $messages,
        int $maxTokens = 1024,
        float $temperature = 0.7
    ): array {
        if (!$this->checkDailyLimit()) {
            return ['error' => 'daily_limit_exceeded', 'message' => '일일 LLM 호출 한도 초과'];
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->incrementDailyCount();
        $this->log('chat', [
            'model' => $this->model,
            'http_code' => $httpCode,
            'tokens_used' => $maxTokens,
        ]);

        if ($error) {
            return ['error' => 'curl_error', 'message' => $error];
        }

        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            return ['error' => 'api_error', 'http_code' => $httpCode, 'message' => $err['error']['message'] ?? 'Unknown error'];
        }

        $data = json_decode($response, true);
        $content = $data['content'][0]['text'] ?? '';

        return [
            'success' => true,
            'content' => $content,
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? $this->model,
        ];
    }

    public function haiku(string $systemPrompt, array $messages, int $maxTokens = 512): array
    {
        $original = $this->model;
        $this->model = 'claude-haiku-4-20250514';
        $result = $this->chat($systemPrompt, $messages, $maxTokens, 0.3);
        $this->model = $original;
        return $result;
    }

    private function checkDailyLimit(): bool
    {
        $count = $this->getDailyCount();
        return $count < $this->dailyCap;
    }

    private function getDailyCount(): int
    {
        $file = $this->logDir . '/edu_llm_count_' . date('Y-m-d') . '.txt';
        if (!file_exists($file)) return 0;
        return (int)file_get_contents($file);
    }

    private function incrementDailyCount(): void
    {
        $file = $this->logDir . '/edu_llm_count_' . date('Y-m-d') . '.txt';
        $count = $this->getDailyCount() + 1;
        file_put_contents($file, (string)$count, LOCK_EX);
    }

    public function getRemainingCalls(): int
    {
        return max(0, $this->dailyCap - $this->getDailyCount());
    }

    private function log(string $action, array $data): void
    {
        $file = $this->logDir . '/edu_llm.log';
        $line = json_encode(array_merge([
            'ts' => date('Y-m-d H:i:s'),
            'action' => $action,
        ], $data), JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

function eduLlm(): EduLlmClient
{
    static $client = null;
    if ($client === null) {
        $client = new EduLlmClient();
    }
    return $client;
}
