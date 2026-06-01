<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;
use PDO;

/**
 * Weekly Gist — the gist 기사 기반 주간 브리핑 (Admin / Strategic Hub)
 */
class WeeklyGistService
{
    private PDO $pdo;
    private OpenAIService $openai;
    private NarrativeDepthService $depth;
    /** @var array<string, mixed> */
    private array $depthConfig;

    public function __construct(
        PDO $pdo,
        ?OpenAIService $openai = null,
        ?NarrativeDepthService $depth = null
    ) {
        $this->pdo = $pdo;
        $projectRoot = dirname(__DIR__, 3) . '/';
        $openaiConfig = require $projectRoot . 'config/openai.php';
        $this->openai = $openai ?? new OpenAIService($openaiConfig);
        $this->depth = $depth ?? new NarrativeDepthService($this->openai);
        $depthPath = $projectRoot . 'config/narrative_depth.php';
        $this->depthConfig = is_file($depthPath) ? require $depthPath : [];
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `weekly_gist_reports` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `period_start` DATE NOT NULL,
              `period_end` DATE NOT NULL,
              `headline` VARCHAR(500) NULL,
              `gist_json` LONGTEXT NOT NULL,
              `article_ids_json` TEXT NULL,
              `article_titles_json` TEXT NULL,
              `article_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_wg_created` (`created_at` DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return array{articles: list<array<string, mixed>>, period: array{start: string, end: string}, count: int}
     */
    public function fetchArticlesForPeriod(string $start, string $end): array
    {
        $columns = [
            'id', 'title', 'description', 'source', 'category_parent', 'category',
            'published_at', 'created_at',
        ];
        $optionalCols = ['why_important', 'narration', 'future_prediction', 'original_title'];
        $existing = $this->pdo->query('SHOW COLUMNS FROM news')->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($optionalCols as $col) {
            if (in_array($col, $existing, true)) {
                $columns[] = $col;
            }
        }

        $colStr = implode(', ', $columns);
        $statusCond = in_array('status', $existing, true)
            ? "(status = 'published' OR status IS NULL)"
            : '1=1';

        $sql = "SELECT {$colStr} FROM news
                WHERE {$statusCond}
                  AND COALESCE(published_at, created_at) BETWEEN :start AND :end
                ORDER BY COALESCE(published_at, created_at) DESC
                LIMIT 100";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
        $articles = $stmt->fetchAll() ?: [];

        $supabase = new SupabaseService([]);
        if ($supabase->isConfigured() && count($articles) > 0) {
            $idMap = [];
            foreach (array_column($articles, 'id') as $nid) {
                $rows = $supabase->select(
                    'analysis_embeddings',
                    'news_id=eq.' . (int) $nid . '&select=news_id,chunk_text,metadata&order=created_at.asc&limit=3',
                    3
                );
                if (!$rows || count($rows) === 0) {
                    continue;
                }
                $meta = $rows[0]['metadata'] ?? [];
                $chunkTexts = [];
                foreach ($rows as $row) {
                    $ct = trim((string) ($row['chunk_text'] ?? ''));
                    if ($ct !== '') {
                        $chunkTexts[] = mb_substr($ct, 0, 600);
                    }
                }
                if (is_array($meta)) {
                    $idMap[(int) $nid] = [
                        'topic_label' => $meta['topic_label'] ?? '',
                        'topic_category' => $meta['topic_category'] ?? '',
                        'entities' => $meta['entities'] ?? [],
                        'region' => $meta['region'] ?? [],
                        'chunk_text' => implode("\n", $chunkTexts),
                    ];
                }
            }
            foreach ($articles as &$art) {
                $art['rag_metadata'] = $idMap[(int) $art['id']] ?? null;
            }
            unset($art);
        }

        return [
            'articles' => $articles,
            'period' => ['start' => $start, 'end' => $end],
            'count' => count($articles),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReports(int $limit = 50): array
    {
        $this->ensureTable();
        $limit = min(100, max(1, $limit));
        $stmt = $this->pdo->query(
            'SELECT id, period_start, period_end, headline, article_count, created_at, updated_at
             FROM weekly_gist_reports ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getReportDetail(int $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM weekly_gist_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $gist = json_decode($row['gist_json'] ?? 'null', true);
        $titles = json_decode($row['article_titles_json'] ?? '{}', true);
        if (!is_array($titles)) {
            $titles = [];
        }

        return [
            'id' => (int) $row['id'],
            'period_start' => $row['period_start'],
            'period_end' => $row['period_end'],
            'headline_row' => $row['headline'],
            'gist' => $gist,
            'article_titles_map' => $titles,
            'article_ids' => json_decode($row['article_ids_json'] ?? '[]', true),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    public function updateGist(int $id, array $gist): bool
    {
        $this->ensureTable();
        $headline = mb_substr((string) ($gist['headline'] ?? ''), 0, 500);
        $json = json_encode($gist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('JSON 인코딩 실패');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE weekly_gist_reports SET gist_json = ?, headline = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$json, $headline, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @return array{gist: array<string, mixed>, saved_id: int|null, save_error: string|null}
     */
    public function generate(string $startDate, string $endDate, array $articles): array
    {
        if (!$this->openai->isConfigured()) {
            throw new \RuntimeException('OpenAI가 설정되지 않았습니다.');
        }
        if ($articles === []) {
            throw new \InvalidArgumentException('articles 배열이 비어 있습니다.');
        }

        $articleInputs = $this->buildArticleInputs($articles);
        $articleCount = count($articleInputs);
        $userPrompt = $this->buildGeneratePrompt($startDate, $endDate, $articleInputs, $articleCount);

        $systemPrompt = <<<'SYSTEM'
당신은 "The Gist"의 수석 에디터입니다.
의사결정자를 위한 주간 인텔리전스 브리핑을 작성합니다.
출력은 반드시 유효한 JSON으로만 응답하세요. 설명이나 마크다운 없이 JSON만 출력합니다.
SYSTEM;

        $gistContextBlocks = [];
        foreach ($articleInputs as $entry) {
            $block = '【' . ($entry['title'] ?? '') . "】\n";
            if (!empty($entry['why_important'])) {
                $block .= '핵심: ' . $entry['why_important'] . "\n";
            }
            $block .= '분석: ' . ($entry['narration'] ?? $entry['description'] ?? '');
            $gistContextBlocks[] = $block;
        }
        $gistContext = implode("\n\n", $gistContextBlocks);
        $topicLine = "주제: \"{$startDate} ~ {$endDate} 주간 인텔리전스 브리핑\"";

        $model = (string) ($this->depthConfig['model'] ?? 'gpt-5.2');
        $temperature = (float) ($this->depthConfig['temperature'] ?? 0.5);
        $maxTokens = (int) ($this->depthConfig['max_tokens']['weekly'] ?? 12000);

        $gistData = null;
        $depthMeta = ['attempts' => 0, 'depth_passed' => false];
        $currentPrompt = $userPrompt;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $depthMeta['attempts'] = $attempt;
            $outputText = $this->openai->chat($systemPrompt, $currentPrompt, [
                'json_mode' => true,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'timeout' => 240,
            ]);
            $parsed = json_decode($outputText, true);
            if (!$parsed) {
                throw new \RuntimeException('GPT 응답이 유효한 JSON이 아닙니다: ' . mb_substr($outputText, 0, 500));
            }
            $gistData = $parsed;

            $syn = trim((string) ($gistData['synthesis_narrative'] ?? ''));
            $minSyn = (int) ($this->depthConfig['min_chars']['synthesis_narrative'] ?? 1200);
            if ($syn === '' || mb_strlen($syn) < $minSyn) {
                $generated = $this->depth->generateSynthesisNarrative($gistContext, $topicLine);
                if ($generated !== null && $generated !== '') {
                    $gistData['synthesis_narrative'] = $generated;
                }
            }

            $depthResult = $this->depth->scoreGistDepth($gistData);
            $depthMeta['depth_score'] = $depthResult['depth_score'] ?? 0;
            $depthMeta['depth_passed'] = (bool) ($depthResult['passed'] ?? false);
            $depthMeta['depth_violations'] = $depthResult['violations'] ?? [];

            if ($depthMeta['depth_passed'] || $attempt >= 2) {
                break;
            }

            $hintLines = implode("\n- ", $depthResult['hints'] ?? ['분량을 검색 분석 수준으로 확장하라.']);
            $currentPrompt = $userPrompt . "\n\n【분량 미달 — 반드시 수정】\n- {$hintLines}\n- synthesis_narrative 1200자·3문단, cluster narrative 600자·3문단 이상";
        }

        if (!$gistData) {
            throw new \RuntimeException('Weekly gist generation failed');
        }

        $gistData['meta'] = array_merge($gistData['meta'] ?? [], [
            'generated_at' => date('c'),
            'total_articles' => $articleCount,
            'period' => "{$startDate} ~ {$endDate}",
            'model' => $model,
            'depth' => $depthMeta,
        ]);

        $savedId = null;
        $saveError = null;
        try {
            $savedId = $this->saveGeneratedReport($startDate, $endDate, $articles, $gistData);
        } catch (\Throwable $dbEx) {
            error_log('[WeeklyGistService] DB save failed: ' . $dbEx->getMessage());
            $saveError = $dbEx->getMessage();
        }

        return [
            'gist' => $gistData,
            'saved_id' => $savedId,
            'save_error' => $saveError,
        ];
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @return list<array<string, mixed>>
     */
    private function buildArticleInputs(array $articles): array
    {
        $articleInputs = [];
        foreach ($articles as $i => $art) {
            $entry = [
                'id' => $art['id'] ?? $i,
                'title' => $art['title'] ?? '',
                'source' => $art['source'] ?? '',
                'category_parent' => $art['category_parent'] ?? '',
                'description' => mb_substr((string) ($art['description'] ?? ''), 0, 300),
                'why_important' => mb_substr((string) ($art['why_important'] ?? ''), 0, 400),
                'future_prediction' => mb_substr((string) ($art['future_prediction'] ?? ''), 0, 300),
                'narration' => mb_substr((string) ($art['narration'] ?? ''), 0, 800),
            ];
            if (!empty($art['rag_metadata'])) {
                $rag = $art['rag_metadata'];
                $entry['rag_metadata'] = [
                    'topic_label' => $rag['topic_label'] ?? '',
                    'topic_category' => $rag['topic_category'] ?? '',
                    'entities' => $rag['entities'] ?? [],
                    'region' => $rag['region'] ?? [],
                ];
                $chunkText = trim((string) ($rag['chunk_text'] ?? ''));
                if ($chunkText !== '') {
                    $entry['analysis_context'] = mb_substr($chunkText, 0, 800);
                }
            }
            $articleInputs[] = $entry;
        }

        return $articleInputs;
    }

    /**
     * @param list<array<string, mixed>> $articleInputs
     */
    private function buildGeneratePrompt(
        string $startDate,
        string $endDate,
        array $articleInputs,
        int $articleCount
    ): string {
        $articlesJson = json_encode($articleInputs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
##############################################################
# The Gist 위클리 브리핑 생성 가이드
##############################################################

[역할]
이번 주({$startDate} ~ {$endDate}) 발행된 기사 {$articleCount}개를
종합하여, 의사결정자를 위한 **주간 인텔리전스 브리핑**을 작성합니다.

[절대 규칙]
1. 기사별 요약 나열 금지. 이것은 뉴스레터가 아니라 분석 브리핑이다.
2. 개별 기사를 인용하되, 반드시 다른 기사와의 관계 속에서 언급할 것.
3. 서로 충돌하는 관점이 있으면 반드시 대비하여 정리할 것.
4. 각 이슈의 마지막은 "그래서 뭐냐(So What)"로 끝낼 것.
5. 출력은 반드시 아래 JSON 스키마를 따를 것.

[입력 데이터 레이어 구조]
- description: 기사 원문 요약 (Layer 1 — 현실)
- why_important, narration, future_prediction: 1차 AI 분석 (Layer 2 — 해석)
- rag_metadata: 구조화 힌트 (Layer 3 — 분류)
- analysis_context: 임베딩 원본 분석 텍스트 (Layer 1.5 — 원문 근거)
  이 필드가 있는 기사는 1차 해석의 근거를 직접 확인할 수 있다.
  1차 해석과 analysis_context가 다르면, analysis_context를 우선하라.

[메타인지 규칙 — 입력 데이터의 한계를 인식하라]
입력의 why_important, narration, future_prediction은
개별 기사에 대한 1차 AI 분석 결과다. 즉, 이미 해석이 들어가 있다.

따라서:
1. 이것들을 그대로 정리하면 "요약의 요약"이 된다. 이건 실패다.
2. 서로 다른 기사의 1차 해석이 같은 방향을 가리키면,
   "왜 같은 결론인가"를 물어라. 근거가 같은가? 다른가?
3. 서로 다른 기사의 1차 해석이 충돌하면,
   어느 쪽이 더 강한 근거를 가지는지 판단하라.
4. 1차 해석 어디에도 없는 새로운 연결(cross-insight)을
   반드시 1개 이상 생성하라.

[RAG 메타데이터 사용 규칙]
topic_label, topic_category, entities, region은
클러스터링을 돕기 위한 "힌트"일 뿐이다.

1. topic_label이 같아도, 반드시 실제 내용 기반으로 재검증하라
2. topic_label이 달라도, 인과관계가 있으면 같은 흐름으로 묶어라
3. entities가 다르면 → 관점 차이로 해석하라
4. RAG 메타데이터와 기사 내용이 충돌하면 → 기사 내용을 우선하라
이 데이터는 "정답"이 아니라 "초기 가설"이다.

[프로세스]
Step 1 — 클러스터링:
  입력 기사들을 이슈별로 3~5개 클러스터로 묶어라.
  같은 사건, 같은 정책, 같은 시장을 다루면 같은 클러스터다.

Step 2 — 관점 통합:
  각 클러스터 내에서:
  - 기사들이 같은 방향이면: "일관되게 X를 가리킨다"
  - 기사들이 다른 방향이면: "A는 X라 하고, B는 Y라 한다. 차이의 근거는 Z"

Step 3 — 교차 연결:
  클러스터 간에 인과관계가 있으면 짚어라.
  예: "반도체 규제(이슈1) → 공급망 재편(이슈2) → 에너지 투자 변화(이슈3)"

Step 4 — So What:
  이번 주 전체를 관통하는 한 줄 메시지.

[so_what 품질 규칙]
- implication: "증가하고 있다", "중요하다", "영향을 준다" 같은 모호한 표현 금지.
  반드시 구조적 변화의 방향을 명시하라.
- why_it_matters: 반드시 "A → B → C → D" 인과 체인 형태로 작성.
  1~2단계 인과 금지. 최소 3단계("X → Y → Z → W").
- what_to_watch: 의견이 아닌 외부에서 추적 가능한 지표/사건만 기재.
  "상황 추이", "동향 변화" 같은 모호 표현 금지.

[action_hints 규칙]
- watch: "무엇을 어떻게 추적할 것인가"를 명시. "상황을 모니터링한다" 금지.
- consider: 직접적 행동 지시 금지. "재검토", "준비", "노출도 평가" 수준.
  "매수/매도", 특정 종목 언급 절대 금지.
- what_to_watch(클러스터)와 watch(action_hints)의 차이:
  what_to_watch = "어떤 신호를 볼 것인가" (지표)
  watch = "그것을 어떻게 추적할 것인가" (행동)

[출력 JSON 스키마]
{
  "headline": "이번 주 한 줄 (30자 이내)",
  "synthesis_narrative": "검색 클러스터 분석과 동일한 3단 평문 (한국어 존댓말, 최소 1200자·3문단, \\n\\n로 문단 구분). 1) 핵심 결론 2) 관점 비교 3) 향후 영향",
  "macro_so_what": "이번 주 전체를 관통하는 전략적 의미 (3~5문장, 200자 이상)",
  "clusters": [
    {
      "cluster_id": 1,
      "title": "클러스터 제목 (20자 이내)",
      "category": "diplomacy|economy|technology|energy|security",
      "priority_rank": 1,
      "impact_score": 4,
      "confidence": "high|medium|low",
      "one_line_takeaway": "이 이슈의 핵심 한 줄",
      "source_article_ids": [12, 45, 67],
      "narrative": "관점 통합 서술 (600~1200자, 3~5문단, \\n\\n로 문단 구분)",
      "perspectives": [
        {
          "viewpoint": "관점 A 요약",
          "source": "기사 제목 또는 매체명",
          "difference_reason": "이 관점의 근거"
        },
        {
          "viewpoint": "관점 B 요약",
          "source": "기사 제목 또는 매체명",
          "difference_reason": "이 관점의 근거"
        }
      ],
      "so_what": {
        "implication": "무슨 구조적 변화가 일어나고 있는가 (2~3문장, 방향성 명시)",
        "why_it_matters": "인과 체인: A → B → C → D (최소 3단계)",
        "what_to_watch": ["추적할 외부 신호/지표 1", "신호 2"]
      }
    }
  ],
  "cross_connections": [
    {
      "from_cluster": 1,
      "to_cluster": 3,
      "relationship": "이슈1이 이슈3에 미치는 영향 설명"
    }
  ],
  "next_week_watch": ["다음 주 주목 포인트 1", "다음 주 주목 포인트 2"],
  "action_hints": {
    "watch": ["무엇을 어떻게 모니터링할 것인가 (구체적 행동)"],
    "consider": ["무엇을 재검토/준비할 것인가 (의사결정 준비)"]
  },
  "meta": {
    "total_articles": {$articleCount},
    "period": "{$startDate} ~ {$endDate}"
  }
}

[금지 패턴 — 이렇게 나오면 실패]
- "이번 주 A 기사에서는 X, B 기사에서는 Y, C 기사에서는 Z" (나열)
- 모든 클러스터의 impact_score가 동일
- perspectives가 1개뿐인 클러스터 (최소 2개 관점)
- so_what.implication이 narrative의 반복
- so_what.why_it_matters가 1단계 인과 ("X가 Y에 영향")
- so_what.what_to_watch에 "상황 추이", "동향 변화" 등 모호 표현
- action_hints.watch에 "상황을 모니터링한다" 같은 비구체적 표현

[성공 패턴 — 이렇게 나와야 함]
- "3개 매체가 일관되게 X를 경고하지만, 근거가 다르다: A는 수치 기반, B는 정치적 맥락, C는 역사적 선례"
- cluster 간 cross_connection으로 인과 사슬 제시
- so_what.implication: "에너지 공급 리스크가 가격이 아닌 물량 안정성 중심으로 이동"
- so_what.why_it_matters: "공급 불확실성 확대 → 유동성 프리미엄 상승 → 기업 자금조달 비용 증가"
- so_what.what_to_watch: ["호르무즈 해협 통과 선박 지연 일수", "아시아 국가의 러시아산 원유 계약 증가 여부"]
- action_hints.watch: ["호르무즈 지연 5일 이상 지속 여부 추적"]
- action_hints.consider: ["장기 LNG 계약 비중 재검토 준비"]

##############################################################
# 입력 기사 목록
##############################################################
{$articlesJson}
PROMPT;
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @param array<string, mixed> $gistData
     */
    private function saveGeneratedReport(
        string $startDate,
        string $endDate,
        array $articles,
        array $gistData
    ): int {
        $this->ensureTable();
        $articleIds = [];
        $titleMap = [];
        foreach ($articles as $art) {
            $aid = isset($art['id']) ? (int) $art['id'] : 0;
            if ($aid > 0) {
                $articleIds[] = $aid;
                $titleMap[$aid] = (string) ($art['title'] ?? '');
            }
        }
        $headlineDb = mb_substr((string) ($gistData['headline'] ?? ''), 0, 500);
        $gistJson = json_encode($gistData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ins = $this->pdo->prepare(
            'INSERT INTO weekly_gist_reports
            (period_start, period_end, headline, gist_json, article_ids_json, article_titles_json, article_count)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $startDate,
            $endDate,
            $headlineDb,
            $gistJson,
            json_encode($articleIds, JSON_UNESCAPED_UNICODE),
            json_encode($titleMap, JSON_UNESCAPED_UNICODE),
            count($articleIds),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
