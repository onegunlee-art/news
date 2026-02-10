<?php
/**
 * Agent Pipeline
 * 
 * 모든 Agent를 순차적으로 실행하는 통합 파이프라인
 * 
 * 실행 순서:
 * 1. ValidationAgent - URL 검증 및 콘텐츠 추출
 * 2. ThumbnailAgent - 썸네일을 일러스트/캐리커처 스타일로 교체 (저작권 회피)
 * 3. AnalysisAgent - 분석, 요약, 번역
 * 4. InterpretAgent - RAG 기반 해석
 * 5. LearningAgent - 스타일 적용
 * 
 * @package Agents\Pipeline
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Pipeline;

use Agents\Core\AgentInterface;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Models\AnalysisResult;
use Agents\Agents\ValidationAgent;
use Agents\Agents\ThumbnailAgent;
use Agents\Agents\AnalysisAgent;
use Agents\Agents\InterpretAgent;
use Agents\Agents\LearningAgent;
use Agents\Services\OpenAIService;
use Agents\Services\GoogleTTSService;
use Agents\Services\WebScraperService;

class AgentPipeline
{
    private array $agents = [];
    private array $results = [];
    private bool $stopOnFailure = true;
    private array $config;
    private OpenAIService $openai;
    private ?string $lastError = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->stopOnFailure = $config['stop_on_failure'] ?? true;
        
        // OpenAI 서비스 초기화
        $this->openai = new OpenAIService($config['openai'] ?? []);
    }

    /**
     * 기본 파이프라인 설정 (모든 Agent 포함)
     */
    public function setupDefaultPipeline(): self
    {
        // 1. Validation Agent
        $scraper = new WebScraperService($this->config['scraper'] ?? []);
        $this->addAgent(new ValidationAgent($this->openai, $scraper, $this->config['validation'] ?? []));

        // 2. Thumbnail Agent (저작권 회피: og:image → 일러스트 스타일)
        $this->addAgent(new ThumbnailAgent($this->openai, $this->config));

        // 3. Analysis Agent (Google TTS 사용 시 주입)
        $googleTts = isset($this->config['google_tts']) && is_array($this->config['google_tts'])
            ? new GoogleTTSService($this->config['google_tts'])
            : null;
        $this->addAgent(new AnalysisAgent($this->openai, $this->config['analysis'] ?? [], $googleTts));

        // 4. Interpret Agent (옵션)
        if ($this->config['enable_interpret'] ?? true) {
            $this->addAgent(new InterpretAgent($this->openai, $this->config['interpret'] ?? []));
        }

        // 5. Learning Agent (옵션)
        if ($this->config['enable_learning'] ?? true) {
            $this->addAgent(new LearningAgent($this->openai, $this->config['learning'] ?? []));
        }

        return $this;
    }

    /**
     * Agent 추가
     */
    public function addAgent(AgentInterface $agent): self
    {
        $this->agents[] = $agent;
        return $this;
    }

    /**
     * 특정 Agent 가져오기
     */
    public function getAgent(string $name): ?AgentInterface
    {
        foreach ($this->agents as $agent) {
            if ($agent->getName() === $name) {
                return $agent;
            }
        }
        return null;
    }

    /**
     * 파이프라인 실행
     */
    public function run(string $url): PipelineResult
    {
        $this->results = [];
        $this->lastError = null;

        $startTime = microtime(true);
        $context = new AgentContext($url);

        // 모든 Agent 초기화
        foreach ($this->agents as $agent) {
            $agent->initialize();
        }

        // 순차 실행
        foreach ($this->agents as $agent) {
            $agentName = $agent->getName();
            
            try {
                $result = $agent->process($context);
                $this->results[$agentName] = $result;

                // 실패 시 처리
                if (!$result->isSuccess() && !$result->isPartial()) {
                    $this->lastError = $result->getFirstError();
                    
                    if ($this->stopOnFailure) {
                        return new PipelineResult(
                            success: false,
                            results: $this->results,
                            error: $this->lastError,
                            duration: microtime(true) - $startTime,
                            context: $context
                        );
                    }
                }

                // 명확화 필요 (partial) 시 처리
                if ($result->isPartial()) {
                    return new PipelineResult(
                        success: false,
                        results: $this->results,
                        needsClarification: true,
                        clarificationData: $result->getData(),
                        duration: microtime(true) - $startTime,
                        context: $context
                    );
                }

                // 컨텍스트 업데이트: Agent가 'article'을 반환하면 다음 Agent에 전달
                $articleData = $result->get('article');
                if (is_array($articleData)) {
                    $context = $context->withArticleData(ArticleData::fromArray($articleData));
                }

                // AnalysisAgent 성공 시 분석 결과를 context에 넣어 InterpretAgent 등이 사용할 수 있게 함
                if ($agentName === 'AnalysisAgent' && $result->isSuccess()) {
                    $data = $result->getData();
                    if (is_array($data) && (isset($data['key_points']) || isset($data['translation_summary']))) {
                        $context = $context->withAnalysisResult(AnalysisResult::fromArray($data));
                    }
                }
            } catch (\Throwable $e) {
                $this->lastError = "[{$agentName}] " . $e->getMessage();
                $this->results[$agentName] = AgentResult::failure($e->getMessage(), $agentName);
                
                if ($this->stopOnFailure) {
                    return new PipelineResult(
                        success: false,
                        results: $this->results,
                        error: $this->lastError,
                        duration: microtime(true) - $startTime,
                        context: $context
                    );
                }
            }
        }

        return new PipelineResult(
            success: true,
            results: $this->results,
            duration: microtime(true) - $startTime,
            context: $context
        );
    }

    /**
     * 특정 Agent만 실행 (테스트용)
     */
    public function runAgent(string $agentName, AgentContext $context): AgentResult
    {
        $agent = $this->getAgent($agentName);
        
        if ($agent === null) {
            return AgentResult::failure("Agent not found: {$agentName}", 'Pipeline');
        }

        $agent->initialize();
        return $agent->process($context);
    }

    /**
     * 모든 결과 반환
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * 최종 결과 (마지막 Agent의 결과)
     */
    public function getFinalResult(): ?AgentResult
    {
        if (empty($this->results)) {
            return null;
        }
        return end($this->results);
    }

    /**
     * 마지막 에러
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Agent 목록
     */
    public function getAgentNames(): array
    {
        return array_map(fn($a) => $a->getName(), $this->agents);
    }

    /**
     * Mock 모드 여부
     */
    public function isMockMode(): bool
    {
        return $this->openai->isMockMode();
    }

    /**
     * OpenAI 서비스 반환
     */
    public function getOpenAIService(): OpenAIService
    {
        return $this->openai;
    }
}

/**
 * Pipeline 실행 결과
 */
class PipelineResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $results,
        public readonly ?string $error = null,
        public readonly bool $needsClarification = false,
        public readonly ?array $clarificationData = null,
        public readonly float $duration = 0,
        public readonly ?AgentContext $context = null
    ) {}

    /**
     * 성공 여부
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * 명확화 필요 여부
     */
    public function needsClarification(): bool
    {
        return $this->needsClarification;
    }

    /**
     * 에러 메시지
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * 특정 Agent 결과
     */
    public function getAgentResult(string $name): ?AgentResult
    {
        return $this->results[$name] ?? null;
    }

    /**
     * 최종 분석 결과 추출
     */
    public function getFinalAnalysis(): ?array
    {
        // AnalysisAgent 결과 우선
        $analysisResult = $this->getAgentResult('AnalysisAgent');
        if ($analysisResult && $analysisResult->isSuccess()) {
            return $analysisResult->getData();
        }

        // LearningAgent에서 스타일 적용된 결과
        $learningResult = $this->getAgentResult('LearningAgent');
        if ($learningResult && $learningResult->isSuccess()) {
            $data = $learningResult->getData();
            if ($data['styled'] ?? false) {
                return $data['output'];
            }
            return $data['original'] ?? null;
        }

        return null;
    }

    /**
     * 배열로 변환
     */
    public function toArray(): array
    {
        $resultsArray = [];
        foreach ($this->results as $name => $result) {
            $resultsArray[$name] = $result->toArray();
        }

        return [
            'success' => $this->success,
            'error' => $this->error,
            'needs_clarification' => $this->needsClarification,
            'clarification_data' => $this->clarificationData,
            'duration_ms' => round($this->duration * 1000, 2),
            'agents' => array_keys($this->results),
            'results' => $resultsArray,
            'final_analysis' => $this->getFinalAnalysis()
        ];
    }

    /**
     * JSON 변환
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_UNESCAPED_UNICODE);
    }
}
