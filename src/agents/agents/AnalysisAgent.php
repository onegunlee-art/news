<?php
/**
 * Analysis Agent
 * 
 * 기사 분석 Agent (Claude Sonnet 4.6)
 * - 서론 요약 (introduction_summary)
 * - 섹션별 분석 (section_analysis[])
 * - 핵심 포인트 (key_points[])
 * - 지정학적 함의 (geopolitical_implication)
 * 
 * Narration과 TTS는 별도 Agent에서 처리
 * 
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 4.0.0 - 구조화된 분석 + Narration 분리
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

class AnalysisAgent extends BaseAgent
{
    private ?ClaudeService $claude = null;

    public function __construct(OpenAIService $openai, array $config = [], ?ClaudeService $claude = null)
    {
        parent::__construct($openai, $config);
        $this->claude = $claude ?? new ClaudeService();
    }

    public function getName(): string
    {
        return 'AnalysisAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 "The Gist"의 수석 에디터입니다. 해외 뉴스를 한국어로 분석하여 독자가 핵심을 빠르게 파악할 수 있도록 합니다. 반드시 요청된 JSON 형식으로만 응답하세요.'
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
            $analysisResult = $this->performStructuredAnalysis($article);

            $analysisResult = $analysisResult->withMetadata([
                'source_url' => $article->getUrl(),
                'processed_at' => date('c'),
                'agent' => $this->getName(),
                'original_language' => $article->getLanguage(),
                'content_length' => $article->getContentLength()
            ]);

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
     * 구조화된 분석 수행 (Claude Sonnet 4.6)
     */
    private function performStructuredAnalysis(ArticleData $article): AnalysisResult
    {
        $systemPrompt = $this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.';
        $analysisPrompt = $this->buildAnalysisPrompt($article);
        
        $response = $this->callClaude($systemPrompt, $analysisPrompt);
        $data = $this->parseJsonResponse($response);
        
        $this->logClaudeResponse('analysis', $response, $data);

        $originalTitle = $article->getTitle() !== '' && $article->getTitle() !== null
            ? $article->getTitle()
            : ($data['original_title'] ?? null);

        $contentSummary = $this->buildContentSummaryFromSections($data);
        
        $criticalAnalysis = [
            'why_important' => $data['geopolitical_implication'] ?? null,
        ];

        return new AnalysisResult(
            translationSummary: $data['introduction_summary'] ?? '',
            keyPoints: $data['key_points'] ?? [],
            criticalAnalysis: $criticalAnalysis,
            audioUrl: null,
            metadata: [],
            newsTitle: $data['news_title'] ?? null,
            narration: null,
            contentSummary: $contentSummary,
            originalTitle: $originalTitle,
            author: $data['author'] ?? null,
            sections: [],
            introductionSummary: $data['introduction_summary'] ?? null,
            sectionAnalysis: $data['section_analysis'] ?? [],
            geopoliticalImplication: $data['geopolitical_implication'] ?? null
        );
    }

    /**
     * 도메인별 분석 프롬프트 생성
     */
    private function buildAnalysisPrompt(ArticleData $article): string
    {
        $url = $article->getUrl();
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $title = $article->getTitle();
        $content = $this->truncateContent($article->getContent(), 40000);

        $domainHint = '';
        if (str_contains($host, 'foreignaffairs.com')) {
            $domainHint = "[Foreign Affairs 기사]\n- 소제목이 명확하게 있음. 각 소제목을 section_analysis에 반영하세요.\n- 대문자 헤딩(예: 'ANTICIPATION AND ADAPTATION')을 소제목으로 인식하세요.\n";
        } elseif (str_contains($host, 'ft.com')) {
            $domainHint = "[FT.com 기사]\n- 소제목이 없거나 약할 수 있음. 논리적 흐름(도입/주장/데이터/결론)으로 가상 섹션을 만드세요.\n- 차트, 수치가 많으면 key_points에 반드시 포함하세요.\n";
        } elseif (str_contains($host, 'economist.com')) {
            $domainHint = "[The Economist 기사]\n- 문단 역할(도입/문제제기/핵심주장/근거/반론/결론)을 파악하여 가상 섹션으로 나누세요.\n- 구독 유도 문구, 날짜, UI 요소는 무시하세요.\n";
        } else {
            $domainHint = "[일반 기사]\n- 소제목이 있으면 그대로 사용, 없으면 논리적 흐름으로 가상 섹션을 만드세요.\n";
        }

        return <<<PROMPT
기사 URL: {$url}
기사 제목: {$title}

기사 본문:
{$content}

{$domainHint}

[분석 규칙]
1. 서론 요약 (introduction_summary): 기사의 핵심 주장과 배경을 2~3문장으로 요약
2. 섹션별 분석 (section_analysis): 
   - 소제목이 있으면 그대로 사용
   - 없으면 단락 흐름을 분석하여 "도입", "핵심 주장", "근거/데이터", "반론", "결론" 등으로 가상 섹션 생성
   - 각 섹션당 summary(요약)와 key_insight(핵심 인사이트) 포함
3. 핵심 포인트 (key_points): 기사에서 가장 중요한 사실/주장/수치 4~6개
4. 지정학적 함의 (geopolitical_implication): 왜 이 기사가 중요한지, 한국 독자 관점에서 의미

[무시할 요소]
- UI 텍스트: Save, Share, Listen, Print, Sign up, Menu
- 이미지 캡션/크레딧: Illustration, Photo, Getty Images, Reuters
- 날짜/읽기시간: Mar 5th 2026, 3 min read
- 구독 유도: Subscribe, Sign up for newsletter
- Pull quote (큰 볼드 인용문)

아래 JSON 형식으로만 응답하세요. JSON 외 텍스트 금지.

{
  "news_title": "기사 제목을 자연스러운 한국어로 번역",
  "author": "저자 이름 (없으면 빈 문자열)",
  "original_title": "영문 원제 그대로",
  "introduction_summary": "서론 요약 (2~3문장, 핵심 주장과 배경)",
  "section_analysis": [
    {
      "section_title": "영문 소제목 또는 가상 섹션명",
      "section_title_ko": "한글 소제목",
      "summary": "해당 섹션 요약 (3~5문장)",
      "key_insight": "이 섹션의 핵심 인사이트 (1~2문장)"
    }
  ],
  "key_points": [
    "핵심 포인트 1 (구체적 사실/수치 포함)",
    "핵심 포인트 2",
    "핵심 포인트 3",
    "핵심 포인트 4"
  ],
  "geopolitical_implication": "지정학적 함의 / 왜 중요한가 (2~3문장)"
}
PROMPT;
    }

    /**
     * 섹션 분석에서 content_summary 생성 (하위 호환)
     */
    private function buildContentSummaryFromSections(array $data): string
    {
        $parts = [];
        
        if (!empty($data['introduction_summary'])) {
            $parts[] = $data['introduction_summary'];
        }
        
        if (!empty($data['section_analysis']) && is_array($data['section_analysis'])) {
            foreach ($data['section_analysis'] as $section) {
                $title = $section['section_title_ko'] ?? $section['section_title'] ?? '';
                $summary = $section['summary'] ?? '';
                if ($title && $summary) {
                    $parts[] = "【{$title}】\n{$summary}";
                }
            }
        }
        
        if (!empty($data['geopolitical_implication'])) {
            $parts[] = "【왜 중요한가】\n" . $data['geopolitical_implication'];
        }
        
        return implode("\n\n", $parts);
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
            'max_tokens' => (int)($this->config['max_tokens'] ?? 8192),
            'temperature' => (float)($this->config['temperature'] ?? 0.3),
            'timeout' => (int)($this->config['timeout'] ?? 180),
        ], $options));
    }

    /**
     * 콘텐츠 길이 제한
     */
    private function truncateContent(string $content, int $maxLength = 40000): string
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

        $this->log("JSON parsing failed, using fallback", 'warning');
        return [
            'news_title' => null,
            'original_title' => null,
            'author' => null,
            'introduction_summary' => '',
            'section_analysis' => [],
            'key_points' => ['분석 결과를 확인하세요.'],
            'geopolitical_implication' => null
        ];
    }

    /**
     * Claude 응답 로깅 (디버깅용)
     */
    private function logClaudeResponse(string $stage, string $rawResponse, array $parsedData): void
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $logFile = $logDir . "/claude_analysis_{$timestamp}.json";
        
        $logData = [
            'timestamp' => date('c'),
            'stage' => $stage,
            'model' => 'claude-sonnet-4-6',
            'raw_response_length' => strlen($rawResponse),
            'raw_response_preview' => mb_substr($rawResponse, 0, 2000),
            'parsed_keys' => array_keys($parsedData),
            'section_analysis_count' => count($parsedData['section_analysis'] ?? []),
            'key_points_count' => count($parsedData['key_points'] ?? []),
        ];
        
        @file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->log("Claude response logged to: {$logFile}", 'debug');
    }
}
