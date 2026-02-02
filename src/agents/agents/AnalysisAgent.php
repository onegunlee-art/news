<?php
/**
 * Analysis Agent
 * 
 * 기사 분석, 요약, 번역, TTS 생성 Agent
 * - 기사 본문 번역 (한국어)
 * - 3문장 요약 생성
 * - 주요 포인트 도출
 * - "이게 왜 중요한대!" 크리티컬 분석
 * - TTS 오디오 변환
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

class AnalysisAgent extends BaseAgent
{
    private int $summaryLength = 3; // 문장 수
    private int $keyPointsCount = 3;
    private bool $enableTTS = true;

    public function __construct(OpenAIService $openai, array $config = [])
    {
        parent::__construct($openai, $config);
        $this->summaryLength = $config['summary_length'] ?? 3;
        $this->keyPointsCount = $config['key_points_count'] ?? 3;
        $this->enableTTS = $config['enable_tts'] ?? true;
    }

    /**
     * Agent 이름
     */
    public function getName(): string
    {
        return 'AnalysisAgent';
    }

    /**
     * 기본 프롬프트
     */
    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 The Gist의 전문 뉴스 분석가입니다. 모든 출력은 한국어로 작성합니다.',
            'tasks' => [
                'full_analysis' => [
                    'prompt' => '기사를 종합 분석하세요.'
                ],
                'translate' => [
                    'prompt' => '영어 기사를 한국어로 번역하세요.'
                ],
                'summarize' => [
                    'prompt' => '기사를 3문장으로 요약하세요.'
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
            $article = $input->getArticleData();
            return $article !== null && !empty($article->getContent());
        }
        
        if ($input instanceof ArticleData) {
            return !empty($input->getContent());
        }

        return false;
    }

    /**
     * 메인 처리 로직
     */
    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();
        
        $article = $context->getArticleData();
        
        if ($article === null) {
            return AgentResult::failure(
                '분석할 기사 데이터가 없습니다. ValidationAgent를 먼저 실행하세요.',
                $this->getName()
            );
        }

        if (!$this->validate($article)) {
            return AgentResult::failure(
                '기사 콘텐츠가 비어있습니다.',
                $this->getName()
            );
        }

        $this->log("Analyzing article: {$article->getTitle()}", 'info');

        try {
            // 종합 분석 수행
            $analysisResult = $this->performFullAnalysis($article);

            // TTS 생성 (옵션)
            if ($this->enableTTS) {
                $audioUrl = $this->generateTTS($analysisResult);
                $analysisResult = $analysisResult->withAudioUrl($audioUrl);
            }

            // 메타데이터 추가
            $analysisResult = $analysisResult->withMetadata([
                'source_url' => $article->getUrl(),
                'processed_at' => date('c'),
                'agent' => $this->getName(),
                'original_language' => $article->getLanguage(),
                'content_length' => $article->getContentLength()
            ]);

            // 컨텍스트 업데이트
            $context = $context
                ->withAnalysisResult($analysisResult)
                ->markProcessedBy($this->getName());

            return AgentResult::success(
                $analysisResult->toArray(),
                ['agent' => $this->getName()]
            );

        } catch (\Exception $e) {
            $this->log("Analysis error: " . $e->getMessage(), 'error');
            return AgentResult::failure(
                '분석 중 오류 발생: ' . $e->getMessage(),
                $this->getName()
            );
        }
    }

    /**
     * 종합 분석 수행
     */
    private function performFullAnalysis(ArticleData $article): AnalysisResult
    {
        $prompt = $this->buildFullAnalysisPrompt($article);
        $response = $this->callGPT($prompt);
        
        // JSON 응답 파싱
        $data = $this->parseJsonResponse($response);

        return new AnalysisResult(
            translationSummary: $data['translation_summary'] ?? '',
            keyPoints: $data['key_points'] ?? [],
            criticalAnalysis: $data['critical_analysis'] ?? []
        );
    }

    /**
     * 종합 분석 프롬프트 생성
     */
    private function buildFullAnalysisPrompt(ArticleData $article): string
    {
        $template = $this->getPrompt('full_analysis');
        
        if (empty($template)) {
            $template = <<<PROMPT
다음 기사를 종합 분석하세요.

기사 제목: {title}
기사 본문:
{content}

다음 항목을 모두 포함하여 JSON으로 응답하세요:
{
  "translation_summary": "번역된 3문장 요약 (한국어)",
  "key_points": ["주요 포인트 1", "주요 포인트 2", "주요 포인트 3"],
  "critical_analysis": {
    "why_important": "이게 왜 중요한대! - 핵심 시사점과 영향 설명 (2-3문장)",
    "future_prediction": "미래 전망 - 이 이슈의 발전 방향 예측 (2-3문장)"
  }
}
PROMPT;
        }

        return $this->formatPrompt($template, [
            'title' => $article->getTitle(),
            'content' => $this->truncateContent($article->getContent(), 4000)
        ]);
    }

    /**
     * 콘텐츠 길이 제한
     */
    private function truncateContent(string $content, int $maxLength): string
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
        // JSON 블록 추출 시도
        if (preg_match('/\{[\s\S]*\}/u', $response, $matches)) {
            $jsonStr = $matches[0];
            $data = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        // JSON 파싱 실패 시 기본 구조 반환
        $this->log("JSON parsing failed, using fallback", 'warning');
        
        return [
            'translation_summary' => $response,
            'key_points' => ['분석 결과를 확인하세요.'],
            'critical_analysis' => [
                'why_important' => '분석 결과를 확인하세요.',
                'future_prediction' => '추가 분석이 필요합니다.'
            ]
        ];
    }

    /**
     * TTS 오디오 생성
     */
    private function generateTTS(AnalysisResult $analysis): string
    {
        // TTS용 텍스트 생성
        $ttsText = $this->buildTTSText($analysis);
        
        try {
            $audioUrl = $this->openai->textToSpeech($ttsText);
            return $audioUrl ?? '';
        } catch (\Exception $e) {
            $this->log("TTS generation failed: " . $e->getMessage(), 'warning');
            return '';
        }
    }

    /**
     * TTS용 텍스트 생성
     */
    private function buildTTSText(AnalysisResult $analysis): string
    {
        $parts = [];

        // 요약
        $parts[] = "오늘의 뉴스 분석입니다.";
        $parts[] = $analysis->getTranslationSummary();

        // 주요 포인트
        $keyPoints = $analysis->getKeyPoints();
        if (!empty($keyPoints)) {
            $parts[] = "주요 포인트입니다.";
            foreach ($keyPoints as $i => $point) {
                $parts[] = ($i + 1) . "번. " . $point;
            }
        }

        // 중요성 분석
        $whyImportant = $analysis->getWhyImportant();
        if ($whyImportant) {
            $parts[] = "이게 왜 중요한대!";
            $parts[] = $whyImportant;
        }

        // 미래 전망
        $prediction = $analysis->getFuturePrediction();
        if ($prediction) {
            $parts[] = "앞으로의 전망입니다.";
            $parts[] = $prediction;
        }

        return implode(" ", $parts);
    }

    /**
     * 개별 번역 수행
     */
    public function translate(string $text): string
    {
        $this->ensureInitialized();
        
        $prompt = "다음 영어 텍스트를 자연스러운 한국어로 번역하세요:\n\n{$text}";
        return $this->callGPT($prompt);
    }

    /**
     * 개별 요약 수행
     */
    public function summarize(string $text, int $sentences = 3): string
    {
        $this->ensureInitialized();
        
        $prompt = "다음 텍스트를 {$sentences}문장으로 요약하세요:\n\n{$text}";
        return $this->callGPT($prompt);
    }

    /**
     * 개별 분석 수행
     */
    public function analyze(string $text): array
    {
        $this->ensureInitialized();
        
        $prompt = <<<PROMPT
다음 텍스트를 분석하세요:

{$text}

JSON 형식으로 응답:
{
  "key_points": ["포인트 1", "포인트 2", "포인트 3"],
  "why_important": "중요성 설명",
  "future_prediction": "미래 전망"
}
PROMPT;

        $response = $this->callGPT($prompt);
        return $this->parseJsonResponse($response);
    }
}
