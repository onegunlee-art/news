<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use Agents\Services\RAGService;
use App\Config\StrategicReportSchema;
use PDO;

class StrategicReportService
{
    private PDO $pdo;
    private OpenAIService $openai;
    private IntelligenceEmbeddingService $embedder;
    private ?RAGService $rag;
    private array $config;

    private const SIMILARITY_THRESHOLD = 0.55;
    private const MAX_EXTERNAL_PER_ANCHOR = 5;
    private const CONFIDENCE_THRESHOLD = 0.6;
    private const MAX_SELF_CORRECTION = 1;

    public function __construct(PDO $pdo, ?OpenAIService $openai = null, ?IntelligenceEmbeddingService $embedder = null, ?RAGService $rag = null)
    {
        $this->pdo = $pdo;
        $this->openai = $openai ?? new OpenAIService([]);
        $this->embedder = $embedder ?? new IntelligenceEmbeddingService();
        $this->rag = $rag;
        $configPath = dirname(__DIR__, 3) . '/config/strategic_report.php';
        $this->config = is_file($configPath) ? require $configPath : [];
    }

    public function generateForWeek(?string $reportWeek = null): array
    {
        [$week, $start, $end] = $this->resolveWeek($reportWeek);

        $gistAnchors = $this->fetchGistAnchors($start, $end);
        $matchResult = $this->matchExternalArticles($gistAnchors);

        $articles = $matchResult['matched'];
        $usedFallback = false;

        if ($articles === [] && $gistAnchors === []) {
            $articles = $this->fetchWeekArticles($start, $end);
            if ($articles === []) {
                $articles = $this->fetchRecentArticles(14);
                $usedFallback = $articles !== [];
            }
            $matchResult = ['matched' => $articles, 'dropped' => 0, 'total_external' => count($articles)];
        }

        if ($articles === [] && $gistAnchors === []) {
            return [
                'success' => false,
                'error' => 'No gist anchors or intelligence articles for period',
                'period' => ['report_week' => $week, 'start' => $start, 'end' => $end],
            ];
        }

        $context = $this->buildContextWithAnchors($gistAnchors, $articles);
        $totalSources = count($gistAnchors) + count($articles);

        $scqa = $this->generateScqa($context, $start, $end, $totalSources);
        if ($scqa === null) {
            return ['success' => false, 'error' => 'SCQA generation failed'];
        }
        $scqa = $this->normalizeScqa($scqa);

        $verification = $this->verifyScqa($scqa, $gistAnchors, $articles, $matchResult);
        $correctionAttempts = 0;

        if ($verification['confidence_score'] < self::CONFIDENCE_THRESHOLD && $correctionAttempts < self::MAX_SELF_CORRECTION) {
            $correctionAttempts++;
            $correctionHints = $this->buildCorrectionHints($verification);
            $correctedScqa = $this->generateScqaWithCorrection($context, $start, $end, $totalSources, $scqa, $correctionHints);
            if ($correctedScqa !== null) {
                $scqa = $this->normalizeScqa($correctedScqa);
                $verification = $this->verifyScqa($scqa, $gistAnchors, $articles, $matchResult);
                $verification['self_correction_applied'] = true;
                $verification['correction_hints_used'] = $correctionHints;
            }
        }
        $verification['correction_attempts'] = $correctionAttempts;

        $meta = $this->buildMetaWithAnchors($gistAnchors, $articles, $scqa, $matchResult);
        $meta['verification'] = $verification;
        if ($usedFallback) {
            $meta['period_fallback'] = 'last_14_days';
        }

        if ($verification['confidence_score'] < self::CONFIDENCE_THRESHOLD) {
            $meta['low_confidence_warning'] = '이 레포트의 신뢰도가 낮습니다. 추가 검토가 필요합니다.';
        }

        $reportId = $this->saveReportWithAnchors($week, $start, $end, $scqa, $gistAnchors, $articles, $meta);
        return ['success' => true, 'report_id' => $reportId, 'report_week' => $week, 'scqa' => $scqa, 'meta' => $meta];
    }

    /**
     * SCQA 결과 검증 (KEEP 설계: source_diversity, entity_overlap, avg_similarity, critique_grounding)
     */
    private function verifyScqa(array $scqa, array $gistAnchors, array $matchedExternal, array $matchResult): array
    {
        $totalAnchors = count($gistAnchors);
        $totalMatched = count($matchedExternal);
        $total = max(1, $totalAnchors + $totalMatched);

        $sourceCount = ['gist' => $totalAnchors];
        foreach ($matchedExternal as $article) {
            $src = (string) ($article['source_api'] ?? 'unknown');
            $sourceCount[$src] = ($sourceCount[$src] ?? 0) + 1;
        }
        $uniqueSources = count(array_filter($sourceCount, fn($c) => $c > 0));
        $sourceDiversity = round($uniqueSources / $total, 3);

        $similarities = array_filter(array_map(fn($a) => (float) ($a['_match_similarity'] ?? 0), $matchedExternal));
        $avgSimilarity = $similarities !== [] ? round(array_sum($similarities) / count($similarities), 3) : 0;

        $entityOverlap = $this->calculateEntityOverlap($gistAnchors, $matchedExternal);

        $critiqueGrounding = $this->checkCritiqueGrounding($scqa);

        $perspectives = count($scqa['complication']['perspectives'] ?? []);
        $collisions = count($scqa['complication']['narrative_collisions'] ?? []);
        $hasShift = trim((string) ($scqa['structural_shift']['headline'] ?? '')) !== '';

        $confidenceScore = $this->calculateConfidenceScore(
            $sourceDiversity,
            $avgSimilarity,
            $entityOverlap,
            $critiqueGrounding,
            $perspectives,
            $collisions,
            $hasShift,
            $totalAnchors,
            $totalMatched
        );

        return [
            'source_diversity' => $sourceDiversity,
            'avg_similarity' => $avgSimilarity,
            'entity_overlap' => $entityOverlap,
            'critique_grounding' => $critiqueGrounding,
            'perspective_count' => $perspectives,
            'collision_count' => $collisions,
            'has_structural_shift' => $hasShift,
            'confidence_score' => $confidenceScore,
            'confidence_label' => $confidenceScore >= 0.7 ? 'high' : ($confidenceScore >= self::CONFIDENCE_THRESHOLD ? 'medium' : 'low'),
            'self_correction_applied' => false,
            'correction_hints_used' => [],
        ];
    }

    private function calculateEntityOverlap(array $gistAnchors, array $matchedExternal): float
    {
        $gistEntities = [];
        foreach ($gistAnchors as $anchor) {
            $title = (string) ($anchor['title'] ?? '');
            $narration = (string) ($anchor['narration'] ?? '');
            preg_match_all('/[A-Z가-힣][a-z가-힣]+(?:\s+[A-Z가-힣][a-z가-힣]+)*/u', $title . ' ' . $narration, $matches);
            foreach ($matches[0] as $entity) {
                if (mb_strlen($entity) >= 2) {
                    $gistEntities[mb_strtolower($entity)] = true;
                }
            }
        }

        if ($gistEntities === []) {
            return 0.0;
        }

        $overlapCount = 0;
        $externalTotal = 0;
        foreach ($matchedExternal as $article) {
            $title = (string) ($article['title'] ?? '');
            $text = (string) ($article['clean_text'] ?? $article['description'] ?? '');
            preg_match_all('/[A-Z가-힣][a-z가-힣]+(?:\s+[A-Z가-힣][a-z가-힣]+)*/u', $title . ' ' . $text, $matches);
            foreach ($matches[0] as $entity) {
                $externalTotal++;
                if (isset($gistEntities[mb_strtolower($entity)])) {
                    $overlapCount++;
                }
            }
        }

        return $externalTotal > 0 ? round($overlapCount / $externalTotal, 3) : 0.0;
    }

    private function checkCritiqueGrounding(array $scqa): array
    {
        if ($this->rag === null || !$this->rag->isConfigured()) {
            return ['available' => false, 'matches' => 0];
        }

        $searchText = ($scqa['executive_summary'] ?? '') . ' ' . ($scqa['structural_shift']['headline'] ?? '');
        $searchText = trim($searchText);
        if ($searchText === '') {
            return ['available' => true, 'matches' => 0];
        }

        try {
            $context = $this->rag->retrieveRelevantContext($searchText, 5);
            $critiqueMatches = count($context['critiques'] ?? []);
            return ['available' => true, 'matches' => $critiqueMatches];
        } catch (\Throwable $e) {
            return ['available' => false, 'matches' => 0, 'error' => $e->getMessage()];
        }
    }

    private function calculateConfidenceScore(
        float $sourceDiversity,
        float $avgSimilarity,
        float $entityOverlap,
        array $critiqueGrounding,
        int $perspectives,
        int $collisions,
        bool $hasShift,
        int $anchors,
        int $matched
    ): float {
        $score = 0.0;

        $score += min($sourceDiversity, 1.0) * 0.15;
        $score += min($avgSimilarity, 1.0) * 0.15;
        $score += min($entityOverlap, 1.0) * 0.10;

        if ($critiqueGrounding['available'] && ($critiqueGrounding['matches'] ?? 0) > 0) {
            $score += 0.10;
        }

        $score += min($perspectives / 3, 1.0) * 0.15;
        $score += min($collisions / 2, 1.0) * 0.15;
        $score += $hasShift ? 0.10 : 0.0;

        if ($anchors >= 2) {
            $score += 0.05;
        }
        if ($matched >= 3) {
            $score += 0.05;
        }

        return round(min($score, 1.0), 3);
    }

    private function buildCorrectionHints(array $verification): array
    {
        $hints = [];

        if ($verification['perspective_count'] < 2) {
            $hints[] = 'perspectives를 최소 2개 이상으로 늘려라. 서로 다른 관점의 출처를 명시하라.';
        }
        if ($verification['collision_count'] < 2) {
            $hints[] = 'narrative_collisions를 최소 2개 이상으로 늘려라. 서로 충돌하는 관점을 구체화하라.';
        }
        if (!$verification['has_structural_shift']) {
            $hints[] = 'structural_shift.headline을 반드시 작성하라. 단순 사건이 아닌 구조적 변화를 포착하라.';
        }
        if ($verification['source_diversity'] < 0.3) {
            $hints[] = '다양한 출처를 균형 있게 인용하라.';
        }
        if ($verification['avg_similarity'] < 0.5) {
            $hints[] = '제공된 context와 더 밀접하게 연결된 분석을 작성하라.';
        }

        return $hints;
    }

    private function generateScqaWithCorrection(string $context, string $start, string $end, int $count, array $previousScqa, array $hints): ?array
    {
        if (!$this->openai->isConfigured() || $hints === []) {
            return null;
        }

        $system = (string) ($this->config['system_prompt'] ?? 'Output valid JSON in Korean.');
        $schema = StrategicReportSchema::jsonSchemaDescription();
        $hintsText = implode("\n- ", $hints);
        $previousJson = json_encode($previousScqa, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $user = <<<PROMPT
{$start}부터 {$end}까지의 주간 지정학·국제정세 전략 레포트를 **수정**하라.
아래 이전 초안과 개선 지침을 참고하여 품질을 높여라.

【개선 지침】
- {$hintsText}

【이전 초안】
{$previousJson}

【규칙】
- timeline·perspectives·collisions에 source_id/source_ids 필수
- meta.language = "ko"
- JSON만 출력

스키마:
{$schema}

Context ({$count} articles):
{$context}
PROMPT;

        try {
            $raw = $this->openai->chat($system, $user, [
                'model' => (string) ($this->config['model'] ?? 'gpt-4o'),
                'temperature' => (float) ($this->config['temperature'] ?? 0.45),
                'max_tokens' => (int) ($this->config['max_tokens'] ?? 5000),
                'json_mode' => true,
                'timeout' => 180,
            ]);
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            error_log('StrategicReportService self-correction: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * gist 앵커: news 테이블에서 발행된 diplomacy 기사 SELECT (읽기 전용)
     */
    private function fetchGistAnchors(string $start, string $end): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, title, narration, description, published_at, category, category_parent, url
                 FROM news
                 WHERE status = 'published'
                   AND category_parent = 'diplomacy'
                   AND published_at BETWEEN :start AND :end
                   AND narration IS NOT NULL AND CHAR_LENGTH(narration) > 100
                 ORDER BY published_at DESC
                 LIMIT 10"
            );
            $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log('StrategicReportService fetchGistAnchors error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * gist 앵커의 narration으로 intelligence_embeddings에서 유사한 외부 기사 매칭
     * @return array{matched: array, dropped: int, total_external: int, by_anchor: array}
     */
    private function matchExternalArticles(array $gistAnchors): array
    {
        if ($gistAnchors === [] || !$this->embedder->isConfigured()) {
            return ['matched' => [], 'dropped' => 0, 'total_external' => 0, 'by_anchor' => []];
        }

        $allMatched = [];
        $seenArticleIds = [];
        $byAnchor = [];
        $totalExternal = 0;
        $dropped = 0;

        foreach ($gistAnchors as $anchor) {
            $narration = trim((string) ($anchor['narration'] ?? ''));
            if ($narration === '') {
                continue;
            }

            $searchText = mb_substr($narration, 0, 2000);
            $hits = $this->embedder->search($searchText, [
                'match_count' => self::MAX_EXTERNAL_PER_ANCHOR * 2,
                'min_relevance' => 50,
            ]);

            $anchorMatches = [];
            foreach ($hits as $hit) {
                $articleId = (int) ($hit['article_id'] ?? 0);
                $similarity = (float) ($hit['semantic_similarity'] ?? $hit['final_score'] ?? 0);
                $totalExternal++;

                if ($similarity < self::SIMILARITY_THRESHOLD) {
                    $dropped++;
                    continue;
                }
                if (isset($seenArticleIds[$articleId])) {
                    continue;
                }
                if (count($anchorMatches) >= self::MAX_EXTERNAL_PER_ANCHOR) {
                    continue;
                }

                $seenArticleIds[$articleId] = true;
                $articleData = $this->fetchIntelligenceArticle($articleId);
                if ($articleData !== null) {
                    $articleData['_match_similarity'] = $similarity;
                    $articleData['_matched_by_gist_id'] = (int) $anchor['id'];
                    $allMatched[] = $articleData;
                    $anchorMatches[] = $articleId;
                }
            }
            $byAnchor[(int) $anchor['id']] = $anchorMatches;
        }

        usort($allMatched, fn($a, $b) => ($b['_match_similarity'] ?? 0) <=> ($a['_match_similarity'] ?? 0));

        return [
            'matched' => $allMatched,
            'dropped' => $dropped,
            'total_external' => $totalExternal,
            'by_anchor' => $byAnchor,
        ];
    }

    private function fetchIntelligenceArticle(int $articleId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM intelligence_source_items WHERE id = :id AND embed_status = 'done' LIMIT 1"
        );
        $stmt->execute(['id' => $articleId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function resolveWeek(?string $reportWeek): array
    {
        if ($reportWeek) {
            $year = (int) substr($reportWeek, 0, 4);
            $weekNum = (int) ltrim(substr($reportWeek, 5), 'W');
            $dto = new \DateTime();
            $dto->setISODate($year, $weekNum);
            $start = (clone $dto)->modify('monday this week')->format('Y-m-d');
            $end = (clone $dto)->modify('sunday this week')->format('Y-m-d');
            return [$reportWeek, $start, $end];
        }
        $dto = new \DateTime('today');
        $start = (clone $dto)->modify('monday this week')->format('Y-m-d');
        $end = (clone $dto)->modify('sunday this week')->format('Y-m-d');
        $week = (clone $dto)->format('o-\WW');
        return [$week, $start, $end];
    }

    private function fetchWeekArticles(string $start, string $end): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM intelligence_source_items
             WHERE embed_status = 'done'
               AND duplicate_of IS NULL
               AND published_at BETWEEN :start AND :end
             ORDER BY FIELD(source_api, 'nyt', 'guardian', 'rss'), relevance_score DESC, published_at DESC
             LIMIT 40"
        );
        $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
        return $stmt->fetchAll() ?: [];
    }

    private function fetchRecentArticles(int $days, int $limit = 40): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM intelligence_source_items
             WHERE embed_status = 'done'
               AND duplicate_of IS NULL
               AND published_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             ORDER BY FIELD(source_api, 'nyt', 'guardian', 'rss'), relevance_score DESC, published_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * gist 앵커 + 매칭된 외부 기사로 컨텍스트 구성 (gist = 타임라인 뼈대, 외부 = 교차 시각)
     */
    private function buildContextWithAnchors(array $gistAnchors, array $matchedExternal): string
    {
        $blocks = [];

        if ($gistAnchors !== []) {
            $blocks[] = "=== the gist 앵커 기사 (타임라인 뼈대 + 관점 1 — 사실 근거로 사용) ===";
            foreach ($gistAnchors as $anchor) {
                $blocks[] = sprintf(
                    "[gist_id:%d published:%s] %s\n%s",
                    (int) $anchor['id'],
                    (string) ($anchor['published_at'] ?? ''),
                    (string) $anchor['title'],
                    mb_substr(strip_tags((string) ($anchor['narration'] ?? '')), 0, 1200)
                );
            }
        }

        if ($matchedExternal !== []) {
            $blocks[] = "\n=== 매칭된 외부 기사 (교차 시각 — NYT/Guardian/RSS) ===";
            foreach ($matchedExternal as $article) {
                $blocks[] = sprintf(
                    '[external_id:%d source:%s similarity:%.2f matched_by_gist:%d] %s\n%s',
                    (int) $article['id'],
                    (string) ($article['source_api'] ?? ''),
                    (float) ($article['_match_similarity'] ?? 0),
                    (int) ($article['_matched_by_gist_id'] ?? 0),
                    (string) $article['title'],
                    mb_substr((string) ($article['clean_text'] ?? $article['description'] ?? ''), 0, 800)
                );
            }
        }

        $styleSamples = $this->loadGistStyleSamples();
        if ($styleSamples !== '' && $gistAnchors === []) {
            $blocks[] = "\n=== the gist 문체 참고 (톤만 참고) ===";
            $blocks[] = $styleSamples;
        }

        return implode("\n\n", $blocks);
    }

    /** @deprecated Use buildContextWithAnchors for gist-anchor mode */
    private function buildContext(array $articles): string
    {
        $ragHits = $this->embedder->search('지정학 구조적 변화 무역 안보 외교 narrative', [
            'match_count' => 12,
            'min_relevance' => 55,
        ]);
        $blocks = ["=== Intelligence RAG (참고) ==="];
        foreach ($ragHits as $hit) {
            $blocks[] = sprintf('[article_id:%s score:%.3f] %s',
                (string) ($hit['article_id'] ?? ''),
                (float) ($hit['final_score'] ?? 0),
                mb_substr((string) ($hit['chunk_text'] ?? ''), 0, 600)
            );
        }
        $gistSamples = $this->loadGistStyleSamples();
        if ($gistSamples !== '') {
            $blocks[] = "\n=== the gist 기사 문체 참고 (출력 톤만 참고, 사실은 intelligence만 사용) ===";
            $blocks[] = $gistSamples;
        }
        $blocks[] = "\n=== Source Articles (intelligence_source_items) ===";
        foreach ($articles as $article) {
            $blocks[] = sprintf('[id:%d source:%s relevance:%d region:%s topic:%s] %s\n%s',
                (int) $article['id'],
                (string) $article['source_api'],
                (int) $article['relevance_score'],
                (string) ($article['region'] ?? ''),
                (string) ($article['topic'] ?? ''),
                (string) $article['title'],
                mb_substr((string) ($article['clean_text'] ?? $article['description'] ?? ''), 0, 900)
            );
        }
        return implode("\n\n", $blocks);
    }

    private function loadGistStyleSamples(): string
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT title, narration FROM news
                 WHERE status = 'published' AND narration IS NOT NULL AND CHAR_LENGTH(narration) > 200
                 ORDER BY published_at DESC LIMIT 2"
            );
            $rows = $stmt->fetchAll() ?: [];
            if ($rows === []) {
                return '';
            }
            $parts = [];
            foreach ($rows as $row) {
                $parts[] = '【' . mb_substr((string) $row['title'], 0, 60) . "】\n"
                    . mb_substr(strip_tags((string) $row['narration']), 0, 800);
            }
            return implode("\n\n---\n\n", $parts);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function generateScqa(string $context, string $start, string $end, int $count): ?array
    {
        if (!$this->openai->isConfigured()) {
            return null;
        }
        $system = (string) ($this->config['system_prompt'] ?? 'Output valid JSON in Korean.');
        $schema = StrategicReportSchema::jsonSchemaDescription();
        $user = <<<PROMPT
{$start}부터 {$end}까지의 주간 지정학·국제정세 전략 레포트를 작성하라.
아래 intelligence context만 근거로 사용한다.

【Phase 1 필수】
1. structural_shift: 사건이 아닌 '질서·패턴'의 변화
2. narrative_collisions: 최소 2개 — 서로 다른 현실을 믿는 관점의 충돌 (actor_a/view_a vs actor_b/view_b)
3. why_it_matters_chain: 인과 3단계 이상
4. 모든 텍스트 필드: 한국어, the gist 독자용

【규칙】
- timeline·perspectives·collisions에 source_id/source_ids 필수
- meta.language = "ko"
- JSON만 출력

스키마:
{$schema}

Context ({$count} articles):
{$context}
PROMPT;
        try {
            $raw = $this->openai->chat($system, $user, [
                'model' => (string) ($this->config['model'] ?? 'gpt-4o'),
                'temperature' => (float) ($this->config['temperature'] ?? 0.45),
                'max_tokens' => (int) ($this->config['max_tokens'] ?? 5000),
                'json_mode' => true,
                'timeout' => 180,
            ]);
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            error_log('StrategicReportService: ' . $e->getMessage());
            return null;
        }
    }

    /** @param array<string, mixed> $scqa */
    private function normalizeScqa(array $scqa): array
    {
        $scqa['meta'] = is_array($scqa['meta'] ?? null) ? $scqa['meta'] : [];
        $scqa['meta']['language'] = 'ko';
        if (!isset($scqa['structural_shift']) || !is_array($scqa['structural_shift'])) {
            $scqa['structural_shift'] = [
                'headline' => '',
                'from_pattern' => '',
                'to_pattern' => '',
                'why_now' => '',
                'evidence_source_ids' => [],
            ];
        }
        $comp = is_array($scqa['complication'] ?? null) ? $scqa['complication'] : [];
        if (!isset($comp['narrative_collisions']) || !is_array($comp['narrative_collisions'])) {
            $comp['narrative_collisions'] = [];
        }
        $scqa['complication'] = $comp;
        return $scqa;
    }

    /**
     * gist 앵커 + 매칭 외부 기반 메타 (anchor/matched/dropped 카운트 포함)
     */
    private function buildMetaWithAnchors(array $gistAnchors, array $matchedExternal, array $scqa, array $matchResult): array
    {
        $sourceCount = ['gist' => count($gistAnchors)];
        foreach ($matchedExternal as $article) {
            $src = (string) ($article['source_api'] ?? 'unknown');
            $sourceCount[$src] = ($sourceCount[$src] ?? 0) + 1;
        }

        $totalAnchors = count($gistAnchors);
        $totalMatched = count($matchedExternal);
        $totalDropped = (int) ($matchResult['dropped'] ?? 0);
        $totalExternal = (int) ($matchResult['total_external'] ?? 0);

        $uniqueSources = count(array_filter($sourceCount, fn($c) => $c > 0));
        $total = max(1, $totalAnchors + $totalMatched);
        $diversity = round($uniqueSources / $total, 2);

        $premium = ($sourceCount['nyt'] ?? 0) + ($sourceCount['guardian'] ?? 0);
        $premiumRatio = $total > 0 ? $premium / $total : 0;

        $perspectives = count($scqa['complication']['perspectives'] ?? []);
        $collisions = count($scqa['complication']['narrative_collisions'] ?? []);
        $hasShift = trim((string) ($scqa['structural_shift']['headline'] ?? '')) !== '';

        $confidence = 'medium';
        if ($totalAnchors >= 2 && $totalMatched >= 3 && $collisions >= 2 && $hasShift) {
            $confidence = 'high';
        } elseif ($totalAnchors === 0 || ($totalMatched === 0 && $perspectives < 2)) {
            $confidence = 'low';
        }
        if ($premiumRatio >= 0.3 && $collisions >= 2) {
            $confidence = $confidence === 'low' ? 'medium' : $confidence;
        }

        return [
            'source_count' => $sourceCount,
            'source_diversity' => $diversity,
            'gist_anchor_count' => $totalAnchors,
            'matched_external_count' => $totalMatched,
            'dropped_unrelated_count' => $totalDropped,
            'total_external_searched' => $totalExternal,
            'perspective_count' => $perspectives,
            'narrative_collision_count' => $collisions,
            'has_structural_shift' => $hasShift,
            'confidence' => $confidence,
            'article_total' => $total,
            'language' => 'ko',
        ];
    }

    /** @deprecated Use buildMetaWithAnchors for gist-anchor mode */
    private function buildMeta(array $articles, array $scqa): array
    {
        $sourceCount = [];
        foreach ($articles as $article) {
            $src = (string) ($article['source_api'] ?? 'unknown');
            $sourceCount[$src] = ($sourceCount[$src] ?? 0) + 1;
        }
        $uniqueSources = count($sourceCount);
        $total = max(1, count($articles));
        $diversity = round($uniqueSources / $total, 2);
        $premium = ($sourceCount['nyt'] ?? 0) + ($sourceCount['guardian'] ?? 0);
        $premiumRatio = $premium / $total;
        $perspectives = count($scqa['complication']['perspectives'] ?? []);
        $collisions = count($scqa['complication']['narrative_collisions'] ?? []);
        $hasShift = trim((string) ($scqa['structural_shift']['headline'] ?? '')) !== '';
        $confidence = 'medium';
        if ($diversity >= 0.5 && $perspectives >= 2 && $collisions >= 2 && $hasShift) {
            $confidence = 'high';
        } elseif ($diversity < 0.3 || $perspectives < 2) {
            $confidence = 'low';
        }
        if ($premiumRatio >= 0.4 && $collisions >= 2) {
            $confidence = $confidence === 'low' ? 'medium' : $confidence;
        }
        return [
            'source_count' => $sourceCount,
            'source_diversity' => $diversity,
            'perspective_count' => $perspectives,
            'narrative_collision_count' => $collisions,
            'has_structural_shift' => $hasShift,
            'confidence' => $confidence,
            'article_total' => count($articles),
            'language' => 'ko',
        ];
    }

    /**
     * gist 앵커 + 매칭된 외부 기사로 레포트 저장
     */
    private function saveReportWithAnchors(string $week, string $start, string $end, array $scqa, array $gistAnchors, array $matchedExternal, array $meta): int
    {
        $sourceArticles = [];
        foreach ($gistAnchors as $anchor) {
            $sourceArticles[] = [
                'id' => (int) $anchor['id'],
                'title' => (string) $anchor['title'],
                'source_type' => 'gist_anchor',
                'url' => (string) ($anchor['url'] ?? ''),
            ];
        }
        foreach ($matchedExternal as $article) {
            $sourceArticles[] = [
                'id' => (int) $article['id'],
                'title' => (string) $article['title'],
                'source_type' => 'external_matched',
                'source_api' => (string) ($article['source_api'] ?? ''),
                'url' => (string) ($article['url'] ?? ''),
                'similarity' => (float) ($article['_match_similarity'] ?? 0),
                'matched_by_gist_id' => (int) ($article['_matched_by_gist_id'] ?? 0),
            ];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO weekly_strategic_reports
             (report_week, period_start, period_end, executive_summary, scqa_raw_json, source_articles_json, meta_json, confidence, status)
             VALUES (:report_week, :period_start, :period_end, :executive_summary, :scqa_raw_json, :source_articles_json, :meta_json, :confidence, :status)
             ON DUPLICATE KEY UPDATE
               period_start = VALUES(period_start),
               period_end = VALUES(period_end),
               executive_summary = VALUES(executive_summary),
               scqa_raw_json = VALUES(scqa_raw_json),
               source_articles_json = VALUES(source_articles_json),
               meta_json = VALUES(meta_json),
               confidence = VALUES(confidence),
               status = :status_on_update,
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'report_week' => $week,
            'period_start' => $start,
            'period_end' => $end,
            'executive_summary' => (string) ($scqa['executive_summary'] ?? ''),
            'scqa_raw_json' => json_encode($scqa, JSON_UNESCAPED_UNICODE),
            'source_articles_json' => json_encode($sourceArticles, JSON_UNESCAPED_UNICODE),
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'confidence' => (string) ($meta['confidence'] ?? 'medium'),
            'status' => 'draft',
            'status_on_update' => 'draft',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $fetch = $this->pdo->prepare('SELECT id FROM weekly_strategic_reports WHERE report_week = :week');
            $fetch->execute(['week' => $week]);
            $row = $fetch->fetch();
            $id = (int) ($row['id'] ?? 0);
        }
        return $id;
    }

    /** @deprecated Use saveReportWithAnchors for gist-anchor mode */
    private function saveReport(string $week, string $start, string $end, array $scqa, array $articles, array $meta): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO weekly_strategic_reports
             (report_week, period_start, period_end, executive_summary, scqa_raw_json, source_articles_json, meta_json, confidence, status)
             VALUES (:report_week, :period_start, :period_end, :executive_summary, :scqa_raw_json, :source_articles_json, :meta_json, :confidence, :status)
             ON DUPLICATE KEY UPDATE
               period_start = VALUES(period_start),
               period_end = VALUES(period_end),
               executive_summary = VALUES(executive_summary),
               scqa_raw_json = VALUES(scqa_raw_json),
               source_articles_json = VALUES(source_articles_json),
               meta_json = VALUES(meta_json),
               confidence = VALUES(confidence),
               status = :status_on_update,
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'report_week' => $week,
            'period_start' => $start,
            'period_end' => $end,
            'executive_summary' => (string) ($scqa['executive_summary'] ?? ''),
            'scqa_raw_json' => json_encode($scqa, JSON_UNESCAPED_UNICODE),
            'source_articles_json' => json_encode(array_map(fn($a) => [
                'id' => (int) $a['id'],
                'title' => (string) $a['title'],
                'source_api' => (string) $a['source_api'],
                'url' => (string) $a['url'],
            ], $articles), JSON_UNESCAPED_UNICODE),
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'confidence' => (string) ($meta['confidence'] ?? 'medium'),
            'status' => 'draft',
            'status_on_update' => 'draft',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $fetch = $this->pdo->prepare('SELECT id FROM weekly_strategic_reports WHERE report_week = :week');
            $fetch->execute(['week' => $week]);
            $row = $fetch->fetch();
            $id = (int) ($row['id'] ?? 0);
        }
        return $id;
    }

    /**
     * Judgment Feedback을 critique_embeddings에 저장 (원래 Judgment 철학: 인간 편집 → AI 학습)
     *
     * @param int $reportId weekly_strategic_reports.id
     * @param array $editDiff computeJsonDiff 결과
     * @param array $judgmentFeedbacks Admin이 입력한 피드백
     * @param string $editReason 편집 이유
     * @return array{stored: int, errors: array}
     */
    public function storeJudgmentFeedback(int $reportId, array $editDiff, array $judgmentFeedbacks, string $editReason): array
    {
        if ($this->rag === null || !$this->rag->isConfigured()) {
            return ['stored' => 0, 'errors' => ['RAGService not configured']];
        }

        $stmt = $this->pdo->prepare('SELECT report_week, executive_summary FROM weekly_strategic_reports WHERE id = :id');
        $stmt->execute(['id' => $reportId]);
        $report = $stmt->fetch();
        if (!$report) {
            return ['stored' => 0, 'errors' => ['Report not found']];
        }

        $stored = 0;
        $errors = [];

        $feedbackTexts = [];

        if ($editReason !== '') {
            $feedbackTexts[] = "【편집 이유】 " . $editReason;
        }

        foreach ($editDiff as $diff) {
            $path = (string) ($diff['path'] ?? '');
            $before = $diff['before'] ?? null;
            $after = $diff['after'] ?? null;
            if ($before === $after) {
                continue;
            }
            $beforeStr = is_array($before) ? json_encode($before, JSON_UNESCAPED_UNICODE) : (string) $before;
            $afterStr = is_array($after) ? json_encode($after, JSON_UNESCAPED_UNICODE) : (string) $after;
            if (mb_strlen($beforeStr) > 20 || mb_strlen($afterStr) > 20) {
                $feedbackTexts[] = "【{$path}】\n변경 전: {$beforeStr}\n변경 후: {$afterStr}";
            }
        }

        foreach ($judgmentFeedbacks as $feedback) {
            $category = (string) ($feedback['category'] ?? 'general');
            $comment = (string) ($feedback['comment'] ?? '');
            if ($comment !== '') {
                $feedbackTexts[] = "【{$category}】 {$comment}";
            }
        }

        if ($feedbackTexts === []) {
            return ['stored' => 0, 'errors' => []];
        }

        $combinedText = "Strategic Report #{$reportId} ({$report['report_week']}) 편집 피드백\n\n"
            . implode("\n\n---\n\n", $feedbackTexts);

        $metadata = [
            'type' => 'strategic_report_feedback',
            'report_id' => $reportId,
            'report_week' => (string) $report['report_week'],
            'feedback_count' => count($feedbackTexts),
            'has_edit_diff' => count($editDiff) > 0,
            'created_at' => date('c'),
        ];

        try {
            $critiqueId = 'sr_' . $reportId . '_' . time();
            $count = $this->rag->storeCritiqueEmbedding($critiqueId, $combinedText, $metadata);
            $stored = $count;
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return ['stored' => $stored, 'errors' => $errors];
    }

    /**
     * 과거 judgment 피드백을 프롬프트에 주입할 컨텍스트로 가져오기
     */
    public function getJudgmentContext(string $topic, int $limit = 5): array
    {
        if ($this->rag === null || !$this->rag->isConfigured()) {
            return [];
        }

        try {
            $context = $this->rag->retrieveRelevantContext($topic, $limit);
            $critiques = $context['critiques'] ?? [];

            $relevant = [];
            foreach ($critiques as $c) {
                $meta = $c['metadata'] ?? [];
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?: [];
                }
                if (($meta['type'] ?? '') === 'strategic_report_feedback') {
                    $relevant[] = [
                        'text' => (string) ($c['chunk_text'] ?? ''),
                        'report_week' => (string) ($meta['report_week'] ?? ''),
                        'similarity' => (float) ($c['similarity'] ?? 0),
                    ];
                }
            }
            return $relevant;
        } catch (\Throwable $e) {
            error_log('StrategicReportService getJudgmentContext error: ' . $e->getMessage());
            return [];
        }
    }
}
