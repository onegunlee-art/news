<?php
/**
 * Agent Pipeline
 * 
 * 모든 Agent를 순차적으로 실행하는 통합 파이프라인
 * 
 * 실행 순서 (v4.0):
 * 1. ValidationAgent - URL 검증 및 콘텐츠 추출
 * 2. AnalysisAgent - 구조화된 분석 (introduction_summary, section_analysis[], key_points, geopolitical_implication)
 * 3. NarrationAgent - 분석 결과 + 원문 → narration 생성
 * 4. EditingAgent - 문체/톤 교정 + 스타일 가이드 적용
 * 5. TTS 생성 - Google TTS (EditingAgent 이후)
 * 6. ThumbnailAgent - 썸네일 생성 (맨 마지막)
 * 
 * @package Agents\Pipeline
 * @author The Gist AI System
 * @version 4.0.0 - 구조화된 분석 파이프라인
 */

declare(strict_types=1);

namespace Agents\Pipeline;

use Agents\Core\AgentInterface;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Models\AnalysisResult;
use Agents\Agents\ValidationAgent;
use Agents\Agents\AnalysisAgent;
use Agents\Agents\NarrationAgent;
use Agents\Agents\EditingAgent;
use Agents\Agents\ThumbnailAgent;
use Agents\Services\OpenAIService;
use Agents\Services\ClaudeService;
use Agents\Services\GoogleTTSService;
use Agents\Services\WebScraperService;

class AgentPipeline
{
    private array $agents = [];
    private array $results = [];
    private bool $stopOnFailure = true;
    private array $config;
    private OpenAIService $openai;
    private ?ClaudeService $claude = null;
    private ?GoogleTTSService $googleTts = null;
    private ?string $lastError = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->stopOnFailure = $config['stop_on_failure'] ?? true;
        
        $this->openai = new OpenAIService($config['openai'] ?? []);
        $this->claude = new ClaudeService($config['claude'] ?? []);
        
        if (isset($this->config['google_tts']) && is_array($this->config['google_tts'])) {
            $this->googleTts = new GoogleTTSService($this->config['google_tts']);
        }
    }

    /**
     * 기본 파이프라인 설정 (v4.0 구조)
     */
    public function setupDefaultPipeline(): self
    {
        // 1. ValidationAgent - 스크래핑
        $scraperConfig = $this->config['scraper'] ?? [];
        if (!empty($this->config['project_root'])) {
            $agentsPath = rtrim($this->config['project_root'], '/\\') . '/config/agents.php';
            if (is_file($agentsPath)) {
                $agents = require $agentsPath;
                $base = $agents['scraper'] ?? [];
                $scraperConfig = array_merge($base, $scraperConfig);
            }
        }
        $scraper = new WebScraperService($scraperConfig);
        $this->addAgent(new ValidationAgent($this->openai, $scraper, $this->config['validation'] ?? []));

        // 2. AnalysisAgent - 구조화된 분석 (Claude)
        $this->addAgent(new AnalysisAgent($this->openai, $this->config['analysis'] ?? [], $this->claude));

        // 3. NarrationAgent - Narration 생성 (Claude)
        $this->addAgent(new NarrationAgent($this->openai, $this->config['narration'] ?? [], $this->claude));

        // 4. EditingAgent - 문체/톤 교정 (Claude)
        $this->addAgent(new EditingAgent($this->openai, $this->config['editing'] ?? [], $this->claude));

        // 5. ThumbnailAgent - 썸네일 생성 (OpenAI DALL·E, 맨 마지막)
        $this->addAgent(new ThumbnailAgent($this->openai, $this->config));

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
    public function run(string $url, ?ArticleData $preloadedArticle = null): PipelineResult
    {
        $this->results = [];
        $this->lastError = null;

        $startTime = microtime(true);
        $context = new AgentContext($url);

        if ($preloadedArticle !== null) {
            $context = $context->withArticleData($preloadedArticle);
        }

        foreach ($this->agents as $agent) {
            $agent->initialize();
        }

        foreach ($this->agents as $agent) {
            $agentName = $agent->getName();

            if ($preloadedArticle !== null && $agentName === 'ValidationAgent') {
                continue;
            }

            try {
                $result = $agent->process($context);
                $this->results[$agentName] = $result;

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

                $articleData = $result->get('article');
                if (is_array($articleData)) {
                    $context = $context->withArticleData(ArticleData::fromArray($articleData));
                }

                if (in_array($agentName, ['AnalysisAgent', 'NarrationAgent', 'EditingAgent']) && $result->isSuccess()) {
                    $data = $result->getData();
                    if (is_array($data)) {
                        $context = $context->withAnalysisResult(AnalysisResult::fromArray($data));
                    }
                }

                if ($agentName === 'EditingAgent' && $result->isSuccess()) {
                    $context = $this->generateTTS($context);
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
     * TTS 생성 (EditingAgent 이후 실행)
     */
    private function generateTTS(AgentContext $context): AgentContext
    {
        $analysisResult = $context->getAnalysisResult();
        if ($analysisResult === null) {
            return $context;
        }

        $narration = $analysisResult->getNarration();
        if (empty($narration)) {
            return $context;
        }

        $enableTTS = $this->config['analysis']['enable_tts'] ?? false;
        if (!$enableTTS) {
            return $context;
        }

        try {
            $audioUrl = null;
            
            if ($this->googleTts !== null) {
                $voice = $this->config['analysis']['tts_voice'] ?? null;
                $options = $voice !== null ? ['voice' => $voice] : [];
                $audioUrl = $this->googleTts->textToSpeech($narration, $options);
            } else {
                $audioUrl = $this->openai->textToSpeech($narration);
            }

            if ($audioUrl) {
                $updatedResult = $analysisResult->withAudioUrl($audioUrl);
                $context = $context->withAnalysisResult($updatedResult);
                
                $this->results['TTS'] = AgentResult::success(['audio_url' => $audioUrl], ['agent' => 'TTS']);
            }
        } catch (\Throwable $e) {
            error_log("TTS generation failed: " . $e->getMessage());
            $this->results['TTS'] = AgentResult::failure($e->getMessage(), 'TTS');
        }

        return $context;
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
     * 최종 결과
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
     * 분석 결과 추출
     */
    public function getAnalysisResult(): ?AnalysisResult
    {
        $editingResult = $this->results['EditingAgent'] ?? null;
        if ($editingResult && $editingResult->isSuccess()) {
            $data = $editingResult->getData();
            if (is_array($data)) {
                return AnalysisResult::fromArray($data);
            }
        }

        $narrationResult = $this->results['NarrationAgent'] ?? null;
        if ($narrationResult && $narrationResult->isSuccess()) {
            $data = $narrationResult->getData();
            if (is_array($data)) {
                return AnalysisResult::fromArray($data);
            }
        }

        $analysisResult = $this->results['AnalysisAgent'] ?? null;
        if ($analysisResult && $analysisResult->isSuccess()) {
            $data = $analysisResult->getData();
            if (is_array($data)) {
                return AnalysisResult::fromArray($data);
            }
        }

        return null;
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
        public readonly float $duration,
        public readonly AgentContext $context,
        public readonly ?string $error = null,
        public readonly bool $needsClarification = false,
        public readonly ?array $clarificationData = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getContext(): AgentContext
    {
        return $this->context;
    }

    public function needsClarification(): bool
    {
        return $this->needsClarification;
    }

    public function getClarificationData(): ?array
    {
        return $this->clarificationData;
    }

    /**
     * 최종 분석 결과 반환 (하위 호환)
     */
    public function getFinalAnalysis(): ?array
    {
        $analysisResult = $this->context->getAnalysisResult();
        if ($analysisResult !== null) {
            return $analysisResult->toArray();
        }

        foreach (['EditingAgent', 'NarrationAgent', 'AnalysisAgent'] as $agentName) {
            $result = $this->results[$agentName] ?? null;
            if ($result && $result->isSuccess()) {
                $data = $result->getData();
                if (is_array($data) && !empty($data)) {
                    return $data;
                }
            }
        }

        return null;
    }
}
