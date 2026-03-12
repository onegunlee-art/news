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
##############################################################
# The Gist 내래이션 작성 가이드
##############################################################

[절대 규칙]
1. 모든 문장은 공손한 높임말 (~입니다, ~합니다, ~됩니다)
2. 평어/반말 절대 금지 (~이다, ~한다, ~된다)
3. 부드럽고 따뜻하면서도 전문적인 어조
4. 인사말 없이 바로 핵심으로 시작
5. 기사에 없는 주장이나 과도한 확신 금지

[출력 형식]
JSON으로만 응답하세요.
{
  "narration_paragraphs": ["문단1", "문단2", "문단3", ...]
}

[참조 예시 - 이 형식과 어조를 따르세요]
<example>
2025년 10월 부산에서 미·중이 '1년짜리 유예'에 합의하면서, 겉으로는 긴장이 한풀 꺾인 것처럼 보였습니다.

하지만 그 '안도감'이 2026년을 더 위험하게 만들 수 있습니다.

일명 부산 합의는 일부 강경한 경제 조치를 1년간 멈춰 세우는 데는 성공했지만, 가장 폭발성이 큰 쟁점들은 건드리지 못했습니다. 미국이 특히 민감해하는 환적, 즉 제3국을 통한 우회수출에 관세를 어떻게 적용할지, 그리고 희토류나 반도체 같은 전략 품목의 수출통제를 어디까지 할지, 여기에 경제를 넘어선 각종 제재까지—핵심 갈등이 그대로 남아 있습니다. 휴전이 '해결'이 아니라 '유예'였다는 말이죠.

국제정치에서는 실제 국력의 크기 만큼이나, 각국이 상황을 어떻게 인식하고 해석하느냐가 행동을 바꿉니다. 2025년의 부분적 봉합이 중국 내부에 '우리가 밀어붙이면 상대가 물러선다'는 자신감을 키웠다면, 그 자신감이 주변국을 향한 강압적 행동으로 바뀔 수 있습니다. 2008년 금융위기 이후 중국이 과신에 빠져 2010년 전후 더 거친 태도를 보였고, 그 결과 주변국의 반발과 견제를 불러왔던 흐름을 기억해야 합니다.

지금도 그런 움직임, 자신감을 얻은 듯 보이는 중국의 모습이 관찰됩니다. 일본을 겨냥한 제재, 대만 주변에서의 군사훈련 강화, 남중국해에서의 존재감 확대, 우리나라 서해 EEZ 안에 구조물을 건설하는 움직임까지—중국이 주변 해역에서 '사실상 현상 변경'을 시도하는 것처럼 행동하고 있습니다.

중국의 그런 과감한 행보가 미국에 먹혀들까요? 현재 중국의 무기는 희토류 통제 카드입니다. 한편 미국은 달러 중심의 금융질서를 기반으로 중국 대형 국유은행을 겨냥한 광범위 제재라는, '금융 핵무기'에 가까운 옵션을 갖고 있습니다. 결국 서로가 서로의 급소를 갖고 있는 상태에서, '부산 휴전'이 갈등을 해결했다는 착시가 오히려 위험한 오판을 낳을 수 있습니다.

이런 미중간 충돌의 무대는 아시아에만 머물지 않습니다. 유럽에서는 우크라이나 전쟁을 둘러싼 대러 압박 과정에서 '대중 2차 제재' 같은 형태로 중국이 더 강하게 반발할 수 있고, 중동에서는 이란 문제, 중남미에서는 미국이 글로벌 사우스 내 중국 영향력 확대를 되받아치는 과정에서 마찰면이 넓어질 수 있습니다.

2026년의 미·중 관계는 정상회담 계기가 많아 자주 접촉하고 소통할 수 있는 시즌이기도 하지만, 동시에 휴전의 착시가 과신을 키워 충돌 가능성이 높아질 수도 있는 시즌이기도 합니다.
</example>

[예시에서 배울 점]
- 모든 문장이 높임말로 끝남 (~입니다, ~합니다, ~있습니다)
- 각 문단은 하나의 논점에 집중
- 문단 간 자연스러운 흐름 (도입→배경→전개→함의)
- 전문적이면서도 부드러운 어조
- 구체적 사례와 비유 활용 ('금융 핵무기', '목줄')

---

기사 제목: {$title}

분석 결과:
{$analysisJson}

기사 원문:
{$articleContent}

---

위 분석 결과와 원문을 참고하여, 예시와 같은 형식과 어조로 narration을 작성하세요.
예시의 '주제'가 아닌 '형식과 어조'만 따르세요.
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
