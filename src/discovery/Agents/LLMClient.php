<?php
declare(strict_types=1);

namespace Discovery\Agents;

final class LLMResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $modelUsed,
        public readonly ?float $costUsd = null,
    ) {
    }
}

final class LLMClient
{
    private string $endpoint;

    public function __construct(
        private string $model,
        private readonly string $apiKey,
        private readonly ?string $fallback = null,
    ) {
        $openaiConfig = require dirname(__DIR__, 3) . '/config/openai.php';
        $this->endpoint = (string) ($openaiConfig['endpoints']['chat'] ?? 'https://api.openai.com/v1/responses');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @param array<string, mixed> $options max_output_tokens, tools, include
     */
    public function complete(string $system, string $user, array $options = []): LLMResponse
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('LLMClient: API key not configured');
        }

        try {
            return $this->callApi($this->model, $system, $user, $options);
        } catch (\Throwable $e) {
            if ($this->fallback !== null && $this->fallback !== $this->model) {
                return $this->callApi($this->fallback, $system, $user, $options);
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function callApi(string $model, string $system, string $user, array $options): LLMResponse
    {
        $payload = [
            'model' => $model,
            'instructions' => $system,
            'input' => $user,
            'max_output_tokens' => (int) ($options['max_output_tokens'] ?? 8000),
        ];

        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }
        if (!empty($options['include'])) {
            $payload['include'] = $options['include'];
        }

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => (int) ($options['timeout_sec'] ?? 120),
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('LLMClient curl error: ' . $err);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException('LLMClient HTTP ' . $httpCode . ': ' . mb_substr((string) $response, 0, 500));
        }

        $data = json_decode((string) $response, true);
        $text = $this->extractText(is_array($data) ? $data : null);
        if ($text === null || trim($text) === '') {
            throw new \RuntimeException('LLMClient empty response');
        }

        return new LLMResponse($text, $model);
    }

    /** @param array<string, mixed>|null $data */
    private function extractText(?array $data): ?string
    {
        if (!$data) {
            return null;
        }
        if (!empty($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $item) {
                if (($item['type'] ?? '') === 'message' && !empty($item['content'])) {
                    foreach ($item['content'] as $part) {
                        if (($part['type'] ?? '') === 'output_text' && !empty($part['text'])) {
                            return (string) $part['text'];
                        }
                    }
                }
            }
        }
        if (!empty($data['output_text'])) {
            return (string) $data['output_text'];
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function parseJsonFromText(string $text): array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('LLMClient invalid JSON: ' . mb_substr($text, 0, 300));
    }

    /** @param array<string, mixed> $agentConfig @param string $apiKey */
    public static function forAgent(string $agentName, array $agentConfig, string $apiKey): self
    {
        $agents = $agentConfig['agents'] ?? [];
        $spec = $agents[$agentName] ?? ['model' => 'gpt-4o', 'fallback' => null];

        return new self(
            (string) ($spec['model'] ?? 'gpt-4o'),
            $apiKey,
            isset($spec['fallback']) ? (string) $spec['fallback'] : null,
        );
    }
}
