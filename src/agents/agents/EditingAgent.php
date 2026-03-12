<?php
/**
 * Editing Agent
 * 
 * Narration 문체/톤 교정 + 스타일 가이드 적용 (Claude Sonnet 4.6)
 * - The Gist 톤앤매너 통일
 * - 문장 다듬기
 * - 불필요한 표현 제거
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
use Agents\Models\AnalysisResult;
use Agents\Services\OpenAIService;
use Agents\Services\ClaudeService;

class EditingAgent extends BaseAgent
{
    private ?ClaudeService $claude = null;

    public function __construct(OpenAIService $openai, array $config = [], ?ClaudeService $claude = null)
    {
        parent::__construct($openai, $config);
        $this->claude = $claude ?? new ClaudeService();
    }

    public function getName(): string
    {
        return 'EditingAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 "The Gist"의 수석 에디터입니다. Narration을 최종 검토하고 문체와 톤을 교정합니다. 원래 의미를 훼손하지 않으면서 가독성과 전달력을 높입니다.'
        ];
    }

    public function validate(mixed $input): bool
    {
        if ($input instanceof AgentContext) {
            $analysisResult = $input->getAnalysisResult();
            return $analysisResult !== null && !empty($analysisResult->getNarration());
        }
        return false;
    }

    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();
        
        $analysisResult = $context->getAnalysisResult();
        
        if ($analysisResult === null) {
            return AgentResult::failure(
                '분석 결과가 없습니다.',
                $this->getName()
            );
        }

        $originalNarration = $analysisResult->getNarration();
        if (empty($originalNarration)) {
            return AgentResult::failure(
                'Narration이 없습니다. NarrationAgent를 먼저 실행하세요.',
                $this->getName()
            );
        }

        $this->log("Editing narration for: " . ($analysisResult->getNewsTitle() ?? 'Unknown'), 'info');

        try {
            $editedNarration = $this->editNarration($originalNarration, $analysisResult);
            
            $updatedResult = $analysisResult->withNarration($editedNarration);
            $updatedResult = $updatedResult->withMetadata([
                'editing_agent' => $this->getName(),
                'editing_completed_at' => date('c'),
                'original_narration_length' => mb_strlen($originalNarration),
                'edited_narration_length' => mb_strlen($editedNarration),
            ]);

            return AgentResult::success(
                $updatedResult->toArray(),
                ['agent' => $this->getName()]
            );

        } catch (\Exception $e) {
            $this->log("Editing error: " . $e->getMessage(), 'error');
            return AgentResult::failure(
                'Editing 중 오류 발생: ' . $e->getMessage(),
                $this->getName()
            );
        }
    }

    /**
     * Narration 편집
     */
    private function editNarration(string $narration, AnalysisResult $analysis): string
    {
        $systemPrompt = $this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.';
        $userPrompt = $this->buildEditingPrompt($narration, $analysis);
        
        $response = $this->callClaude($systemPrompt, $userPrompt);
        $data = $this->parseJsonResponse($response);
        
        $this->logClaudeResponse('editing', $response, $data);

        if (!empty($data['edited_paragraphs']) && is_array($data['edited_paragraphs'])) {
            $edited = implode("\n\n", array_filter(array_map('trim', $data['edited_paragraphs'])));
            $this->log("Editing completed: " . count($data['edited_paragraphs']) . " paragraphs", 'debug');
            return $edited;
        }

        if (!empty($data['edited_narration'])) {
            return $data['edited_narration'];
        }

        $this->log("Editing parsing failed, returning original", 'warning');
        return $narration;
    }

    /**
     * 편집 프롬프트 생성 (스타일 가이드 내장)
     */
    private function buildEditingPrompt(string $narration, AnalysisResult $analysis): string
    {
        $title = $analysis->getNewsTitle() ?? '';

        return <<<PROMPT
##############################################################
# 절대 규칙 - 어조 (이 규칙은 무조건 지켜야 합니다)
##############################################################
- 모든 문장은 반드시 공손한 높임말로 작성/유지하세요.
- 문장 끝: ~입니다, ~합니다, ~됩니다, ~있습니다, ~겠습니다
- 금지: ~이다, ~한다, ~된다, ~있다 (평어/반말 절대 금지)
- 편집 시 높임말이 아닌 문장이 있으면 높임말로 수정하세요.
- 굉장히 부드럽고 친근하면서도 격식 있는 어조를 유지하세요.
##############################################################

[The Gist 스타일 가이드]

1. 톤앤매너
   - 전문적이지만 딱딱하지 않게
   - 친근하되 가볍지 않게
   - 확신 있게 서술하되 과장하지 않게
   - 독자를 존중하는 어조
   - 굉장히 부드럽고 따뜻한 말투

2. 문장 규칙
   - 한 문장은 40자 이내 권장
   - 주어-목적어-서술어 순서 명확히
   - 능동태 우선 (피동태 최소화)
   - 불필요한 접속사 제거 ("그리고", "또한", "그러나" 남용 금지)

3. 금지 표현
   - "~입니다만", "~라고 할 수 있습니다" (불필요한 완곡 표현)
   - "사실", "실제로", "기본적으로" (의미 없는 부사)
   - "굉장히", "매우", "정말" (과장 부사)
   - "것으로 보입니다", "것으로 알려졌습니다" (불확실한 표현)

4. 권장 표현
   - 직접적 서술: "미국이 발표했습니다" (O) vs "미국에 의해 발표되었습니다" (X)
   - 구체적 수치: "약 30%" (O) vs "상당수" (X)
   - 명확한 인과: "A 때문에 B가 발생했습니다"

5. 문단 규칙
   - 문단 간 빈 줄(\n\n)로 구분
   - 한 문단당 2~4문장
   - 각 문단은 하나의 논점만

[편집 원칙]
- 원래 의미를 절대 훼손하지 않음
- 최소한의 개입으로 가독성 향상
- 정보 추가/삭제 금지
- 문단 구조 유지
- 높임말이 아닌 문장 발견 시 높임말로 수정

[출력 형식]
JSON으로만 응답하세요.

{
  "edited_paragraphs": [
    "교정된 첫 번째 문단 (높임말 필수)",
    "교정된 두 번째 문단 (높임말 필수)",
    "..."
  ],
  "changes_made": [
    "변경 사항 1 (예: '~입니다만' → '~입니다')",
    "변경 사항 2"
  ]
}

---

기사 제목: {$title}

원본 Narration:
{$narration}

---

위 스타일 가이드에 따라 Narration을 교정하세요.
원래 의미를 유지하면서 문체와 가독성만 개선합니다.
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
            'temperature' => (float)($this->config['temperature'] ?? 0.3),
            'timeout' => (int)($this->config['timeout'] ?? 90),
        ], $options));
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
        $logFile = $logDir . "/claude_editing_{$timestamp}.json";
        
        $logData = [
            'timestamp' => date('c'),
            'stage' => $stage,
            'model' => 'claude-sonnet-4-6',
            'raw_response_length' => strlen($rawResponse),
            'has_edited_paragraphs' => isset($parsedData['edited_paragraphs']),
            'edited_paragraphs_count' => count($parsedData['edited_paragraphs'] ?? []),
            'changes_made' => $parsedData['changes_made'] ?? [],
        ];
        
        @file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->log("Claude editing logged to: {$logFile}", 'debug');
    }
}
