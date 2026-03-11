<?php
/**
 * Analysis Agent
 * 
 * 기사 분석 Agent (GPT-5.2)
 * - GPT가 뉴스 제목 생성
 * - 본문 전체를 4개 이상 불렛 포인트로 요약
 * - 뉴스 앵커 스타일 내레이션 스크립트 생성
 * - 왜 중요한가(why_important)
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
use Agents\Services\PersonaService;

class AnalysisAgent extends BaseAgent
{
    private int $keyPointsCount = 4;
    private bool $enableTTS = true;
    private ?GoogleTTSService $googleTts = null;
    private ?RAGService $ragService = null;
    private ?PersonaService $personaService = null;

    public function __construct(OpenAIService $openai, array $config = [], ?GoogleTTSService $googleTts = null, ?RAGService $ragService = null, ?PersonaService $personaService = null)
    {
        parent::__construct($openai, $config);
        $this->keyPointsCount = max(4, (int) ($config['key_points_count'] ?? 4));
        $this->enableTTS = $config['enable_tts'] ?? true;
        $this->googleTts = $googleTts;
        $this->ragService = $ragService ?? $config['rag_service'] ?? null;
        $this->personaService = $personaService ?? $config['persona_service'] ?? null;
    }

    public function getName(): string
    {
        return 'AnalysisAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 "The Gist"의 수석 에디터입니다. 모든 기사는 해외 뉴스를 한국어로 이해하고 싶어하는 독자를 위한 콘텐츠입니다. 반드시 독자 관점에서 작성하고, 요청된 JSON 형식으로만 응답하세요.',
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
        if ($this->isAdminPurePromptMode()) {
            $options = $this->buildAnalysisOptions($article, false);
            $analysisPrompt = $this->withReferenceImages(
                $this->buildStructuredAnalysisPrompt($article),
                $options
            );
            $analysisResponse = $this->callGPT($analysisPrompt, $options);
            $data = $this->parseJsonResponse($analysisResponse);

            $narrationPrompt = $this->buildNarrationFromAnalysisPrompt($article, $data);
            $narrationResponse = $this->callGPT($narrationPrompt, $options);
            $narrationData = $this->parseJsonResponse($narrationResponse);
            if (!empty($narrationData['narration'])) {
                $data['narration'] = $narrationData['narration'];
            }
        } else {
            $prompt = $this->buildFullAnalysisPrompt($article);
            $options = $this->buildAnalysisOptions($article, true);
            $prompt = $this->withReferenceImages($prompt, $options);
            $response = $this->callGPT($prompt, $options);
            $data = $this->parseJsonResponse($response);
        }

        // original_title: 스크래퍼 메타(ArticleData.title) 1순위. GPT가 슬러그 재구성으로 잘못 바꾸는 것 방지.
        $originalTitle = $article->getTitle() !== '' && $article->getTitle() !== null
            ? $article->getTitle()
            : ($data['original_title'] ?? null);

        // narration 정규화: 인사말 제거, 메타 문구 제거
        $narration = $this->normalizeNarration($data['narration'] ?? null);
        $narration = $this->stripMetaPhrasesFromText($narration);
        $narration = $this->normalizeParagraphBreaks($narration);

        $contentSummary = $this->stripMetaPhrasesFromText($data['content_summary'] ?? null);
        $contentSummary = $this->normalizeParagraphBreaks($contentSummary);

        $criticalAnalysis = $data['critical_analysis'] ?? [];
        if (is_array($criticalAnalysis) && isset($criticalAnalysis['why_important'])) {
            $criticalAnalysis['why_important'] = $this->normalizeParagraphBreaks(
                $this->stripMetaPhrasesFromText($criticalAnalysis['why_important'])
            );
        }

        // narration이 있으면 translation_summary로도 사용 (하위 호환)
        $translationSummary = $data['translation_summary'] ?? '';
        if (empty($translationSummary) && !empty($narration)) {
            $translationSummary = mb_substr($narration, 0, 200);
        }

        return new AnalysisResult(
            translationSummary: $translationSummary,
            keyPoints: $data['key_points'] ?? [],
            criticalAnalysis: $criticalAnalysis,
            newsTitle: $data['news_title'] ?? null,
            narration: $narration,
            contentSummary: $contentSummary,
            originalTitle: $originalTitle,
            author: $data['author'] ?? null,
            sections: $data['sections'] ?? []
        );
    }

    private function isAdminPurePromptMode(): bool
    {
        return (bool) ($this->config['admin_pure_prompt_mode'] ?? false);
    }

    private function buildAnalysisOptions(ArticleData $article, bool $allowRuntimeContext): array
    {
        $options = [
            'model' => $this->config['model'] ?? 'gpt-5.2',
            'timeout' => (int) ($this->config['timeout'] ?? 180),
            'max_tokens' => (int) ($this->config['max_tokens'] ?? 8000),
        ];

        $basePrompt = $this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.';
        if (!$allowRuntimeContext) {
            $options['system_prompt'] = $basePrompt;
            return $options;
        }

        $basePrompt = $this->personaService ? $this->personaService->getSystemPrompt() : $basePrompt;
        if ($this->ragService && $this->ragService->isConfigured()) {
            $query = $article->getTitle() . ' ' . mb_substr($article->getContent(), 0, 500);
            $ragContext = $this->ragService->retrieveRelevantContext($query, 3);
            $options['system_prompt'] = $this->ragService->buildSystemPromptWithRAG($basePrompt, $ragContext);
        } else {
            $options['system_prompt'] = $basePrompt;
        }

        return $options;
    }

    private function withReferenceImages(string $prompt, array &$options): string
    {
        $imageUrls = $this->loadAllReferenceImages();
        if (empty($imageUrls)) {
            return $prompt;
        }

        $options['image_urls'] = $imageUrls;
        return "[참조 이미지 - 반드시 확인] 첨부된 참조 이미지를 순서대로 확인하세요.\n\n"
            . "1) 제목: Foreign Affairs 기사 상단 스크린샷. 제목(가장 큰 볼드체) 위치를 확인하세요.\n"
            . "2) 소제목: 본문 중간의 섹션 헤딩(큰 글씨/대문자) 예시. content_summary에서 소제목 한글(영문) 형식으로 나열하세요.\n"
            . "3) 무시할 부분: pull quote 스타일의 큰 볼드 텍스트. content_summary, key_points, narration에 포함하지 마세요.\n"
            . "4) 요약 룰: content_summary 작성 형식(영한 교차 등) 참고.\n"
            . "5) [가독성 형식 - 필수] The Gist 스타일 content_summary 예시. 줄글이 아닌 구조화된 형식을 학습하세요:\n"
            . "   - 섹션별 명확한 제목/헤딩 (한글 (영문) 형식)\n"
            . "   - 영문 1줄 → 한글 1줄 교차 (짧은 문단)\n"
            . "   - 여백과 문단 구분으로 가독성 확보\n"
            . "   - 연속된 줄글 금지. 반드시 위 이미지처럼 구분된 형식으로 작성.\n\n"
            . "기사 본문에서 위 패턴에 맞게 분석하세요.\n\n" . $prompt;
    }

    private function buildStructuredAnalysisPrompt(ArticleData $article): string
    {
        $url = $article->getUrl();
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        if (str_contains($host, 'ft.com')) {
            return $this->buildFTStructuredPrompt($article);
        }
        if (str_contains($host, 'economist.com')) {
            return $this->buildEconomistStructuredPrompt($article);
        }
        return $this->buildDefaultStructuredPrompt($article);
    }

    private function buildFTStructuredPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        return <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

[FT.com 분석 규칙]
- 이 단계에서는 narration을 만들지 말고, 먼저 분석 결과만 구조화하세요.
- FT는 소제목이 약할 수 있으므로 도입, 핵심 주장, 데이터, 반론, 결론 흐름으로 정리하세요.
- 차트, 수치, 비율, 추세가 있으면 key_points와 content_summary에 반드시 반영하세요.
- Premium content, Recommended, Related, Save, Share, Listen, Print, Sign up, 캡션, 크레딧, 날짜 단독 라인은 모두 제거하세요.

[문단 규칙]
- content_summary와 critical_analysis.why_important는 반드시 짧은 문단으로 쓰세요.
- 각 문단은 1~3문장.
- 문단이 바뀔 때마다 반드시 빈 줄 하나(\n\n)를 넣으세요.
- 한 문단에는 하나의 논점만 담으세요.

[출력 목표]
- content_summary: 한국 독자가 기사 전체 논리를 빠르게 이해하게 하는 구조화 요약
- key_points: 사실, 수치, 핵심 주장 중심
- critical_analysis.why_important: 왜 중요한지 한 단계 위에서 설명

아래 JSON 형식으로만 응답하세요.

{
  "news_title": "기사 제목을 과장 없이 자연스러운 한국어로 옮긴 제목",
  "author": "저자 이름 (없으면 빈 문자열)",
  "original_title": "위 기사 제목을 그대로 유지",
  "sections": [],
  "content_summary": "짧은 문단들로 구성된 구조화 요약. 최소 700자 이상",
  "key_points": [
    "핵심 포인트 1",
    "핵심 포인트 2",
    "핵심 포인트 3",
    "핵심 포인트 4"
  ],
  "critical_analysis": {
    "why_important": "짧은 문단들로 구성된 중요성 설명"
  }
}
PROMPT;
    }

    private function buildEconomistStructuredPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        return <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

[The Economist 분석 규칙]
- 이 단계에서는 narration을 만들지 말고, 먼저 분석 결과만 구조화하세요.
- 이 기사는 반드시 문단(paragraph) 단위로 읽으세요.
- 각 문단이 어떤 역할인지 먼저 파악하세요: 도입 / 문제 제기 / 핵심 주장 / 근거 / 반론 / 결론.
- 각 문단의 핵심을 짧게 머릿속에 정리한 뒤, 그 문단 흐름을 따라 content_summary를 다시 구성하세요.
- 문단 순서를 무시한 채 한 번에 뭉뚱그려 요약하지 마세요.

[반드시 무시할 잡음]
- "This article appeared in...", "Subscribe to...", "For more expert analysis..." 같은 편집/구독 유도 문구
- Save, Share, Listen to this story, Reuse this content, Sign up, Log in, Menu, Skip to content
- Illustration:, Photo:, Getty Images, Reuters 등 캡션/크레딧
- Mar 5th 2026, 3 min read 같은 날짜/읽기시간 단독 라인
- Recommended, Related, newsletter 블록
- Leaders |, Briefing |, Finance & economics | 같은 앞쪽 네비게이션 라벨

[문단 규칙]
- content_summary와 critical_analysis.why_important는 반드시 짧은 문단으로 쓰세요.
- 각 문단은 1~3문장.
- 문단이 바뀔 때마다 반드시 빈 줄 하나(\n\n)를 넣으세요.
- 한 문단에는 하나의 논점만 담으세요.

[출력 목표]
- content_summary: 문단 흐름이 살아 있는 구조화 요약
- key_points: 각 문단의 핵심 주장/사실/수치가 빠지지 않게 정리
- critical_analysis.why_important: 이 논지가 외교, 경제, 안보, 정책에 왜 중요한지 설명

아래 JSON 형식으로만 응답하세요.

{
  "news_title": "기사 제목을 과장 없이 자연스러운 한국어로 옮긴 제목",
  "author": "저자 이름 (없으면 빈 문자열)",
  "original_title": "위 기사 제목을 그대로 유지",
  "sections": [],
  "content_summary": "문단 흐름을 따라 재구성한 구조화 요약. 최소 700자 이상",
  "key_points": [
    "핵심 포인트 1",
    "핵심 포인트 2",
    "핵심 포인트 3",
    "핵심 포인트 4"
  ],
  "critical_analysis": {
    "why_important": "짧은 문단들로 구성된 중요성 설명"
  }
}
PROMPT;
    }

    private function buildDefaultStructuredPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        return <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

[기본 분석 규칙]
- 이 단계에서는 narration을 만들지 말고, 먼저 분석 결과만 구조화하세요.
- 기존 Foreign Affairs 스타일의 강점은 유지하세요. 다만 과도하게 새 형식으로 갈아엎지 마세요.
- 소제목이 보이면 sections와 content_summary에 반영하세요.
- pull quote, 캡션, 날짜 단독 라인, UI 문구, 구독 유도 문구는 제거하세요.
- 한국 독자가 기사 핵심 논리와 맥락을 빠르게 이해할 수 있도록 설명형으로 정리하세요.

[문단 규칙]
- content_summary와 critical_analysis.why_important는 반드시 짧은 문단으로 쓰세요.
- 각 문단은 1~3문장.
- 문단이 바뀔 때마다 반드시 빈 줄 하나(\n\n)를 넣으세요.
- 한 문단에는 하나의 논점만 담으세요.

[출력 목표]
- content_summary: 기사 논지를 재구성한 구조화 요약
- key_points: 핵심 사실, 주장, 수치, 배경 중심
- critical_analysis.why_important: 왜 중요한지 The Gist 에디터 톤으로 설명

아래 JSON 형식으로만 응답하세요.

{
  "news_title": "기사 제목을 과장 없이 자연스러운 한국어로 옮긴 제목",
  "author": "저자 이름 (없으면 빈 문자열)",
  "original_title": "위 기사 제목을 그대로 유지",
  "sections": [],
  "content_summary": "짧은 문단들로 구성된 구조화 요약. 최소 700자 이상",
  "key_points": [
    "핵심 포인트 1",
    "핵심 포인트 2",
    "핵심 포인트 3",
    "핵심 포인트 4"
  ],
  "critical_analysis": {
    "why_important": "짧은 문단들로 구성된 중요성 설명"
  }
}
PROMPT;
    }

    private function buildNarrationFromAnalysisPrompt(ArticleData $article, array $analysisData): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 25000);
        $analysisJson = json_encode([
            'news_title' => $analysisData['news_title'] ?? null,
            'author' => $analysisData['author'] ?? null,
            'original_title' => $analysisData['original_title'] ?? null,
            'content_summary' => $analysisData['content_summary'] ?? null,
            'key_points' => $analysisData['key_points'] ?? [],
            'critical_analysis' => $analysisData['critical_analysis'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
기사 제목: {$title}

기사 원문:
{$content}

1차 분석 결과:
{$analysisJson}

위 기사 원문과 1차 분석 결과를 함께 참고하여 The Gist 스타일의 narration만 작성하세요.

[narration 작성 목표]
- 청자가 기사 원문을 읽지 않아도 전체 맥락을 이해하게 만드세요.
- 단순 요약이 아니라 핵심 논지, 배경, 중요한 근거, 의미를 자연스럽게 설명하세요.
- 첫 문장은 바로 핵심으로 들어가세요.
- 인사말로 시작하지 마세요.
- 기사에 없는 주장이나 과도한 확신을 덧붙이지 마세요.
- key_points를 기계적으로 나열하지 말고 흐름 있는 설명문으로 다시 쓰세요.

[문단 규칙]
- 각 문단은 1~3문장.
- 문단이 바뀔 때마다 반드시 빈 줄 하나(\n\n)를 넣으세요.
- 한 문단에는 하나의 논점만 담으세요.
- paragraph별 한 줄 띄기 규칙을 반드시 지키세요.

[분량]
- 최소 1000자 이상.
- 가능하면 1100~1500자 밀도로 작성하세요.

아래 JSON 형식으로만 응답하세요.

{
  "narration": "짧은 문단들로 구성된 The Gist 스타일 narration"
}
PROMPT;
    }

    /**
     * 도메인별 종합 분석 프롬프트 분기 (FT.com, Economist, 기본)
     */
    private function buildFullAnalysisPrompt(ArticleData $article): string
    {
        $url = $article->getUrl();
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        if (str_contains($host, 'ft.com')) {
            return $this->buildFTPrompt($article);
        }
        if (str_contains($host, 'economist.com')) {
            return $this->buildEconomistPrompt($article);
        }
        return $this->buildDefaultPrompt($article);
    }

    /**
     * FT.com 전용: 데이터/차트 설명, 소제목 없는 구조, narration 1000자+
     */
    private function buildFTPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        return <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

[FT.com 기사 특성]
- 소제목이 없거나 적을 수 있음. 이 경우 논리적 흐름(도입·전개·데이터·결론)으로 content_summary 섹션을 나누어 작성하세요.
- 데이터, 차트, 수치 설명이 많을 수 있음. 핵심 수치와 결론을 요약에 반드시 포함하세요.
- "Premium content", "Recommended", "Related" 등 블록은 본문이 아니므로 무시하세요.
- 인용문(quote)은 핵심 논지와 연결해 요약에 포함해도 됩니다.
- 웹페이지에서 복사·붙여넣기 된 경우 "Save", "Share", "Listen", "Print", "Sign up", 이미지 캡션/크레딧, 단독 날짜 라인, 구독 유도 문구 등은 무시하세요.

[제목/저자]
- original_title: 위에 주어진 "기사 제목"을 단어 하나도 바꾸지 말고 그대로 사용하세요. 재구성·추론하지 마세요.
- author: 본문 또는 URL 슬러그에서 추출. 없으면 빈 문자열.

[narration·content_summary 문단 규칙 - 반드시 준수]
narration과 content_summary 모두 아래 규칙을 지키세요:
1) 의미가 전환되는 곳에서 반드시 빈 줄(\n\n)로 문단을 나누세요.
2) 한 문단은 1~3문장. 하나의 논점만 담습니다.
3) 절대 전체를 한 덩어리로 이어 붙이지 마세요.

좋은 예 (narration):
"미국의 무역적자가 사상 최고치를 경신했습니다.

이번 데이터는 특히 중국과의 교역에서 적자 폭이 크게 확대된 것이 주요 원인으로 꼽힙니다.

전문가들은 이러한 추세가 당분간 지속될 것으로 전망하고 있습니다."

나쁜 예 (한 덩어리):
"미국의 무역적자가 사상 최고치를 경신했습니다. 이번 데이터는 특히 중국과의 교역에서 적자 폭이 크게 확대된 것이 주요 원인으로 꼽힙니다. 전문가들은 이러한 추세가 당분간 지속될 것으로 전망하고 있습니다."

위 기사를 분석하여 아래 JSON 형식으로만 응답하세요. JSON 외에 다른 텍스트는 절대 포함하지 마세요.

{
  "news_title": "기사 영문 제목을 단어 그대로 직역한 한국어. 임팩트 문구가 아닌 직역만.",
  "author": "저자 이름 (본문 또는 URL에서 추출, 없으면 빈 문자열)",
  "original_title": "위 '기사 제목'과 동일하게 그대로 (재구성 금지)",
  "content_summary": "가독성 좋은 구조화 형식. 한글 제목 (영문 제목)\n\n[논리적 섹션별 요약 - 영문 1줄 → 한글 1줄 교차]. 소제목이 있으면 섹션 제목으로 사용, 없으면 도입·전개·데이터·결론 등으로 구분.\n\n- 위 [문단 규칙]을 반드시 따르세요\n- 최소 600자 이상.",
  "key_points": [
    "핵심 내용 최소 4개 이상, 구체적 사실과 수치 포함",
    "두 번째 포인트",
    "세 번째 포인트",
    "네 번째 포인트"
  ],
  "narration": "위 [문단 규칙]을 반드시 따르세요. 인사말 없이 바로 본문 시작. 도입 → 주요 내용(수치·배경·결론)을 자연스럽게 이어서 말하듯 작성. 귀로만 들어도 기사 전체를 이해할 수 있도록 충분히 상세하게. 최소 900자 이상."
}
PROMPT;
    }

    /**
     * The Economist 전용: 광고/CTA 무시, 섹션 구조 대응, narration 1000자+
     */
    private function buildEconomistPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        return <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

[The Economist 기사 특성 - 반드시 무시할 부분]
- "This article appeared in...", "For more expert analysis...", "Subscribe to..." 등 편집/구독 유도 문구는 본문이 아니므로 완전히 무시하세요.
- 중간에 끼어 있는 광고, CTA, "Recommended", "Related" 블록은 분석에서 제외하세요.
- 기사가 중간에 잘렸거나 광고로 끊긴 것처럼 보이면, 읽을 수 있는 부분까지만 분석하세요. narration 본문에는 상태 문구를 붙이지 마세요.

[웹페이지 복사 잡음 - 반드시 무시]
본문이 웹페이지에서 복사·붙여넣기 된 것일 수 있습니다. 다음 패턴은 기사 본문이 아니므로 완전히 무시하세요:
- 섹션/카테고리 라벨: "Leaders |", "Briefing |", "Finance & economics |" 등 맨 앞의 섹션명
- UI 요소 텍스트: "Save", "Share", "Listen to this story", "Reuse this content", "Sign up", "Log in", "Menu", "Skip to content"
- 이미지 캡션/크레딧: "Illustration:", "illustration:", "Photo:", "Getty Images", "Reuters" 등 단독 라인
- 날짜/읽기시간 라인: "Mar 5th 2026", "3 min read" 등 단독 날짜 표시
- 구독/뉴스레터 유도: "Subscribers to The Economist can sign up...", "Sign up for our...", "newsletter" 관련 문구
- 이 잡음들을 제거한 후 순수 기사 본문만 분석하세요.

[섹션 구조]
- Leader, Briefing, Finance & economics 등 섹션명이 있으면 content_summary에서 한글(영문)로 반영.
- 소제목(큰 글씨/대문자 헤딩)이 있으면 섹션별로 요약 작성.

[제목/저자]
- original_title: 위에 주어진 "기사 제목"을 단어 하나도 바꾸지 말고 그대로 사용하세요. URL 슬러그 재구성하지 마세요.
- author: 본문 또는 URL 슬러그에서 추출. 없으면 빈 문자열.

[narration·content_summary 문단 규칙 - 반드시 준수]
narration과 content_summary 모두 아래 규칙을 지키세요:
1) 의미가 전환되는 곳에서 반드시 빈 줄(\n\n)로 문단을 나누세요.
2) 한 문단은 1~3문장. 하나의 논점만 담습니다.
3) 절대 전체를 한 덩어리로 이어 붙이지 마세요.

좋은 예 (narration):
"유럽 경제가 전환점을 맞이하고 있습니다.

독일의 제조업 지표가 3개월 연속 하락하며, 유로존 전체의 성장 전망에 먹구름이 드리우고 있습니다.

ECB는 추가 금리 인하 가능성을 시사했지만, 인플레이션 리스크도 여전합니다."

나쁜 예 (한 덩어리):
"유럽 경제가 전환점을 맞이하고 있습니다. 독일의 제조업 지표가 3개월 연속 하락하며, 유로존 전체의 성장 전망에 먹구름이 드리우고 있습니다. ECB는 추가 금리 인하 가능성을 시사했지만, 인플레이션 리스크도 여전합니다."

위 기사를 분석하여 아래 JSON 형식으로만 응답하세요. JSON 외에 다른 텍스트는 절대 포함하지 마세요.

{
  "news_title": "기사 영문 제목을 직역한 한국어.",
  "author": "저자 (없으면 빈 문자열)",
  "original_title": "위 '기사 제목'과 동일하게 그대로 (재구성 금지)",
  "content_summary": "구조화 형식. 한글 제목 (영문 제목)\n\n[섹션별 요약 - 영문 1줄 → 한글 1줄 교차]. 소제목이 있으면 섹션 제목으로 사용.\n\n- 위 [문단 규칙]을 반드시 따르세요\n- 최소 600자 이상.",
  "key_points": [
    "핵심 4개 이상, 구체적 사실·수치 포함",
    "두 번째 포인트",
    "세 번째 포인트",
    "네 번째 포인트"
  ],
  "narration": "위 [문단 규칙]을 반드시 따르세요. 인사말 없이 바로 본문 시작. 도입(무슨 일이 있었는지) → 주요 내용(핵심 사실들)을 자연스럽게 이어서 말하듯 작성. 귀로만 들어도 기사 전체를 이해할 수 있도록 충분히 상세하게. 최소 900자 이상."
}
PROMPT;
    }

    /**
     * 기본(Foreign Affairs 등): 168번 프롬프트 기반, 소제목·pull-quote·문단 규칙
     */
    private function buildDefaultPrompt(ArticleData $article): string
    {
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);
        $url = $article->getUrl();

        $template = <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

[제목/저자]
- original_title: 위에 주어진 "기사 제목"을 단어 하나도 바꾸지 말고 그대로 사용하세요. URL 슬러그에서 재구성하지 마세요. (스크래퍼가 이미 og:title 등에서 추출한 정확한 영문 제목입니다.)
- author: URL 슬러그(맨 뒤 / 다음) 또는 본문에서 추출. 예: Stephen Kotkin.

[소제목(Subheading) 식별 방법]
- 본문 중간에 등장하는, 주변보다 큰 글씨 또는 전부 대문자로 된 짧은 문구
- 예: "ANTICIPATION AND ADAPTATION" (섹션 구분용 헤딩)
- content_summary에서 각 소제목을 한글(영문) 형식으로 나열하고, 그 아래 해당 섹션 요약을 작성

[해석 시 무시할 부분]
본문 중간에 등장하는 "pull quote" 스타일의 큰 볼드 텍스트는 요약/분석에서 완전히 무시하세요.
- 예: 본문과 별도로 강조된 한 문장 ("The American military has largely had the same programs and training for the last 30 years.")
- 이런 인용형 볼드는 기사 핵심이 아니므로 content_summary, key_points, narration에 포함하지 마세요.

[웹페이지 복사 잡음 - 반드시 무시]
본문이 웹페이지에서 복사·붙여넣기 된 것일 수 있습니다. 다음 패턴은 기사 본문이 아니므로 완전히 무시하세요:
- UI 요소: "Save", "Share", "Listen to this story", "Reuse this content", "Print", "Sign up", "Log in", "Menu"
- 이미지 캡션/크레딧: "Illustration:", "Photo:", "Getty Images" 등 단독 라인
- 날짜/읽기시간 단독 라인: "Mar 5th 2026", "3 min read" 등
- 구독/뉴스레터 유도: "Subscribe to...", "Subscribers to...", "Sign up for our newsletter" 등
- 섹션 카테고리 라벨: "Leaders |", "Briefing |" 등 맨 앞의 네비게이션 구분
- 이 잡음들을 제거한 후 순수 기사 본문만 분석하세요.

[narration·content_summary 문단 규칙 - 반드시 준수]
narration과 content_summary 모두 아래 규칙을 지키세요:
1) 의미가 전환되는 곳에서 반드시 빈 줄(\n\n)로 문단을 나누세요.
2) 한 문단은 1~3문장. 하나의 논점만 담습니다.
3) 절대 전체를 한 덩어리로 이어 붙이지 마세요.

좋은 예 (narration):
"트럼프 대통령이 동맹국과의 관계를 망가뜨리고 있는 건 사실입니다.

그런데 차기 미국 대통령이 취임하면 모든 동맹을 원래대로 복원해야 할까요? 이 글은 그렇지 않다고 봅니다.

저자는 동맹마다 성적표를 매겨야 한다고 주장합니다."

나쁜 예 (한 덩어리):
"트럼프 대통령이 동맹국과의 관계를 망가뜨리고 있는 건 사실입니다. 그런데 차기 미국 대통령이 취임하면 모든 동맹을 원래대로 복원해야 할까요? 이 글은 그렇지 않다고 봅니다. 저자는 동맹마다 성적표를 매겨야 한다고 주장합니다."

위 기사를 분석하여 아래 JSON 형식으로만 응답하세요. JSON 외에 다른 텍스트는 절대 포함하지 마세요.

{
  "news_title": "기사 영문 제목(original_title)을 단어 그대로 직역한 한국어. 예: What America Must Learn From Ukraine → 미국이 우크라이나에서 배워야 할 것. 임팩트 문구가 아닌 직역만.",
  "author": "URL 슬러그 또는 본문에서 추출한 저자. 예: Stephen Kotkin",
  "original_title": "위 '기사 제목'과 동일하게 그대로 (재구성 금지)",
  "content_summary": "아래 구조를 정확히 따르세요.\n\n한글 제목 (영문 제목)\n한글 부제 (영문 부제)\n\n[첫 번째 소제목 나오기 전까지의 전체 요약 - 영문 한 줄 → 한글 한 줄 교차]\n\n소제목1 한글 (영문 소제목)\n[해당 섹션 - 영문 한 줄 → 한글 한 줄 교차]\n\n소제목2 한글 (영문 소제목)\n[해당 섹션 - 영문 한 줄 → 한글 한 줄 교차]\n\n- 소제목이 없으면 한글 제목/부제/전체 요약(영한 교차)만 작성\n- 위 [문단 규칙]을 반드시 따르세요\n- 최소 600자 이상",
  "key_points": [
    "기사 본문의 핵심 내용을 최소 4개 이상의 불렛 포인트로 요약. 각 항목은 1~2문장, 구체적 사실과 수치를 포함. (pull-quote 볼드는 제외)",
    "두 번째 핵심 포인트",
    "세 번째 핵심 포인트",
    "네 번째 핵심 포인트"
  ],
  "narration": "위 [문단 규칙]을 반드시 따르세요. 인사말 없이 바로 본문 시작. 도입 → 주요 내용을 자연스럽게 이어서 말하듯 작성. pull-quote 볼드는 제외. 귀로만 들어도 기사 전체를 이해할 수 있을 만큼 상세하게. 최소 900자 이상."
}
PROMPT;

        return $template;
    }

    /**
     * GPT 분석용 참조 이미지 전체 로드 (base64 data URL 배열)
     * src/agents/assets/reference/ 내 이미지들 (배포에 포함됨)
     * - title_subtitle.jpg / subtitle_foreign_affairs.png: 제목 위치 확인용 참조 이미지
     * - subheading_reference.jpg: 소제목 식별
     * - pull_quote_ignore.jpg: 무시할 pull quote
     * - summary_rules.jpg: 요약 룰
     * - readability_format.png: content_summary 가독성 형식 (The Gist 스타일)
     */
    private function loadAllReferenceImages(): array
    {
        $base = dirname(__DIR__) . '/assets/reference/';
        $first = is_file($base . 'subtitle_foreign_affairs.png') ? 'subtitle_foreign_affairs.png' : 'title_subtitle.jpg';
        $files = [
            $first,
            'subheading_reference.jpg',
            'pull_quote_ignore.jpg',
            'summary_rules.jpg',
            'readability_format.png',
        ];
        $result = [];
        foreach ($files as $f) {
            $path = $base . $f;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            $data = file_get_contents($path);
            if ($data === false || strlen($data) < 100) {
                continue;
            }
            $mime = str_ends_with($f, '.png') ? 'image/png' : 'image/jpeg';
            $result[] = 'data:' . $mime . ';base64,' . base64_encode($data);
        }
        return $result;
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

    /** 인사말 제거: 여러분, 시청자 여러분 등으로 시작하면 제거하고 자연스럽게 정리 */
    private function normalizeNarration(?string $narration): ?string
    {
        if ($narration === null || trim($narration) === '') {
            return $narration;
        }
        $out = trim($narration);
        // 문두 인사말 제거 (쉼표·마침표 뒤부터 시작)
        $out = preg_replace('/^(여러분|시청자\s+여러분|청취자\s+여러분)[,.\s]*/u', '', $out);
        $out = preg_replace('/^(여러분)[,.\s]*/u', '', $out);
        return trim($out) ?: $narration;
    }

    /** content_summary/narration에 섞인 메타·브랜드 문구 제거 (저장 전 적용) */
    private function stripMetaPhrasesFromText(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }
        $out = $text;
        // RAG 블록 전체 제거
        $out = preg_replace('/\n?---\s*RAG Context[^\n]*\n[\s\S]*/u', '', $out);
        $out = preg_replace('/\n?##\s*과거 분석 참고자료\s*\n?/u', "\n", $out);
        $out = preg_replace('/\n?##\s*참조 프레임워크[^\n]*\n(?:(?:아래|이론)[^\n]*\n)?/u', "\n", $out);
        $out = preg_replace('/\n?##\s*편집자 크리틱[^\n]*\n?/u', "\n", $out);
        $out = preg_replace('/\n-\s*\[유사도\s*[\d.]+\][^\n]*/u', '', $out);
        // 문구 단위 제거
        $out = preg_replace('/\s*The Gist\'s Critique\.?\s*:?/ui', ' ', $out);
        $out = preg_replace('/\s*지스터\s*(관점의\s*)?시사점\.?\s*:?/u', ' ', $out);
        $out = preg_replace('/\s*\[기사 일부만 분석됨\]\s*/u', ' ', $out);
        $out = preg_replace('/\s*참고자료\s*:?/u', ' ', $out);
        $out = preg_replace('/\s*참고자료를\s*제대로\s*반영하지\s*못했습니다\.?\s*/u', ' ', $out);
        $out = preg_replace('/\s*참고글을\s*제대로\s*못했다\.?\s*/u', ' ', $out);
        $out = preg_replace('/\s*참조 프레임워크[^\n.]*\.?/u', ' ', $out);
        $out = preg_replace('/\s{2,}/u', ' ', $out);
        return trim($out) ?: $text;
    }

    private function normalizeParagraphBreaks(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        $out = str_replace(["\r\n", "\r"], "\n", trim($text));
        $out = preg_replace("/\n{3,}/u", "\n\n", $out);
        return trim($out) ?: $text;
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
  "news_title": "독자를 위한 개선된 한국어 뉴스 제목. 15~30자.",
  "author": "URL 슬러그에서 추출한 저자 이름. 이전 분석에서 가져오거나 유지.",
  "original_title": "원문의 영어 제목 그대로. 이전 분석에서 가져오거나 기사에서 확인.",
  "content_summary": "줄글이 아닌 가독성 좋은 구조화 형식. 참조 이미지(가독성 형식)를 확인하고: 섹션별 제목(한글 (영문)), 영문 1줄→한글 1줄 교차, 짧은 문단, 여백·문단 구분. 연속된 줄글 금지. 최소 600자 이상.",
  "key_points": [
    "개선된 핵심 포인트 1",
    "개선된 핵심 포인트 2",
    "개선된 핵심 포인트 3",
    "개선된 핵심 포인트 4"
  ],
  "narration": "독자를 위한 개선된 내레이션. 인사말 없이 바로 본문으로 자연스럽게 시작. 최소 900자 이상."
}
PROMPT;

        $options = ['model' => 'gpt-5.2', 'timeout' => 180, 'max_tokens' => 8000];

        $basePrompt = $this->personaService ? $this->personaService->getSystemPrompt() : ($this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.');
        if ($this->ragService && $this->ragService->isConfigured()) {
            $query = ($originalAnalysis['news_title'] ?? '') . ' ' . mb_substr($adminFeedback, 0, 300);
            $ragContext = $this->ragService->retrieveRelevantContext($query, 3);
            $options['system_prompt'] = $this->ragService->buildSystemPromptWithRAG($basePrompt, $ragContext);
        } else {
            $options['system_prompt'] = $basePrompt;
        }

        $imageUrls = $this->loadAllReferenceImages();
        if (!empty($imageUrls)) {
            $options['image_urls'] = $imageUrls;
            $prompt = "[참조 이미지 - 반드시 확인] 첨부된 참조 이미지 5번(가독성 형식)을 확인하세요. content_summary는 줄글이 아닌 구조화된 형식으로 작성: 섹션별 제목(한글 (영문)), 영문 1줄→한글 1줄 교차, 짧은 문단, 여백·문단 구분.\n\n" . $prompt;
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
