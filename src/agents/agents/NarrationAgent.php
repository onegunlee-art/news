<?php
/**
 * Narration Agent
 * 
 * 분석 결과 + 원문을 기반으로 Narration 생성 (Claude Sonnet 4.6)
 * - 구조화된 분석 결과를 자연스러운 설명문으로 변환
 * - 문단 구분이 명확한 narration_paragraphs[] 배열 출력
 * 
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Agents;

use Agents\Core\BaseAgent;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Models\AnalysisResult;
use Agents\Services\OpenAIService;
use Agents\Services\ClaudeService;

class NarrationAgent extends BaseAgent
{
    private ?ClaudeService $claude = null;

    public function __construct(OpenAIService $openai, array $config = [], ?ClaudeService $claude = null)
    {
        parent::__construct($openai, $config);
        $this->claude = $claude ?? new ClaudeService();
    }

    public function getName(): string
    {
        return 'NarrationAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 "The Gist"의 내레이션 작가입니다. 분석 결과를 바탕으로 청자가 귀로만 들어도 기사 전체를 이해할 수 있는 자연스러운 설명문을 작성합니다.'
        ];
    }

    public function validate(mixed $input): bool
    {
        if ($input instanceof AgentContext) {
            $analysisResult = $input->getAnalysisResult();
            return $analysisResult !== null;
        }
        return false;
    }

    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();
        
        $analysisResult = $context->getAnalysisResult();
        $article = $context->getArticleData();
        
        if ($analysisResult === null) {
            return AgentResult::failure(
                '분석 결과가 없습니다. AnalysisAgent를 먼저 실행하세요.',
                $this->getName()
            );
        }

        $this->log("Generating narration for: " . ($analysisResult->getNewsTitle() ?? 'Unknown'), 'info');

        try {
            $narration = $this->generateNarration($analysisResult, $article);
            
            $updatedResult = $analysisResult->withNarration($narration);
            $updatedResult = $updatedResult->withMetadata([
                'narration_agent' => $this->getName(),
                'narration_generated_at' => date('c'),
            ]);

            return AgentResult::success(
                $updatedResult->toArray(),
                ['agent' => $this->getName()]
            );

        } catch (\Exception $e) {
            $this->log("Narration error: " . $e->getMessage(), 'error');
            return AgentResult::failure(
                'Narration 생성 중 오류 발생: ' . $e->getMessage(),
                $this->getName()
            );
        }
    }

    /**
     * Narration 생성
     */
    private function generateNarration(AnalysisResult $analysis, ?ArticleData $article): string
    {
        $systemPrompt = $this->prompts['system'] ?? '당신은 "The Gist"의 내레이션 작가입니다.';
        $userPrompt = $this->buildNarrationPrompt($analysis, $article);
        
        $response = $this->callClaude($systemPrompt, $userPrompt);
        $data = $this->parseJsonResponse($response);
        
        $this->logClaudeResponse('narration', $response, $data);

        if (!empty($data['narration_paragraphs']) && is_array($data['narration_paragraphs'])) {
            $narration = implode("\n\n", array_filter(array_map('trim', $data['narration_paragraphs'])));
            $this->log("Narration generated: " . count($data['narration_paragraphs']) . " paragraphs", 'debug');
            return $narration;
        }

        if (!empty($data['narration'])) {
            $this->log("Fallback to narration string field", 'debug');
            return $data['narration'];
        }

        $this->log("Narration generation failed, using raw response", 'warning');
        return trim($response);
    }

    /**
     * Narration 프롬프트 생성
     */
    private function buildNarrationPrompt(AnalysisResult $analysis, ?ArticleData $article): string
    {
        $title = $analysis->getNewsTitle() ?? $analysis->getOriginalTitle() ?? '';
        
        $analysisJson = json_encode([
            'news_title' => $analysis->getNewsTitle(),
            'introduction_summary' => $analysis->getIntroductionSummary(),
            'section_analysis' => $analysis->getSectionAnalysis(),
            'key_points' => $analysis->getKeyPoints(),
            'geopolitical_implication' => $analysis->getGeopoliticalImplication(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $articleContent = '';
        if ($article !== null) {
            $articleContent = $this->truncateContent($article->getContent(), 25000);
        }

        return <<<PROMPT
[필수 출력 형식]
JSON으로만 응답하세요. 반드시 "narration_paragraphs" 배열을 사용하세요.

{
  "narration_paragraphs": [
    "첫 번째 문단",
    "두 번째 문단",
    "세 번째 문단",
    "..."
  ]
}

[Narration 작성 규칙]
1. 청자가 기사 원문을 읽지 않아도 전체 맥락을 이해할 수 있게 작성
2. 분석 결과(introduction_summary, section_analysis, key_points)의 흐름을 따라 자연스럽게 설명
3. 첫 문장은 바로 핵심으로 시작 (인사말 금지)
4. 기사에 없는 주장이나 과도한 확신 금지
5. key_points를 기계적으로 나열하지 말고 흐름 있는 설명문으로 작성

[문단 규칙]
- 각 문단은 2~4문장
- 한 문단에는 하나의 논점만
- 전체 합산 1000~1500자
- 의미 전환 시 반드시 새 문단으로

[문단 흐름 예시]
1. 도입: 핵심 사건/주장 소개
2. 배경: 왜 이 일이 일어났는지
3. 주요 내용: section_analysis 기반 상세 설명
4. 함의: 왜 중요한지, 앞으로의 전망

---

기사 제목: {$title}

분석 결과:
{$analysisJson}

기사 원문:
{$articleContent}

---

위 분석 결과와 원문을 참고하여 The Gist 스타일의 narration을 작성하세요.
반드시 narration_paragraphs 배열로 응답하세요.
PROMPT;
    }

    /**
     * Claude API 호출
     */
    private function callClaude(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        if (!$this->claude->isConfigured()) {
            $this->log("Claude not configured, falling back to GPT", 'warning');
            return $this->callGPT($userPrompt, array_merge($options, ['system_prompt' => $systemPrompt]));
        }
        
        return $this->claude->chat($systemPrompt, $userPrompt, array_merge([
            'max_tokens' => (int)($this->config['max_tokens'] ?? 4096),
            'temperature' => (float)($this->config['temperature'] ?? 0.5),
            'timeout' => (int)($this->config['timeout'] ?? 120),
        ], $options));
    }

    /**
     * 콘텐츠 길이 제한
     */
    private function truncateContent(string $content, int $maxLength = 25000): string
    {
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }
        return mb_substr($content, 0, $maxLength) . '...';
    }

    /**
     * JSON 응답 파싱
     */
    private function parseJsonResponse(string $response): array
    {
        if (preg_match('/```json\s*([\s\S]*?)```/u', $response, $codeBlock)) {
            $jsonStr = trim($codeBlock[1]);
        } elseif (preg_match('/\{[\s\S]*\}/u', $response, $matches)) {
            $jsonStr = $matches[0];
        } else {
            $jsonStr = '';
        }

        if ($jsonStr !== '') {
            $data = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Claude 응답 로깅
     */
    private function logClaudeResponse(string $stage, string $rawResponse, array $parsedData): void
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $logFile = $logDir . "/claude_narration_{$timestamp}.json";
        
        $logData = [
            'timestamp' => date('c'),
            'stage' => $stage,
            'model' => 'claude-sonnet-4-6',
            'raw_response_length' => strlen($rawResponse),
            'raw_response_preview' => mb_substr($rawResponse, 0, 1500),
            'has_narration_paragraphs' => isset($parsedData['narration_paragraphs']),
            'narration_paragraphs_count' => count($parsedData['narration_paragraphs'] ?? []),
        ];
        
        @file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->log("Claude narration logged to: {$logFile}", 'debug');
    }
}
