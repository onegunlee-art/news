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

[절대 규칙 - 반드시 준수]
1. 전체 분석 결과는 반드시 900자 이상 작성하세요.
2. 소제목(section_analysis)은 반드시 원문에 등장하는 순서대로 분석하세요. 순서를 바꾸거나 재배열하지 마세요.
3. section_title에는 원문 소제목을 그대로 사용하세요. 번역하거나 재해석하지 마세요.

{$domainHint}

[무시할 요소]
- UI 텍스트, 이미지 캡션, 날짜, 구독 유도 문구, Pull quote

[참조 예시 1 - Foreign Affairs 스타일]
<example>
기사: "The Perils of Militarizing Law Enforcement"

출력:
{
  "news_title": "법집행기관의 군사화 위험성",
  "author": "",
  "original_title": "The Perils of Militarizing Law Enforcement",
  "introduction_summary": "라틴 아메리카가 미국 민주주의에 보내는 경고. 2025년 8월, 워싱턴 D.C. 주민들은 미국에서는 드문 장면을 목격했다. 연방 정부가 주도한 범죄 대응 캠페인의 일환으로 주방위군이 시내를 순찰한 것이다. 문제는 당시 D.C.의 폭력범죄가 그해 1월 '30여 년 만의 최저치'로 떨어졌는데도, 트럼프 대통령이 8월 11일 행정명령으로 '범죄 비상사태'를 선포하며 '통제 회복을 위한 비상조치'를 정당화했다는 점이다.",
  "section_analysis": [
    {
      "section_title": "COP-OUT",
      "section_title_ko": "빠져나가기",
      "summary": "처음엔 '예외 상태'라는 이름의 임시 대응으로 시작하지만, 시간이 지나면 군의 치안 개입이 정상적인 것처럼 여겨지고, 행정부 권력이 집중되며, 시민 제도가 약화되고, 시민적 자유가 침식된다. 멕시코는 1990년대부터 군의 치안 투입이 늘었고, 2006년 칼데론의 '마약과의 전쟁' 이후 급격히 확대됐다. 2006~2019년 살인율은 인구 10만 명당 9명에서 29명으로 상승했다.",
      "key_insight": "일시적 비상조치가 상시 통치 방식으로 굳어지는 경로가 라틴아메리카에서 반복되었다."
    },
    {
      "section_title": "A CURE WORSE THAN THE DISEASE?",
      "section_title_ko": "질병 자체보다 더 유해한 처방?",
      "summary": "군사화된 치안이 장기적으로 범죄를 줄인다는 실증적 근거는 약하다. 빈곤·불평등·부패 같은 구조적 원인을 건드리지 못하고, 오히려 폭력과 인권침해를 늘릴 수 있다는 연구가 있다.",
      "key_insight": "군 투입은 범죄의 구조적 원인을 해결하지 못하며 오히려 폭력을 증가시킬 수 있다."
    },
    {
      "section_title": "NO ORDER IN THE COURT",
      "section_title_ko": "법정에서의 질서 부재",
      "summary": "더 근본적으로는 '헌정 질서'가 흔들린다. 비상선포는 입법·사법의 견제를 약화시키고, 군 투입이 반복될수록 '군과 경찰의 경계'는 되돌리기 어려워진다.",
      "key_insight": "비상선포의 반복은 삼권분립을 약화시키고 헌정 질서를 훼손한다."
    },
    {
      "section_title": "NO TURNING BACK",
      "section_title_ko": "돌이킬 수 없는",
      "summary": "미국은 역사적으로 내란법(1807)과 포시 코미타투스 법(1878) 등으로 연방군의 국내 법집행을 제한해 왔다. 하지만 이런 규범적·법적 방벽이 조금씩 깎이면, 특정 행정부를 넘어서는 장기적 권력 재배치가 일어날 수 있다.",
      "key_insight": "한 번 흐려진 군-경찰 경계는 되돌리기 어렵고 후속 정부에도 선례로 남는다."
    }
  ],
  "key_points": [
    "2025년 8월 워싱턴 D.C. 폭력범죄가 30년 만의 최저치인데도 범죄 비상사태 선포",
    "멕시코 2006~2019년 살인율이 인구 10만 명당 9명에서 29명으로 상승",
    "엘살바도르 30일 예외 상태가 47차례 연장되어 상시 통치 프레임화",
    "미국 내란법(1807)과 포시 코미타투스 법(1878)이 연방군 국내 법집행 제한"
  ],
  "geopolitical_implication": "군의 치안 투입이 반복되면 예외적 조치가 상시적 통치 방식으로 굳어지고, 이는 미국 민주주의의 핵심 기둥인 군-경찰 분리 원칙을 훼손할 수 있다."
}
</example>

[참조 예시 2 - 동맹 분석 스타일]
<example>
기사: "America Needs an Alliance Audit"

출력:
{
  "news_title": "미국은 동맹 감사를 필요로 한다",
  "author": "",
  "original_title": "America Needs an Alliance Audit",
  "introduction_summary": "모든 파트너십이 유지할 가치가 있는 것은 아니다. 트럼프는 단기적 이익을 위해 압박, 동맹 영토 병합 위협, 무차별 관세로 동맹을 훼손하고 있다. 그렇다고 해서 차기 대통령이 냉전기의 동맹 틀을 그대로 복원해서는 안 된다. 중국·러시아·핵무장한 북한은 동맹의 필요성을 높이면서도, 전쟁 연루 위험과 비용도 크게 높인다.",
  "section_analysis": [
    {
      "section_title": "DON'T SETTLE",
      "section_title_ko": "정착하지 말 것",
      "summary": "동맹은 반도체·핵심 광물 등 공급망 다변화와 기술 표준 선점으로 미국의 상대적 약화를 보완할 수 있다. 동맹은 군사 역량 제공과 기지·인프라 접근을 통해 방위 부담을 줄이고, 다자기구에서 외교적 지렛대를 보태준다. 하지만 동맹은 전쟁 의무와 복수의 전선에 대한 대비 비용을 높이며, 감당 못 할 약속이 될 수도 있다.",
      "key_insight": "동맹은 이익과 비용 양면이 있으며, 자동 유지가 아닌 전략적 평가가 필요하다."
    },
    {
      "section_title": "TRIM THE FAT",
      "section_title_ko": "군살 빼기",
      "summary": "미·필리핀 동맹은 재검토가 필요하다. 한국은 경제·반도체 측면에서 강력한 파트너지만, 북한이 핵으로 미 본토를 타격할 수 있어 동맹 리스크가 커졌다. 주한미군 감축을 검토하되, 그럴 경우 한국의 핵무장 유인이 높아질 것이므로 재래식 전력 강화와 NPT 이탈 시 발생할 문제점 등을 경고하며 관리해야 한다.",
      "key_insight": "필리핀·한국 동맹은 효용 대비 위험을 재평가해야 한다."
    },
    {
      "section_title": "BANG FOR YOUR BUCK",
      "section_title_ko": "가성비 계산하기",
      "summary": "일본은 디지털 기술·핵심 광물 공급망·국방비 증액·외교적 영향력 덕분에 21세기에 더 가치 있는 동맹이 됐다. 호주는 위험이 비교적 낮고, 핵심 광물과 중국 미사일 사거리 밖의 지리적 이점이 있어 AUKUS에 추가 투자가 필요하다. 유럽 동맹은 잠재력이 크지만 대중 노선에 대한 일관된 입장을 보이지 못하고 있다.",
      "key_insight": "일본·호주·유럽은 유지 또는 강화가 필요한 고가치 동맹이다."
    },
    {
      "section_title": "TIME FOR AN AUDIT",
      "section_title_ko": "심사 시간",
      "summary": "미국은 동맹들을 전 세계 관점에서 평가할 전담 조직을 만들어 비용·위험·국내 지지에 대해 평가해야 한다. 의회는 주요 조약 의무의 연례 순 평가를 요구하고, 미국은 방위 개입 조건과 상호 의무를 더 분명히 해야 한다.",
      "key_insight": "목표는 냉전 동맹에 대한 그리움이 아니라 미래 도전에 맞춘 맞춤형 동맹이다."
    }
  ],
  "key_points": [
    "중국·러시아·핵무장 북한으로 동맹 필요성과 전쟁 연루 위험이 동시에 상승",
    "필리핀 동맹은 남중국해 분쟁으로 전략적 이익 대비 위험이 과도할 수 있음",
    "북한 핵 능력으로 한반도 위기 시 미국 본토가 직접 위협받을 수 있음",
    "일본은 디지털 기술·국방비 증액·외교적 영향력으로 고가치 동맹"
  ],
  "geopolitical_implication": "미국은 동맹을 자동 유지가 아닌 전략적 선택의 대상으로 만들어, 비용·위험을 연례 평가하고 미래 도전에 맞게 재조정해야 한다."
}
</example>

[예시에서 배울 점]
- section_title: 원문의 영문 소제목 그대로 (번역/재해석 금지)
- section_title_ko: 한글 번역
- section_analysis: 반드시 원문 순서대로 (순서 변경 금지)
- summary: 해당 섹션의 핵심 내용 3~5문장
- key_insight: 한 줄로 핵심 인사이트
- key_points: 구체적 사실/수치 포함
- 전체 900자 이상

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
            'temperature' => (float)($this->config['temperature'] ?? 0.2),
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
