<?php
/**
 * Narration Agent
 * 
 * 분석 결과 + 원문을 기반으로 Narration 생성 (GPT-5.4 + JSON mode)
 * - 구조화된 분석 결과를 자연스러운 설명문으로 변환
 * - 문단 구분이 명확한 narration_paragraphs[] 배열 출력
 * 
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 2.0.0 - GPT-5.4 전환
 */

declare(strict_types=1);

namespace Agents\Agents;

use Agents\Core\BaseAgent;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Models\AnalysisResult;
use Agents\Services\OpenAIService;

class NarrationAgent extends BaseAgent
{
    public function __construct(OpenAIService $openai, array $config = [])
    {
        parent::__construct($openai, $config);
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
        $userPrompt = $this->buildNarrationPrompt($analysis, $article);
        
        $response = $this->callGPT($userPrompt, [
            'system_prompt' => $this->prompts['system'] ?? '당신은 "The Gist"의 내레이션 작가입니다.',
            'model' => $this->config['model'] ?? 'gpt-5.4',
            'max_tokens' => (int)($this->config['max_tokens'] ?? 4096),
            'temperature' => (float)($this->config['temperature'] ?? 0.5),
            'timeout' => (int)($this->config['timeout'] ?? 180),
            'json_mode' => true,
        ]);
        $data = $this->parseJsonResponse($response);
        
        $this->logResponse('narration', $response, $data);

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

[절대 규칙 - 반드시 준수]
1. 내래이션은 반드시 1,300자 이상 작성하세요.
2. 모든 문장은 공손한 높임말 (~입니다, ~합니다, ~됩니다)
3. 평어/반말 절대 금지 (~이다, ~한다, ~된다)
4. 부드럽고 따뜻하면서도 전문적인 어조
5. 인사말 없이 바로 핵심으로 시작
6. 기사에 없는 주장이나 과도한 확신 금지

[출력 형식]
JSON으로만 응답하세요.
{
  "narration_paragraphs": ["문단1", "문단2", "문단3", ...]
}

[참조 예시 1 - 법집행기관의 군사화]
<example>
2025년 8월 워싱턴DC에서 낯선 장면이 펼쳐졌습니다.

군복을 입은 주방위군이 도심을 순찰하기 시작한 건데요. 겉으로는 치안 강화가 사유였습니다. 트럼프 대통령이 행정명령으로 범죄 비상사태를 선포하면서 통제를 회복하려면 특별한 조치가 필요하다고 주장했죠.

그런데 아이러니한 것은 워싱턴DC 내 폭력범죄가 그해 1월 이미 30여 년 만의 최저 수준으로 내려가고 있었다는 점입니다.

이러한 움직임은 워싱턴DC 뿐 만이 아니라 시카고, 로스앤젤레스, 멤피스, 뉴올리언스, 포틀랜드 같은 대도시에도 벌어졌고, 물론 그때마다 명분은 범죄·소요·공공질서 위협이었습니다.

이번 사건은 단순한 엄격한 범죄 대응 시그널 이상의 변화로 보입니다.

미국이 오랫동안 지켜온 원칙, 즉 군인에 대비되는 개념으로서의 민간인 자격에서의 치안 담당을 하는 경찰과 국방을 담당하는 군인을 분리해 온 경계가 흔들리기 시작했다는 신호라는 거죠. 그리고 이게 반복되면 예외적 투입이 어느 순간 상시적 통치 방식으로 굳어질 수 있습니다.

사실 이런 움직임은 라틴아메리카에서 그간 쉽게 찾아볼 수 있었죠.

라틴아메리카에서는 정치 지도자들이 군을 거리로 내보내 강력한 질서를 보여주는 방식이 익숙합니다. 공포가 커질수록 사람들은 눈에 보이는 강경책을 원하고, 군은 규율과 힘의 상징이니까요. 하지만 그 대가는 만만치 않았습니다.

처음엔 일시적이라던 조치가 차츰 상시화되고, 행정부 권력은 커지며, 의회와 법원의 견제는 약해지고, 시민적 자유는 조금씩 닳아 없어졌다는 겁니다.

라틴 아메리카 상황들을 보면 이런 움직임은 범죄율을 낮추는데 실효성 마저 없는 것으로 보입니다. 다만 실효성이 만약에 있다치더라도 이 같은 현상이 어떻게 헌정 질서를 변질시키느냐에 주목해야 합니다.

그 동안 미국은 관련 법령들이라던가 여러 장치를 통해 연방군의 국내 법집행을 제한해 왔고, 건국 초기부터 군사력의 국내 동원이 공화정과 시민의 자유를 해칠 수 있다는 경계심이 강했습니다. 이 같은 군과 경찰의 분리 관념이 어떤 면에서는 미국 민주주의의 핵심 기둥이었기도 했죠.

비상선포가 반복되면 의회는 범죄에 약한 입장을 취한다는 비난을 두려워해 견제를 주저하게되기 마련이고, 법원도 적법절차가 약해진 환경에서는 행정부에 더 관대해질 수 있습니다.

한 번 흐려진 경계는 되돌리기 어려워져 - 차후 전혀 다른 성향의 정부가 들어선다할지라도 선례를 제공하고, 관성을 갖고 남겨질 수 있습니다.

공동체 내 구성원들 각자가, 군인들이 치안을 맡는 다는 것이 과연 무엇을 의미하는지, 무엇을 희생하는지에 대해 분명히 인식하는 것이 중요한 시점입니다.
</example>

[참조 예시 2 - 동맹 감사]
<example>
트럼프 대통령이 동맹국과의 관계를 망가뜨리고 있는 건 사실입니다.

툭하면 압박하고, 영토를 내놓으라하고, 미국의 51번째 주(州)로 편입하겠다고 얘기하고, 관세를 올렸다가 내렸다가말이죠. 좋은 건 아닙니다. 일단 미국의 동맹관계가 원만치 않다는 건 알겠는데 -

그런데, 이후 차기 미국 대통령으로 누군가가 당선된다면 그는 어떤 식으로 동맹 관계를 복원해야 할까요? 이를 냉정하게, 미국 국익의 입장에서 생각해봅시다.

확실한 것은 냉전 시기의 동맹 체제를 그대로 되살리는 것은 잘못된 접근이라는 것입니다. 그 대신 미국은 모든 동맹을 제대로 다시 평가해야 합니다.

왜냐하면, 당시의 동맹은 냉전 체제를 바탕으로 형성된 것이고, 오늘날 국제 질서는 첫째, 중국은 미국의 경제적·군사적 패권에 도전하는 경쟁자가 되었고, 둘째, 러시아는 다시 군사적 위협이 되었으며, 셋째, 북한은 미국 본토를 타격할 수 있는 핵무기를 보유하게 되었다는 점에서 그때와는 상황이 크게 다르거든요.

따라서 과거의 관성이나 향수에 기반한 동맹 유지가 아니라, 현재 변화된 질서 내에서 전략 환경에 맞는 동맹 재조정이 필요합니다.

자, 그러자면 동맹을 평가하는 기준이 있어야 하는데, 다음 두 개가 핵심 기준입니다.

첫째, 중국과의 경쟁에 도움이 되는가 하는 것입니다. 특히 미중 갈등 관계에 있어 동맹국이 반도체·핵심 광물 공급망 안정화, 기술 표준 경쟁, 외교적 영향력 확대, 방위비 및 군사적 부담 분담에 도움이 되는가가 중요한 평가 기준입니다.

둘째, 미국을 불필요한 전쟁에 끌어들일 위험이 낮은가하는 것입니다. 동맹은 자동적으로 전쟁 의무를 수반하게 됩니다. 복수의 전선(戰線)에서 전쟁을 치를 가능성이 발생하는 겁니다. 일종의 동맹이 만들어내는 불가피한 비용입니다. 이게 낮아야 동맹을 유지할 가치가 높아지는 겁니다.

이런 시각에서 필리핀과의 동맹 관계는 변경이 필요합니다. 1951년 체결된 상호방위조약은 당시에는 합리적이었지만, 현재는 중국의 해양 군사력이 크게 강화되었습니다.

한국과의 동맹 관계 역시 마찬가지입니다. 한국은 경제·기술 측면에서 매우 중요한 동맹임은 인정하지만, 군사적 위험 역시 엄청나게 증가했습니다. 냉전 초기와 달리, 북한은 미국 본토를 핵으로 공격 가능하기에 - 한반도 위기 시 미국 도시가 직접 위협받을 수 있기 때문입니다.

반면, 일본, 호주, 그리고 NATO를 통한 유럽과의 동맹은 유지하거나 강화해야할 것입니다. 일본의 첨단 기술력, 대규모 경제력, 외교적 영향력 및 중국 견제에 실질적 기여를 한다는 측면에서 일본은 인도태평양에서 미국 전략의 중심축으로 남아야 합니다.

향후 미국은 모든 동맹을 총괄 평가하는 전담 기구 설립하고, 연례 동맹 비용·위험 평가해서 동맹을 자동 유지가 아닌 전략적 선택의 대상으로 만들어야 할 것입니다.
</example>

[예시에서 배울 점]
- 1,300자 이상의 충분한 길이
- 모든 문장이 높임말로 끝남 (~입니다, ~합니다, ~있습니다)
- 각 문단은 하나의 논점에 집중
- 문단 간 자연스러운 흐름 (도입→배경→전개→함의)
- 전문적이면서도 부드러운 어조
- 구체적 사례와 비유 활용

---

기사 제목: {$title}

분석 결과:
{$analysisJson}

기사 원문:
{$articleContent}

---

위 분석 결과와 원문을 참고하여, 예시와 같은 형식과 어조로 1,300자 이상의 narration을 작성하세요.
예시의 '주제'가 아닌 '형식과 어조'만 따르세요.
PROMPT;
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
     * GPT 응답 로깅
     */
    private function logResponse(string $stage, string $rawResponse, array $parsedData): void
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $logFile = $logDir . "/gpt_narration_{$timestamp}.json";
        
        $logData = [
            'timestamp' => date('c'),
            'stage' => $stage,
            'model' => $this->config['model'] ?? 'gpt-5.4',
            'raw_response_length' => strlen($rawResponse),
            'raw_response_preview' => mb_substr($rawResponse, 0, 1500),
            'has_narration_paragraphs' => isset($parsedData['narration_paragraphs']),
            'narration_paragraphs_count' => count($parsedData['narration_paragraphs'] ?? []),
        ];
        
        @file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->log("GPT narration logged to: {$logFile}", 'debug');
    }
}
