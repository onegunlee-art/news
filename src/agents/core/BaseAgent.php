<?php
/**
 * Base Agent Abstract Class
 * 
 * 모든 Agent의 기본 클래스
 * Template Method 패턴 적용
 * 
 * @package Agents\Core
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Core;

use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Services\OpenAIService;
use Symfony\Component\Yaml\Yaml;

abstract class BaseAgent implements AgentInterface
{
    protected OpenAIService $openai;
    protected array $config;
    protected array $prompts;
    protected bool $initialized = false;

    public function __construct(OpenAIService $openai, array $config = [])
    {
        $this->openai = $openai;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Agent 초기화
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->loadPrompts();
        $this->onInitialize();
        $this->initialized = true;
    }

    /**
     * 서브클래스에서 오버라이드 가능한 초기화 훅
     */
    protected function onInitialize(): void
    {
        // 서브클래스에서 필요시 구현
    }

    /**
     * Agent 준비 상태 확인
     */
    public function isReady(): bool
    {
        return $this->initialized && $this->openai->isConfigured();
    }

    /**
     * 프롬프트 파일 로드
     */
    protected function loadPrompts(): void
    {
        $promptFile = $this->getPromptFilePath();
        
        if (file_exists($promptFile)) {
            $this->prompts = Yaml::parseFile($promptFile);
        } else {
            $this->prompts = $this->getDefaultPrompts();
        }
    }

    /**
     * 프롬프트 파일 경로
     */
    protected function getPromptFilePath(): string
    {
        $agentName = strtolower(str_replace('Agent', '', $this->getName()));
        return dirname(__DIR__) . "/config/prompts/{$agentName}.yaml";
    }

    /**
     * 기본 프롬프트 (파일이 없을 때 사용)
     */
    abstract protected function getDefaultPrompts(): array;

    /**
     * 기본 설정
     */
    protected function getDefaultConfig(): array
    {
        return [
            'timeout' => 60,
            'max_retries' => 3,
            'retry_delay' => 1000, // ms
            'model' => 'gpt-4.1',
            'temperature' => 0.7,
            'max_tokens' => 4000
        ];
    }

    /**
     * GPT API 호출
     */
    protected function callGPT(string $prompt, array $options = []): string
    {
        $this->ensureInitialized();

        $systemPrompt = $this->prompts['system'] ?? '';
        
        return $this->openai->chat(
            systemPrompt: $systemPrompt,
            userPrompt: $prompt,
            options: array_merge([
                'model' => $this->config['model'],
                'temperature' => $this->config['temperature'],
                'max_tokens' => $this->config['max_tokens']
            ], $options)
        );
    }

    /**
     * 프롬프트 템플릿 로드
     */
    protected function getPrompt(string $task): string
    {
        return $this->prompts['tasks'][$task]['prompt'] ?? '';
    }

    /**
     * 프롬프트에 변수 치환
     */
    protected function formatPrompt(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", (string)$value, $template);
        }
        return $template;
    }

    /**
     * 초기화 확인
     */
    protected function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }
    }

    /**
     * 설정 반환
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 로깅 헬퍼
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'agent' => $this->getName(),
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
        
        // 실제 환경에서는 PSR-3 Logger 사용
        error_log(json_encode($logEntry, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 재시도 로직 래퍼
     */
    protected function withRetry(callable $operation, int $maxRetries = null): mixed
    {
        $maxRetries = $maxRetries ?? $this->config['max_retries'];
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                $this->log("Attempt {$attempt} failed: {$e->getMessage()}", 'warning');
                
                if ($attempt < $maxRetries) {
                    usleep($this->config['retry_delay'] * 1000 * $attempt); // 지수 백오프
                }
            }
        }

        throw $lastException;
    }

    /**
     * 처리 결과에 Agent 정보 추가
     */
    protected function wrapResult(AgentContext $context): AgentContext
    {
        return $context->markProcessedBy($this->getName());
    }
}
