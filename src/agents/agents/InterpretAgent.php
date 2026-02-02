<?php
/**
 * Interpret Agent
 * 
 * RAG 기반 쿼리 해석 및 필터링 Agent
 * - 사용자 쿼리 유효성 판단
 * - 단계별 퀄리파잉 프로세스
 * - 도움이 되는 쿼리만 다음 단계로 전달
 * - @ingredient 기반 패턴 매칭
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

class InterpretAgent extends BaseAgent
{
    private array $knowledgeBase = [];
    private array $embeddings = [];
    private float $relevanceThreshold = 0.7;
    private int $topK = 5;

    public function __construct(OpenAIService $openai, array $config = [])
    {
        parent::__construct($openai, $config);
        $this->relevanceThreshold = $config['relevance_threshold'] ?? 0.7;
        $this->topK = $config['top_k'] ?? 5;
    }

    /**
     * Agent 이름
     */
    public function getName(): string
    {
        return 'InterpretAgent';
    }

    /**
     * 기본 프롬프트
     */
    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 쿼리 해석 및 필터링 전문가입니다.',
            'tasks' => [
                'validate_query' => [
                    'prompt' => '쿼리의 유효성을 판단하세요.'
                ],
                'match_pattern' => [
                    'prompt' => '쿼리와 매칭되는 패턴을 찾으세요.'
                ],
                'clarify' => [
                    'prompt' => '불명확한 쿼리에 대한 명확화 질문을 생성하세요.'
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
            return !empty($input->getQuery()) || $input->getArticleData() !== null;
        }

        if (is_string($input)) {
            return !empty(trim($input));
        }

        return false;
    }

    /**
     * 지식 베이스 로드 (@ingredient 데이터)
     */
    public function loadKnowledgeBase(array $documents): void
    {
        $this->knowledgeBase = $documents;
        
        // 임베딩 생성
        foreach ($documents as $id => $doc) {
            $text = is_array($doc) ? ($doc['content'] ?? json_encode($doc)) : (string)$doc;
            $this->embeddings[$id] = $this->openai->createEmbedding($text);
        }

        $this->log("Loaded " . count($documents) . " documents to knowledge base", 'info');
    }

    /**
     * 메인 처리 로직
     */
    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();
        
        // 분석 대상: 쿼리 또는 기사 분석 결과
        $analysisResult = $context->getAnalysisResult();
        $query = $context->getQuery();

        if ($analysisResult === null && empty($query)) {
            return AgentResult::failure(
                '해석할 쿼리 또는 분석 결과가 없습니다.',
                $this->getName()
            );
        }

        $targetText = $query ?: $analysisResult->getTranslationSummary();
        
        $this->log("Interpreting: " . substr($targetText, 0, 100) . "...", 'info');

        try {
            // Step 1: 쿼리 유효성 판단
            $validationResult = $this->validateQuery($targetText);
            
            if (!$validationResult['is_valid']) {
                // 유효하지 않으면 명확화 질문 생성
                $clarification = $this->generateClarification($targetText, $validationResult['reason']);
                
                return AgentResult::partial(
                    [
                        'needs_clarification' => true,
                        'clarification_question' => $clarification,
                        'reason' => $validationResult['reason'],
                        'original_query' => $targetText
                    ],
                    ['agent' => $this->getName()]
                );
            }

            // Step 2: RAG - 관련 패턴 매칭
            $matchedPatterns = $this->findRelevantPatterns($targetText);

            // Step 3: 최종 해석 결과 생성
            $interpretation = $this->generateInterpretation($targetText, $matchedPatterns);

            // 컨텍스트 업데이트
            $context = $context
                ->withMetadata('interpretation', $interpretation)
                ->markProcessedBy($this->getName());

            return AgentResult::success(
                [
                    'interpretation' => $interpretation,
                    'matched_patterns' => $matchedPatterns,
                    'query_valid' => true,
                    'confidence' => $interpretation['confidence'] ?? 0.8,
                    'pass_to_next' => true
                ],
                ['agent' => $this->getName()]
            );

        } catch (\Exception $e) {
            $this->log("Interpretation error: " . $e->getMessage(), 'error');
            return AgentResult::failure(
                '해석 중 오류 발생: ' . $e->getMessage(),
                $this->getName()
            );
        }
    }

    /**
     * 쿼리 유효성 판단
     */
    private function validateQuery(string $query): array
    {
        // Step 1: 기본 검증 (길이, 형식)
        if (mb_strlen($query) < 5) {
            return [
                'is_valid' => false,
                'reason' => '쿼리가 너무 짧습니다. 더 구체적인 질문을 해주세요.'
            ];
        }

        // Step 2: AI 기반 검증
        $prompt = <<<PROMPT
다음 쿼리가 뉴스 분석에 유효하고 도움이 되는지 판단하세요.

쿼리: {$query}

유효한 쿼리 기준:
1. 뉴스, 시사, 국제관계, 경제, 기술 관련 주제인가?
2. 구체적이고 명확한가?
3. 분석/해석이 가능한 내용인가?
4. 정치적 편향이나 유해한 내용이 없는가?

JSON 형식으로 응답:
{
  "is_valid": true/false,
  "reason": "판단 이유",
  "topic_category": "외교/경제/기술 중 하나",
  "confidence": 0.0~1.0
}
PROMPT;

        $response = $this->callGPT($prompt);
        $result = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($result['is_valid'])) {
            return $result;
        }

        // AI 판단 실패 시 기본 통과
        return [
            'is_valid' => true,
            'reason' => '기본 검증 통과',
            'confidence' => 0.7
        ];
    }

    /**
     * 관련 패턴 찾기 (RAG)
     */
    private function findRelevantPatterns(string $query): array
    {
        if (empty($this->knowledgeBase)) {
            // 지식 베이스가 없으면 빈 결과
            return [];
        }

        // 쿼리 임베딩 생성
        $queryEmbedding = $this->openai->createEmbedding($query);

        // 코사인 유사도 계산
        $similarities = [];
        foreach ($this->embeddings as $id => $docEmbedding) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $docEmbedding);
            if ($similarity >= $this->relevanceThreshold) {
                $similarities[$id] = $similarity;
            }
        }

        // 상위 K개 선택
        arsort($similarities);
        $topPatterns = array_slice($similarities, 0, $this->topK, true);

        // 결과 구성
        $result = [];
        foreach ($topPatterns as $id => $similarity) {
            $result[] = [
                'id' => $id,
                'content' => $this->knowledgeBase[$id],
                'similarity' => round($similarity, 4)
            ];
        }

        return $result;
    }

    /**
     * 코사인 유사도 계산
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        $length = min(count($vec1), count($vec2));
        
        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * 명확화 질문 생성
     */
    private function generateClarification(string $query, string $reason): string
    {
        $prompt = <<<PROMPT
다음 쿼리가 불명확하여 명확화가 필요합니다.

쿼리: {$query}
문제: {$reason}

사용자에게 물어볼 명확화 질문을 1-2개 생성하세요.
자연스럽고 친절한 톤으로 작성하세요.
PROMPT;

        return $this->callGPT($prompt);
    }

    /**
     * 최종 해석 결과 생성
     */
    private function generateInterpretation(string $query, array $matchedPatterns): array
    {
        $patternsContext = '';
        if (!empty($matchedPatterns)) {
            $patternsContext = "\n\n참조할 수 있는 관련 패턴:\n";
            foreach ($matchedPatterns as $pattern) {
                $content = is_array($pattern['content']) 
                    ? json_encode($pattern['content'], JSON_UNESCAPED_UNICODE)
                    : $pattern['content'];
                $patternsContext .= "- ({$pattern['similarity']}) {$content}\n";
            }
        }

        $prompt = <<<PROMPT
다음 쿼리/기사 내용을 해석하고 분석 방향을 제시하세요.

쿼리/내용: {$query}
{$patternsContext}

JSON 형식으로 응답:
{
  "main_topic": "주요 주제",
  "sub_topics": ["세부 주제1", "세부 주제2"],
  "analysis_direction": "분석 방향 제안",
  "key_questions": ["탐구할 핵심 질문1", "핵심 질문2"],
  "confidence": 0.0~1.0
}
PROMPT;

        $response = $this->callGPT($prompt);
        $result = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // 파싱 실패 시 기본 구조
        return [
            'main_topic' => '일반 분석',
            'sub_topics' => [],
            'analysis_direction' => $response,
            'key_questions' => [],
            'confidence' => 0.7
        ];
    }

    /**
     * 쿼리가 도움이 되는지 판단
     */
    public function isHelpfulQuery(string $query): bool
    {
        $result = $this->validateQuery($query);
        return $result['is_valid'] && ($result['confidence'] ?? 0) >= 0.6;
    }
}
