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
     * 프롬프트 파일 로드 (YAML 파일이 있으면 로드, 없거나 파싱 불가 시 기본 프롬프트 사용)
     * Symfony YAML 의존성 없이 동작하도록 순수 PHP만 사용
     */
    protected function loadPrompts(): void
    {
        $promptFile = $this->getPromptFilePath();
        if (file_exists($promptFile)) {
            $parsed = $this->parseYamlFile($promptFile);
            if ($parsed !== null && $parsed !== []) {
                $this->prompts = $parsed;
                return;
            }
        }
        $this->prompts = $this->getDefaultPrompts();
    }

    /**
     * 간단한 YAML 파싱 (Symfony 의존성 없이 prompts/*.yaml 지원)
     * system, tasks 등 단순 구조만 처리
     */
    protected function parseYamlFile(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $out = [];
        $currentKey = null;
        $currentTask = null;
        $lines = explode("\n", $raw);
        $buffer = '';
        foreach ($lines as $line) {
            $trimmed = rtrim($line);
            if (preg_match('/^([a-z_]+):\s*\|?\s*$/', $trimmed, $m)) {
                if ($currentKey !== null && $buffer !== '') {
                    if ($currentKey === 'tasks' && $currentTask !== null) {
                        $out['tasks'] = $out['tasks'] ?? [];
                        $out['tasks'][$currentTask] = ['prompt' => trim($buffer)];
                    } else {
                        $out[$currentKey] = trim($buffer);
                    }
                    $buffer = '';
                }
                $currentKey = $m[1];
                $currentTask = null;
                continue;
            }
            if (preg_match('/^  ([a-z_]+):\s*\|?\s*$/', $trimmed, $m) && $currentKey === 'tasks') {
                if ($currentTask !== null && $buffer !== '') {
                    $out['tasks'] = $out['tasks'] ?? [];
                    $out['tasks'][$currentTask] = ['prompt' => trim($buffer)];
                    $buffer = '';
                }
                $currentTask = $m[1];
                continue;
            }
            if ($currentKey !== null && ($trimmed === '' || $trimmed[0] === ' ' || $trimmed[0] === "\t" || strpos($trimmed, ':') !== 0)) {
                if ($trimmed !== '' && $trimmed[0] !== '#') {
                    $buffer .= $line . "\n";
                }
            }
        }
        if ($currentKey !== null && $buffer !== '') {
            if ($currentKey === 'tasks' && $currentTask !== null) {
                $out['tasks'] = $out['tasks'] ?? [];
                $out['tasks'][$currentTask] = ['prompt' => trim($buffer)];
            } else {
                $out[$currentKey] = trim($buffer);
            }
        }
        return $out ?: null;
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
            'model' => 'gpt-5.2',
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

        $systemPrompt = $options['system_prompt'] ?? $this->prompts['system'] ?? '';
        
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
        
        // 실제 환경에서는 PSR-3 Logger 사용 (PHP 8+: error_log 인자는 string만 허용)
        $encoded = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        error_log($encoded !== false ? $encoded : sprintf('[%s] %s: %s', $logEntry['timestamp'], $logEntry['agent'], $message));
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
