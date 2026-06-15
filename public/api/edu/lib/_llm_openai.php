<?php
/**
 * GIST EDU — OpenAI LLM 클라이언트 (EduLlmClient 호환 인터페이스)
 *
 * chat()  → EDU_OPENAI_MODEL (기본 gpt-5.4)
 * haiku() → EDU_OPENAI_FAST_MODEL (기본 gpt-5.4-mini) — 경량 호출 별칭
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = eduFindProjectRoot();
require_once $root . 'src/agents/autoload.php';

use Agents\Services\OpenAIService;

class EduOpenAILlmClient
{
    private OpenAIService $openai;
    private string $model;
    private string $fastModel;
    private int $dailyCap;
    private string $logDir;

    public function __construct(?OpenAIService $openai = null)
    {
        $this->openai = $openai ?? new OpenAIService([]);
        if (!$this->openai->isConfigured()) {
            throw new RuntimeException('OPENAI_API_KEY required');
        }
        $this->model = getenv('EDU_OPENAI_MODEL') ?: 'gpt-5.4';
        $this->fastModel = getenv('EDU_OPENAI_FAST_MODEL') ?: 'gpt-5.4-mini';
        $this->dailyCap = (int) (getenv('EDU_DAILY_LLM_CAP') ?: 1000);
        $this->logDir = eduFindProjectRoot() . 'storage/logs';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
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

        try {
            $user = $this->extractUserContent($messages);
            $content = $this->openai->chat($systemPrompt, $user, $this->buildOptions(
                $this->model,
                $maxTokens,
                $temperature
            ));

            $this->incrementDailyCount();
            $this->log('chat', ['model' => $this->model]);

            return [
                'success' => true,
                'content' => $content,
                'model' => $this->model,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'api_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /** 이름 유지(haiku) — 실제 모델은 gpt-5.4-mini */
    public function haiku(string $systemPrompt, array $messages, int $maxTokens = 512): array
    {
        if (!$this->checkDailyLimit()) {
            return ['error' => 'daily_limit_exceeded', 'message' => '일일 LLM 호출 한도 초과'];
        }

        try {
            $user = $this->extractUserContent($messages);
            $tokenBudget = max($maxTokens, 512);
            $content = $this->openai->chat($systemPrompt, $user, $this->buildOptions(
                $this->fastModel,
                $tokenBudget,
                null,
                false
            ));

            $this->incrementDailyCount();
            $this->log('haiku', ['model' => $this->fastModel]);

            return [
                'success' => true,
                'content' => $content,
                'model' => $this->fastModel,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'api_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getFastModel(): string
    {
        return $this->fastModel;
    }

    public function getRemainingCalls(): int
    {
        return max(0, $this->dailyCap - $this->getDailyCount());
    }

    private function checkDailyLimit(): bool
    {
        return $this->getDailyCount() < $this->dailyCap;
    }

    private function getDailyCount(): int
    {
        $file = $this->logDir . '/edu_llm_count_' . date('Y-m-d') . '.txt';
        if (!is_file($file)) {
            return 0;
        }
        return (int) file_get_contents($file);
    }

    private function incrementDailyCount(): void
    {
        $file = $this->logDir . '/edu_llm_count_' . date('Y-m-d') . '.txt';
        file_put_contents($file, (string) ($this->getDailyCount() + 1), LOCK_EX);
    }

    private function log(string $action, array $data): void
    {
        $file = $this->logDir . '/edu_llm.log';
        $line = json_encode(array_merge([
            'ts' => date('Y-m-d H:i:s'),
            'action' => $action,
            'provider' => 'openai',
        ], $data), JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function extractUserContent(array $messages): string
    {
        $parts = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'user' && isset($msg['content'])) {
                $parts[] = (string) $msg['content'];
            }
        }
        return implode("\n", $parts);
    }

    /** gpt-5 계열은 Responses API에서 temperature 미지원 */
    private function buildOptions(string $model, int $maxTokens, ?float $temperature, bool $jsonMode = false): array
    {
        $opts = [
            'model' => $model,
            'max_tokens' => $maxTokens,
        ];
        if ($jsonMode) {
            $opts['json_mode'] = true;
        }
        if ($temperature !== null && !str_starts_with($model, 'gpt-5')) {
            $opts['temperature'] = $temperature;
        }
        return $opts;
    }
}
