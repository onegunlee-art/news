<?php
/**
 * Analysis Agent
 * 
 * 기사 분석 Agent (GPT-5.2)
 * - GPT가 뉴스 제목 생성
 * - 본문 전체를 4개 이상 불렛 포인트로 요약
 * - 뉴스 앵커 스타일 내레이션 스크립트 생성
 * - The Gist's Critique (왜 중요한가)
 * - TTS 오디오 변환
 * 
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 2.0.0
 */

declare(strict_types=1);

namespace Agents\Agents;

use Agents\Core\BaseAgent;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Models\AnalysisResult;
use Agents\Services\OpenAIService;
use Agents\Services\GoogleTTSService;
use Agents\Services\RAGService;

class AnalysisAgent extends BaseAgent
{
    private int $keyPointsCount = 4;
    private bool $enableTTS = true;
    private ?GoogleTTSService $googleTts = null;
    private ?RAGService $ragService = null;

    public function __construct(OpenAIService $openai, array $config = [], ?GoogleTTSService $googleTts = null, ?RAGService $ragService = null)
    {
        parent::__construct($openai, $config);
        $this->keyPointsCount = max(4, (int) ($config['key_points_count'] ?? 4));
        $this->enableTTS = $config['enable_tts'] ?? true;
        $this->googleTts = $googleTts;
        $this->ragService = $ragService ?? $config['rag_service'] ?? null;
    }

    public function getName(): string
    {
        return 'AnalysisAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 "The Gist"의 수석 에디터입니다. 모든 기사는 지스터(The Gist 독자)를 위한 콘텐츠입니다. 지스터는 해외 뉴스를 한국어로 이해하고 싶어하는 독자층이며, The Gist의 핵심 독자입니다. 반드시 지스터 독자 관점에서 작성하고, 요청된 JSON 형식으로만 응답하세요.',
            'tasks' => [
                'full_analysis' => [
                    'prompt' => '기사를 종합 분석하세요.'
                ],
                'translate' => [
                    'prompt' => '영어 기사를 한국어로 번역하세요.'
                ],
                'summarize' => [
                    'prompt' => '기사를 요약하세요.'
                ]
            ]
        ];
    }

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
            $analysisResult = $this->performFullAnalysis($article);

            if ($this->enableTTS) {
                $audioUrl = $this->generateTTS($analysisResult);
                $analysisResult = $analysisResult->withAudioUrl($audioUrl);
            }

            $analysisResult = $analysisResult->withMetadata([
                'source_url' => $article->getUrl(),
                'processed_at' => date('c'),
                'agent' => $this->getName(),
                'original_language' => $article->getLanguage(),
                'content_length' => $article->getContentLength()
            ]);

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
     * 종합 분석 수행 (GPT-5.2 명시)
     */
    private function performFullAnalysis(ArticleData $article): AnalysisResult
    {
        $prompt = $this->buildFullAnalysisPrompt($article);

        $options = ['model' => 'gpt-5.2', 'timeout' => 180, 'max_tokens' => 8000];
        if ($this->ragService && $this->ragService->isConfigured()) {
            $query = $article->getTitle() . ' ' . mb_substr($article->getContent(), 0, 500);
            $ragContext = $this->ragService->retrieveRelevantContext($query, 3);
            $basePrompt = $this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.';
            $options['system_prompt'] = $this->ragService->buildSystemPromptWithRAG($basePrompt, $ragContext);
        }

        $response = $this->callGPT($prompt, $options);
        
        $data = $this->parseJsonResponse($response);

        // narration 정규화: 시청자 여러분 → 지스터 여러분
        $narration = $this->normalizeNarration($data['narration'] ?? null);

        // narration이 있으면 translation_summary로도 사용 (하위 호환)
        $translationSummary = $data['translation_summary'] ?? '';
        if (empty($translationSummary) && !empty($narration)) {
            $translationSummary = mb_substr($narration, 0, 200);
        }

        return new AnalysisResult(
            translationSummary: $translationSummary,
            keyPoints: $data['key_points'] ?? [],
            criticalAnalysis: $data['critical_analysis'] ?? [],
            newsTitle: $data['news_title'] ?? null,
            narration: $narration,
            contentSummary: $data['content_summary'] ?? null,
            originalTitle: $data['original_title'] ?? null,
            author: $data['author'] ?? null
        );
    }

    /**
     * 종합 분석 프롬프트 생성 (v3: content_summary, 4+ key_points, narration 900자+, Critique 미요청)
     */
    private function buildFullAnalysisPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        $template = <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}
기사 본문:
{$content}

[제목과 저자 구분 방법]
URL의 맨 뒤 / 다음 부분(슬러그)에 제목과 저자가 함께 포함되어 있습니다.
1) 저자를 먼저 추출하세요. (보통 슬러그 끝부분이 저자 이름)
2) 저자를 제외한 나머지가 원문 제목(original_title)입니다. 하이픈을 공백으로, 각 단어 첫 글자 대문자로 변환 (예: the-limits-of-russian-power → The Limits of Russian Power)

위 기사를 분석하여 아래 JSON 형식으로만 응답하세요. JSON 외에 다른 텍스트는 절대 포함하지 마세요.

{
  "news_title": "지스터가 클릭하고 싶은 한국어 뉴스 제목. 15~30자. 핵심을 담되 임팩트 있게. (지스터 = The Gist 독자)",
  "author": "URL 슬러그에서 추출한 저자 이름. 예: Stephen Kotkin",
  "original_title": "URL 슬러그에서 저자를 제외한 나머지를 제목 형식으로 변환. 예: The Limits of Russian Power. (저자 제외, 본문에서 확인한 정확한 영문 제목과 일치하면 그대로 사용)",
  "content_summary": "원문을 한국어로 번역한 내용으로 작성. 중요 단어·전문 용어는 괄호 안에 영어 원문을 병기 (예: ai 삼중딜레마 (ai trilemma)). 도입·전개·결론 구조 유지, 핵심 논지·사실·수치 포함. 최소 600자 이상, 가능하면 900자 이상. 마크다운 제목(##)과 불렛(-) 사용 가능.",
  "key_points": [
    "기사 본문의 핵심 내용을 최소 4개 이상의 불렛 포인트로 요약. 각 항목은 1~2문장, 구체적 사실과 수치를 포함.",
    "두 번째 핵심 포인트",
    "세 번째 핵심 포인트",
    "네 번째 핵심 포인트"
  ],
  "narration": "지스터(The Gist 독자)를 위한 내레이션. 반드시 '지스터 여러분'으로 시작하세요. 도입(무슨 일이 있었는지) → 주요 내용(핵심 사실들)을 자연스럽게 이어서 말하듯 작성. 지스터가 귀로만 들어도 기사 전체를 이해할 수 있도록 충분히 상세하게. 최소 900자 이상."
}
PROMPT;

        return $template;
    }

    /**
     * 콘텐츠 길이 제한 (40000자 - 긴 기사 지원)
     */
    private function truncateContent(string $content, int $maxLength = 40000): string
    {
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }
        return mb_substr($content, 0, $maxLength) . '...';
    }

    /**
     * JSON 응답 파싱 (안정성 강화: 어떤 경우든 빈 결과 없음)
     */
    private function parseJsonResponse(string $response): array
    {
        // JSON 블록 추출 (```json ... ``` 또는 바로 { ... })
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

        // JSON 파싱 실패 → GPT 원문을 narration에 넣어 빈 결과 방지
        $this->log("JSON parsing failed for GPT response, using fallback. Response length: " . strlen($response), 'warning');
        
        return [
            'news_title' => null,
            'original_title' => null,
            'author' => null,
            'narration' => $this->normalizeNarration(trim($response)),
            'key_points' => ['분석 결과를 확인하세요.'],
            'content_summary' => null,
            'critical_analysis' => []
        ];
    }

    /** 시청자 여러분 → 지스터 여러분 정규화 */
    private function normalizeNarration(?string $narration): ?string
    {
        if ($narration === null || trim($narration) === '') {
            return $narration;
        }
        $out = str_replace('시청자 여러분', '지스터 여러분', $narration);
        $out = preg_replace('/^시청자\s+여러분/', '지스터 여러분', $out);
        $out = str_replace('청취자가', '지스터가', $out);
        $out = str_replace('청취자에게', '지스터에게', $out);
        return $out !== $narration ? $out : $narration;
    }

    /**
     * TTS 오디오 생성
     */
    private function generateTTS(AnalysisResult $analysis): string
    {
        $ttsText = $this->buildTTSText($analysis);

        try {
            if ($this->googleTts !== null) {
                $voice = $this->config['tts_voice'] ?? null;
                $options = $voice !== null ? ['voice' => $voice] : [];
                $audioUrl = $this->googleTts->textToSpeech($ttsText, $options);
            } else {
                $audioUrl = $this->openai->textToSpeech($ttsText);
            }
            return $audioUrl ?? '';
        } catch (\Exception $e) {
            $this->log("TTS generation failed: " . $e->getMessage(), 'warning');
            return '';
        }
    }

    /**
     * TTS용 텍스트 생성
     * narration 필드가 있으면 그대로 사용 (GPT가 이미 "말하기 좋게" 작성)
     * 없으면 기존 방식으로 조합 (하위 호환)
     */
    private function buildTTSText(AnalysisResult $analysis): string
    {
        // narration이 있으면 그대로 TTS에 사용
        $narration = $analysis->getNarration();
        if (!empty($narration)) {
            return $narration;
        }

        // fallback: 기존 방식
        $parts = [];
        $parts[] = "오늘의 뉴스 분석입니다.";

        $summary = $analysis->getTranslationSummary();
        if (!empty($summary)) {
            $parts[] = $summary;
        }

        $keyPoints = $analysis->getKeyPoints();
        if (!empty($keyPoints)) {
            $parts[] = "주요 포인트입니다.";
            foreach ($keyPoints as $i => $point) {
                $parts[] = ($i + 1) . "번. " . $point;
            }
        }

        return implode(" ", $parts);
    }

    /**
     * GPT 재분석 (Admin 피드백 반영)
     *
     * Admin이 초안 분석에 코멘트/점수를 남기면,
     * 그 피드백을 반영하여 GPT가 개선된 분석을 생성합니다.
     *
     * @param array  $originalAnalysis 이전 분석 결과 (news_title, content_summary, key_points, narration 등)
     * @param string $adminFeedback    Admin이 작성한 코멘트
     * @param int|null $score          품질 점수 (1-10)
     * @return array 개선된 분석 결과 (동일 JSON 구조)
     */
    public function revise(array $originalAnalysis, string $adminFeedback, ?int $score = null): array
    {
        $this->ensureInitialized();

        $originalJson = json_encode($originalAnalysis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $scoreText = $score !== null ? "현재 품질 점수: {$score}/10\n" : '';

        $prompt = <<<PROMPT
당신은 이전에 아래와 같은 뉴스 분석을 작성했습니다:

=== 이전 분석 ===
{$originalJson}
=== 이전 분석 끝 ===

편집자(Admin)가 다음과 같은 피드백을 남겼습니다:

=== 편집자 피드백 ===
{$adminFeedback}
{$scoreText}=== 편집자 피드백 끝 ===

위 피드백을 반영하여 분석을 개선하세요. 반드시 아래 JSON 형식으로만 응답하세요.
피드백에서 지적된 부분을 수정하고, 더 나은 분석을 작성하세요.
JSON 외에 다른 텍스트는 절대 포함하지 마세요.

{
  "news_title": "지스터를 위한 개선된 한국어 뉴스 제목. 15~30자.",
  "author": "URL 슬러그에서 추출한 저자 이름. 이전 분석에서 가져오거나 유지.",
  "original_title": "원문의 영어 제목 그대로. 이전 분석에서 가져오거나 기사에서 확인.",
  "content_summary": "원문을 한국어로 번역한 내용. 중요 단어는 (영어 원문) 병기 (예: ai 삼중딜레마 (ai trilemma)). 최소 600자 이상, 가능하면 900자 이상.",
  "key_points": [
    "개선된 핵심 포인트 1",
    "개선된 핵심 포인트 2",
    "개선된 핵심 포인트 3",
    "개선된 핵심 포인트 4"
  ],
  "narration": "지스터를 위한 개선된 내레이션. 반드시 '지스터 여러분'으로 시작. 최소 900자 이상."
}
PROMPT;

        $options = ['model' => 'gpt-5.2', 'timeout' => 180, 'max_tokens' => 8000];

        // RAG 컨텍스트 주입 (knowledge_library 포함)
        if ($this->ragService && $this->ragService->isConfigured()) {
            $query = ($originalAnalysis['news_title'] ?? '') . ' ' . mb_substr($adminFeedback, 0, 300);
            $ragContext = $this->ragService->retrieveRelevantContext($query, 3);
            $basePrompt = $this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.';
            $options['system_prompt'] = $this->ragService->buildSystemPromptWithRAG($basePrompt, $ragContext);
        }

        $response = $this->callGPT($prompt, $options);
        $data = $this->parseJsonResponse($response);
        if (isset($data['narration']) && $data['narration'] !== null) {
            $data['narration'] = $this->normalizeNarration($data['narration']);
        }
        return $data;
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
  "key_points": ["포인트 1", "포인트 2", "포인트 3", "포인트 4"],
  "why_important": "중요성 설명"
}
PROMPT;
        $response = $this->callGPT($prompt);
        return $this->parseJsonResponse($response);
    }
}
