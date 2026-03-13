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
        $subtitle = $article->getDescription() ?? '';
        $subheadings = $article->getSubheadings();
        $content = $this->truncateContent($article->getContent(), 40000);

        $domainHint = '';
        if (str_contains($host, 'foreignaffairs.com')) {
            $domainHint = <<<HINT
[Foreign Affairs 기사]
- 본문 중간에 볼드 처리된 대문자 텍스트(예: "REAPING AND SOWING", "ANTICIPATION AND ADAPTATION")가 소제목입니다.
- section_title에 원문 소제목을 그대로 입력하세요. 번역하거나 재해석하지 마세요.
- section_title_ko에는 한글 번역을 넣으세요.
- 소제목이 없는 서론 부분은 "Introduction" 또는 "서론"으로 처리하세요.

HINT;
        } elseif (str_contains($host, 'ft.com')) {
            $domainHint = <<<HINT
[FT.com 기사]
- 소제목이 없거나 약할 수 있음. 논리적 흐름(도입/주장/데이터/결론)으로 가상 섹션을 만드세요.
- 차트, 수치가 많으면 key_points에 반드시 포함하세요.

HINT;
        } elseif (str_contains($host, 'economist.com')) {
            $domainHint = <<<HINT
[The Economist 기사]
- 소제목이 없으므로 단락(paragraph) 구분을 기준으로 가상 섹션을 만드세요.
- 2~3개 단락을 하나의 논리 단위로 묶어서 섹션화하세요.
- 각 섹션명은 해당 단락들의 핵심 주제를 반영하세요 (예: "문제 제기", "핵심 주장", "근거와 데이터", "결론").
- 구독 유도 문구, 날짜, UI 요소는 무시하세요.

[Economist 예시 - 반드시 이 형식을 따르세요]
<example>
기사: "How India Can Supercharge Its Development"

출력:
{
  "news_title": "인도가 어떻게 개발을 초가속할 수 있는가",
  "author": "",
  "original_title": "How India Can Supercharge Its Development",
  "introduction_summary": "미국의 변덕, 중국의 압박 속에서 인도는 CPTPP로 '개혁+성장+규칙'의 지름길을 찾을 수 있다.",
  "section_analysis": [
    {
      "section_title": "UNFAVORABLE CONDITIONS",
      "section_title_ko": "불리한 조건",
      "summary": "중국은 미국 주도의 세계화·개방 국면에서 성장했지만, 인도는 미·중 경쟁과 경제 민족주의, AI·기후변화 같은 변수를 동시에 맞닥뜨렸다. 인도는 지난 10년처럼 미국의 투자·기술이전·시장 접근에 기대기 어렵고, 그래서 EU·일본·한국·동남아 등으로 외연을 넓히고 있다. 대중 견제와 군 현대화를 위해선 장기 고성장이 필요한데, 인도 경제는 '일자리·수출 엔진'이 약하다는 게 문제다.",
      "key_insight": "인도는 미·중 갈등 시대에 미국 의존을 줄이고 다변화 파트너십을 추구하고 있다."
    },
    {
      "section_title": "BLOC ECONOMICS",
      "section_title_ko": "블록 경제학",
      "summary": "CPTPP는 관세 인하뿐 아니라 노동·지재권·투자·국영기업(SOE) 규율 같은 '높은 기준'을 요구해, 가입 자체가 구조개혁을 밀어붙이는 장치가 된다. CPTPP는 12개 회원국으로 세계 경제의 약 15%를 차지하며, 연간 약 5,000억 달러 규모의 교역 흐름을 포괄한다. 베트남은 CPTPP 준수 거점으로서 해외투자를 끌어 수출을 키웠고, 인도도 전자·자동차·정밀제조 등에서 공급망 편입을 가속할 수 있다.",
      "key_insight": "CPTPP 가입은 인도에 연간 약 560억 달러 GDP 증가 효과를 낼 수 있다."
    },
    {
      "section_title": "HOW TO ESCAPE THE MIDDLE-INCOME TRAP",
      "section_title_ko": "중진국 함정에서 벗어나는 법",
      "summary": "최대 난관은 정치적으로 민감한 분야다: 농산물 관세 인하, 법률·전문서비스 개방, 의약품 지재권 강화, 국영기업 지원 제한이 포함된다. 2019년 RCEP 협상에서 철수한 사례 때문에 신뢰 문제도 남아 있다. 저자들은 단계적 접근을 제안한다: 격차 분석→옵서버 참여→디지털·AI 표준과 기후금융 연계 같은 덜 민감한 분야부터 신뢰를 쌓자는 것이다.",
      "key_insight": "인도는 농업 개혁을 생산성 제고·농촌 인프라 투자와 묶어 단계적으로 추진할 수 있다."
    },
    {
      "section_title": "INDIA'S GAMBIT",
      "section_title_ko": "인도의 승부수",
      "summary": "인도가 들어오면 CPTPP는 '미국도, 중국도 지배하지 않는' 더 큰 경제 질서로 확장될 수 있다. 인도·CPTPP·ASEAN·EU의 수렴은 유럽~인도태평양을 잇는 거대한 개방경제 블록을 만들고, 중소국에 선택지를 제공할 수 있다. 트럼프 시대의 관세 충격, 중국의 공세, 모디의 강한 정치적 입지가 결합된 지금이 '어려운 개혁을 밀어붙일 창'이다.",
      "key_insight": "인도에 CPTPP는 '중진국 함정 탈출'과 '대중 경쟁력 강화'를 동시에 달성하는 수단이다."
    }
  ],
  "key_points": [
    "트럼프 행정부의 고율 관세로 인도의 외교·통상 계산이 흔들리며 파트너 다변화 추진",
    "CPTPP는 12개 회원국으로 세계 경제의 약 15%, 연간 약 5,000억 달러 규모 교역 포괄",
    "인도 CPTPP 가입 시 연간 약 560억 달러 GDP 증가 효과 추정",
    "2019년 RCEP 철수로 인한 신뢰 문제가 남아 있음"
  ],
  "geopolitical_implication": "인도의 CPTPP 가입은 미·중 어느 쪽도 지배하지 않는 개방경제 블록을 확장시켜, 중소국에 선택지를 제공하고 한국을 포함한 역내 국가들의 통상 환경을 재편할 수 있다."
}
</example>

[Economist 예시에서 배울 점]
- section_title: 원문에 명시적 소제목이 없으면 단락 내용을 기반으로 영문 대문자 소제목을 생성 (예: UNFAVORABLE CONDITIONS)
- section_title_ko: 생성한 영문 소제목의 한글 번역
- 2~3개 단락을 하나의 섹션으로 묶기
- summary: 해당 섹션의 핵심 내용 3~5문장, 구체적 수치/사실 포함
- key_insight: 한 줄로 핵심 인사이트

HINT;
        } else {
            $domainHint = <<<HINT
[일반 기사]
- 소제목이 있으면 그대로 사용하세요 (번역/재해석 금지).
- 소제목이 없으면 논리적 흐름으로 가상 섹션을 만드세요.

HINT;
        }

        return <<<PROMPT
##############################################################
# The Gist AI 원문 분석 가이드
##############################################################

{$domainHint}

[무시할 요소]
- UI 텍스트, 이미지 캡션, 날짜, 구독 유도 문구, Pull quote

[참조 예시 - 이 형식을 따르세요]
<example>
기사: "Will China Overplay Its Hand?"

출력:
{
  "news_title": "중국은 자기 손을 과하게 쓸까?",
  "author": "",
  "original_title": "Will China Overplay Its Hand?",
  "introduction_summary": "트럼프의 3일 방중 정상회담은 2026년 최대 네 차례에 이를 수 있는 트럼프-시진핑 회담의 '첫 단추'가 될 수 있다. 2025년 10월 부산에서 양측은 일부 강경한 경제 조치를 1년 유예하며 '확전'에서 한발 물러섰다. 다만 휴전은 환적(우회수출) 관세, 희토류·반도체 수출통제, 비경제 제재 같은 핵심 쟁점을 그대로 남겼다.",
  "section_analysis": [
    {
      "section_title": "BACK TO THE FUTURE?",
      "section_title_ko": "과거로 돌아가나?",
      "summary": "국제정치에서는 현실만큼 '인식'이 중요하며, 2025년 이후 중국의 자신감이 마찰과 강압 행동으로 이어질 수 있다. 2008년 금융위기 뒤 과신에 빠진 중국이 2010년 전후 더 거친 태도로 주변국을 소외시켰던 전례가 있다. 2026년 초의 흐름이 이미 2010년을 닮아가고 있는바, 일본 제재, 110억 달러 규모 대만 무기판매 이후 대만 인근 훈련 강화, 주변 해역에서의 공세적 행보 등이 그 사례다.",
      "key_insight": "2008년 금융위기 후 과신했던 중국이 2010년 주변국 반발을 샀던 패턴이 반복될 수 있다."
    },
    {
      "section_title": "WHAT'S WRONG WITH BEING CONFIDENT",
      "section_title_ko": "자신감이 뭐가 문제인가",
      "summary": "부산 휴전이 취약한 이유는 미국이 특히 민감해하는 환적(우회수출) 관세 문제를 비켜갔기 때문이다. 미국은 달러 금융망을 통한 '금융 핵무기'를 보유하고 있어, 중국 대형 국유은행에 광범위 제재가 걸리면 충격이 매우 클 수 있다. 중국의 희토류 카드도 위력적이지만, 달러의 중심성은 중국의 '목줄'보다 더 오래 지속될 가능성이 높다.",
      "key_insight": "미국의 달러 기반 금융제재 vs 중국의 희토류 통제—서로의 급소를 쥐고 있는 상황."
    }
  ],
  "key_points": [
    "2025년 부산 합의로 일부 경제 조치가 1년 유예되었으나, 환적 관세·희토류 통제 등 핵심 쟁점은 미해결",
    "2008년 금융위기 후 과신했던 중국이 2010년 주변국 반발을 산 전례가 있음",
    "미국은 달러 금융망을 통한 '금융 핵무기' 보유, 중국 대형 국유은행 제재 시 충격 클 것",
    "유럽·중동·중남미에서도 미중 마찰면 확대 가능성"
  ],
  "geopolitical_implication": "2026년 미중 관계는 정상회담 기회가 많지만, '부산 휴전'이 갈등 해결이라는 착시가 중국의 과신을 키워 오히려 충돌 가능성을 높일 수 있다."
}
</example>

[예시에서 배울 점]
- section_title: 원문의 영문 소제목 그대로 (번역/재해석 금지)
- section_title_ko: 한글 번역
- summary: 해당 섹션의 핵심 내용 3~5문장
- key_insight: 한 줄로 핵심 인사이트
- key_points: 구체적 사실/수치 포함

---

##############################################################
# 스크래핑된 기사 정보 (정확히 사용하세요)
##############################################################

[기사 URL]
{$url}

[원문 제목 - Title]
{$title}

[원문 부제목 - Subtitle]
{$subtitle}

[원문 소제목 목록 - Subheadings]
{$this->formatSubheadingsForPrompt($subheadings, $host)}

[기사 본문]
{$content}

##############################################################
# 출력 규칙
##############################################################

1. news_title: 원문 제목을 한글로 번역
2. original_title: 원문 제목 그대로 (위 [원문 제목] 사용)
3. introduction_summary: 원문 부제목을 한글로 번역 + 서론 요약 (위 [원문 부제목] 참조)
4. section_analysis:
   - Foreign Affairs: 위 [원문 소제목 목록]의 각 소제목을 section_title에 그대로 사용
   - Economist/FT/일반: 단락별 주제를 영문 대문자로 생성하여 section_title에 사용
   - section_title_ko: section_title의 한글 번역

위 규칙에 따라 JSON으로 응답하세요. JSON 외 텍스트 금지.
PROMPT;
    }

    /**
     * 소제목 목록을 프롬프트용 문자열로 포맷
     */
    private function formatSubheadingsForPrompt(array $subheadings, string $host): string
    {
        if (empty($subheadings)) {
            if (str_contains(strtolower($host), 'economist.com')) {
                return "(소제목 없음 - 단락별 주제를 영문 대문자로 생성하세요)";
            }
            if (str_contains(strtolower($host), 'ft.com')) {
                return "(소제목 없음 - 논리적 흐름으로 가상 섹션을 만드세요)";
            }
            return "(소제목 없음)";
        }

        $lines = [];
        foreach ($subheadings as $i => $heading) {
            $num = $i + 1;
            $lines[] = "{$num}. {$heading}";
        }
        return implode("\n", $lines);
    }

    /**
     * 섹션 분석에서 content_summary 생성 (참조 형식)
     * 
     * 형식:
     * 한글 제목 (영문 원제)
     * - 부제목/핵심 요약
     * 
     * 서론 요약 문장들
     * 
     * 1. 소제목 (영문 소제목)
     * 
     * - 요점1
     * - 요점2
     */
    private function buildContentSummaryFromSections(array $data): string
    {
        // 섹션 간 반드시 한 줄 띄우기: 블록 단위로 모아서 "\n\n"로 연결
        $blocks = [];
        
        // 제목 (한글 + 영문 원제)
        $newsTitle = $data['news_title'] ?? '';
        $originalTitle = $data['original_title'] ?? '';
        if ($newsTitle) {
            $blocks[] = $originalTitle && $originalTitle !== $newsTitle
                ? "{$newsTitle} ({$originalTitle})"
                : $newsTitle;
        }
        
        // 서론 요약 (부제목) — 끝나고 한 줄 띄움
        if (!empty($data['introduction_summary'])) {
            $blocks[] = "- " . trim($data['introduction_summary']);
        }
        
        // 섹션별 분석 — 각 소제목 앞·뒤 한 줄 띄움
        if (!empty($data['section_analysis']) && is_array($data['section_analysis'])) {
            $sectionNum = 1;
            foreach ($data['section_analysis'] as $section) {
                $titleKo = $section['section_title_ko'] ?? '';
                $titleEn = $section['section_title'] ?? '';
                $summary = $section['summary'] ?? '';
                $keyInsight = $section['key_insight'] ?? '';
                
                $sectionLines = [];
                if ($titleKo || $titleEn) {
                    if ($titleKo && $titleEn && $titleKo !== $titleEn) {
                        $sectionLines[] = "{$sectionNum}. {$titleKo} ({$titleEn})";
                    } elseif ($titleKo) {
                        $sectionLines[] = "{$sectionNum}. {$titleKo}";
                    } else {
                        $sectionLines[] = "{$sectionNum}. {$titleEn}";
                    }
                }
                if ($summary) {
                    $sentences = $this->splitIntoSentences($summary);
                    foreach ($sentences as $sentence) {
                        $sentence = trim($sentence);
                        if ($sentence) {
                            $sectionLines[] = "- " . $sentence;
                        }
                    }
                }
                if ($keyInsight && $keyInsight !== $summary) {
                    $sectionLines[] = "- " . trim($keyInsight);
                }
                if ($sectionLines !== []) {
                    $blocks[] = implode("\n", $sectionLines);
                }
                $sectionNum++;
            }
        }
        
        // 왜 중요한가 — 앞에 한 줄 띄움
        if (!empty($data['geopolitical_implication'])) {
            $blocks[] = "왜 중요한가\n\n- " . trim($data['geopolitical_implication']);
        }
        
        return implode("\n\n", $blocks);
    }
    
    /**
     * 요약 텍스트를 문장 단위로 분리
     */
    private function splitIntoSentences(string $text): array
    {
        // 한국어 문장 종결 패턴으로 분리
        $pattern = '/(?<=[.!?다요])\s+/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
        return $sentences ?: [$text];
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
