<?php
/**
 * Learning Agent
 * 
 * 사용자 글 패턴 학습 Agent
 * - 사용자가 작성한 글의 스타일 분석
 * - 어조, 구조, 표현 패턴 학습
 * - AnalysisAgent의 출력 스타일 조정
 * - 불명확한 분석 방향에 대한 질의
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
use Agents\Services\OpenAIService;

class LearningAgent extends BaseAgent
{
    private array $learnedPatterns = [];
    private array $sampleTexts = [];
    private string $storagePath;
    private bool $patternsLoaded = false;

    public function __construct(OpenAIService $openai, array $config = [])
    {
        parent::__construct($openai, $config);
        $this->storagePath = $config['storage_path'] 
            ?? dirname(__DIR__, 3) . '/storage/learning';
    }

    /**
     * Agent 이름
     */
    public function getName(): string
    {
        return 'LearningAgent';
    }

    /**
     * 기본 프롬프트
     */
    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 글쓰기 스타일 분석 전문가입니다. 사용자의 글을 분석하여 패턴을 학습합니다.',
            'tasks' => [
                'analyze_pattern' => [
                    'prompt' => '글의 스타일 패턴을 분석하세요.'
                ],
                'apply_style' => [
                    'prompt' => '학습된 스타일을 적용하여 텍스트를 변환하세요.'
                ],
                'clarify' => [
                    'prompt' => '분석 방향에 대한 명확화 질문을 생성하세요.'
                ]
            ]
        ];
    }

    /**
     * 입력 유효성 검증
     */
    public function validate(mixed $input): bool
    {
        if ($input instanceof AgentContext) {
            return $input->getAnalysisResult() !== null;
        }

        if (is_string($input)) {
            return !empty(trim($input)) && mb_strlen($input) >= 50;
        }

        if (is_array($input)) {
            return !empty($input);
        }

        return false;
    }

    /**
     * 초기화 - 저장된 패턴 로드
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadStoredPatterns();
    }

    /**
     * 저장된 패턴 로드
     */
    private function loadStoredPatterns(): void
    {
        $patternFile = $this->storagePath . '/patterns.json';
        
        if (file_exists($patternFile)) {
            $content = file_get_contents($patternFile);
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->learnedPatterns = $data['patterns'] ?? [];
                $this->sampleTexts = $data['samples'] ?? [];
                $this->patternsLoaded = true;
                $this->log("Loaded " . count($this->learnedPatterns) . " patterns", 'info');
            }
        }
    }

    /**
     * 학습 데이터 추가 (사용자의 글)
     */
    public function addSampleText(string $text, array $metadata = []): void
    {
        $this->sampleTexts[] = [
            'text' => $text,
            'metadata' => $metadata,
            'added_at' => date('c')
        ];

        $this->log("Added sample text (" . mb_strlen($text) . " chars)", 'info');
    }

    /**
     * 패턴 학습 실행
     */
    public function learn(): array
    {
        if (empty($this->sampleTexts)) {
            return ['error' => '학습할 샘플 텍스트가 없습니다.'];
        }

        $this->log("Learning from " . count($this->sampleTexts) . " samples", 'info');

        // 모든 샘플 텍스트 합치기
        $combinedText = '';
        foreach ($this->sampleTexts as $sample) {
            $combinedText .= $sample['text'] . "\n\n---\n\n";
        }

        // AI를 통한 패턴 분석
        $patterns = $this->analyzePatterns($combinedText);
        
        // 패턴 저장
        $this->learnedPatterns = $patterns;
        $this->savePatterns();

        return $patterns;
    }

    /**
     * 패턴 분석
     */
    private function analyzePatterns(string $text): array
    {
        $prompt = <<<PROMPT
다음 글들을 분석하여 작성자의 글쓰기 패턴을 추출하세요.

글:
{$text}

다음 항목들을 분석하여 JSON으로 응답하세요:

{
  "style": {
    "formality": "formal/informal/mixed",
    "tone": "objective/subjective/analytical/conversational",
    "detail_level": "concise/moderate/detailed"
  },
  "structure": {
    "intro_style": "서론 작성 스타일 설명",
    "body_style": "본문 전개 스타일 설명",
    "conclusion_style": "결론 작성 스타일 설명",
    "paragraph_length": "short/medium/long"
  },
  "common_patterns": [
    "자주 사용하는 표현 패턴 1",
    "자주 사용하는 표현 패턴 2",
    "자주 사용하는 표현 패턴 3"
  ],
  "emphasis_methods": [
    "강조할 때 사용하는 방법 1",
    "강조할 때 사용하는 방법 2"
  ],
  "transition_phrases": [
    "문단 전환 시 사용하는 표현들"
  ],
  "unique_expressions": [
    "작성자만의 독특한 표현"
  ],
  "content_focus": [
    "주로 강조하는 내용 유형"
  ],
  "target_audience": "독자층 추정",
  "vocabulary_level": "beginner/intermediate/advanced"
}
PROMPT;

        $response = $this->callGPT($prompt);
        $result = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // 파싱 실패 시 기본 패턴
        return [
            'style' => [
                'formality' => 'formal',
                'tone' => 'analytical',
                'detail_level' => 'detailed'
            ],
            'common_patterns' => [],
            'raw_analysis' => $response
        ];
    }

    /**
     * 패턴 저장
     */
    private function savePatterns(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $data = [
            'patterns' => $this->learnedPatterns,
            'samples' => $this->sampleTexts,
            'updated_at' => date('c'),
            'sample_count' => count($this->sampleTexts)
        ];

        file_put_contents(
            $this->storagePath . '/patterns.json',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $this->log("Patterns saved", 'info');
    }

    /**
     * 메인 처리 로직 - 분석 결과에 스타일 적용
     */
    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();
        
        $analysisResult = $context->getAnalysisResult();
        
        if ($analysisResult === null) {
            return AgentResult::failure(
                '스타일을 적용할 분석 결과가 없습니다.',
                $this->getName()
            );
        }

        // 학습된 패턴이 없으면 원본 유지
        if (empty($this->learnedPatterns)) {
            $this->log("No learned patterns, returning original", 'info');
            
            return AgentResult::success(
                [
                    'styled' => false,
                    'reason' => '학습된 패턴이 없습니다. 먼저 학습을 진행하세요.',
                    'original' => $analysisResult->toArray()
                ],
                ['agent' => $this->getName()]
            );
        }

        try {
            // 분석 방향이 불명확한지 확인
            $clarificationNeeded = $this->checkClarificationNeeded($analysisResult);
            
            if ($clarificationNeeded['needed']) {
                return AgentResult::partial(
                    [
                        'needs_clarification' => true,
                        'clarification_question' => $clarificationNeeded['question'],
                        'reason' => $clarificationNeeded['reason']
                    ],
                    ['agent' => $this->getName()]
                );
            }

            // 스타일 적용
            $styledResult = $this->applyStyle($analysisResult);

            // 컨텍스트 업데이트
            $context = $context
                ->withMetadata('styled_output', $styledResult)
                ->markProcessedBy($this->getName());

            return AgentResult::success(
                [
                    'styled' => true,
                    'patterns_applied' => array_keys($this->learnedPatterns),
                    'output' => $styledResult,
                    'original' => $analysisResult->toArray()
                ],
                ['agent' => $this->getName()]
            );

        } catch (\Exception $e) {
            $this->log("Style application error: " . $e->getMessage(), 'error');
            return AgentResult::failure(
                '스타일 적용 중 오류: ' . $e->getMessage(),
                $this->getName()
            );
        }
    }

    /**
     * 명확화 필요 여부 확인
     */
    private function checkClarificationNeeded($analysisResult): array
    {
        $content = $analysisResult->getTranslationSummary();
        
        // 간단한 휴리스틱: 내용이 너무 짧거나 모호한 경우
        if (mb_strlen($content) < 50) {
            return [
                'needed' => true,
                'reason' => '분석 내용이 너무 짧습니다.',
                'question' => '어떤 관점에서 더 자세히 분석할까요? (예: 경제적 영향, 정치적 함의, 기술적 측면)'
            ];
        }

        // AI 기반 체크 (옵션)
        $prompt = <<<PROMPT
다음 분석 내용의 방향이 명확한지 판단하세요.

분석 내용: {$content}

JSON으로 응답:
{
  "is_clear": true/false,
  "reason": "판단 이유",
  "suggested_question": "불명확한 경우 물어볼 질문"
}
PROMPT;

        $response = $this->callGPT($prompt);
        $result = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($result['is_clear'])) {
            return [
                'needed' => !$result['is_clear'],
                'reason' => $result['reason'] ?? '',
                'question' => $result['suggested_question'] ?? '분석 방향을 더 구체적으로 알려주세요.'
            ];
        }

        return ['needed' => false, 'reason' => '', 'question' => ''];
    }

    /**
     * 스타일 적용
     */
    private function applyStyle($analysisResult): array
    {
        $summary = $analysisResult->getTranslationSummary();
        $keyPoints = $analysisResult->getKeyPoints();
        $critical = $analysisResult->getCriticalAnalysis();

        $patternsJson = json_encode($this->learnedPatterns, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
다음 분석 결과를 주어진 글쓰기 스타일 패턴에 맞게 재작성하세요.

[학습된 스타일 패턴]
{$patternsJson}

[원본 분석 결과]
요약: {$summary}

주요 포인트:
- {$this->arrayToString($keyPoints)}

중요성 분석:
- 왜 중요한가: {$critical['why_important']}
- 미래 전망: {$critical['future_prediction']}

[작업 지시]
1. 학습된 스타일 패턴의 어조, 구조, 표현 방식을 적용
2. 특히 common_patterns와 unique_expressions 활용
3. 내용의 정확성은 유지하면서 스타일만 변환

JSON 형식으로 응답:
{
  "translation_summary": "스타일이 적용된 요약",
  "key_points": ["스타일이 적용된 포인트1", "포인트2", "포인트3"],
  "critical_analysis": {
    "why_important": "스타일이 적용된 중요성 설명",
    "future_prediction": "스타일이 적용된 전망"
  }
}
PROMPT;

        $response = $this->callGPT($prompt);
        $result = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // 파싱 실패 시 원본 반환
        return $analysisResult->toArray();
    }

    /**
     * 배열을 문자열로 변환
     */
    private function arrayToString(array $items): string
    {
        return implode("\n- ", $items);
    }

    /**
     * 학습된 패턴 반환
     */
    public function getLearnedPatterns(): array
    {
        return $this->learnedPatterns;
    }

    /**
     * 패턴 초기화
     */
    public function resetPatterns(): void
    {
        $this->learnedPatterns = [];
        $this->sampleTexts = [];
        
        $patternFile = $this->storagePath . '/patterns.json';
        if (file_exists($patternFile)) {
            unlink($patternFile);
        }

        $this->log("Patterns reset", 'info');
    }

    /**
     * 패턴 학습 여부
     */
    public function hasLearnedPatterns(): bool
    {
        return !empty($this->learnedPatterns);
    }
}
