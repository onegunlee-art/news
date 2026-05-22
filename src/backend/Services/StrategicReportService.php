<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use App\Config\StrategicReportSchema;
use PDO;

class StrategicReportService
{
    private PDO $pdo;
    private OpenAIService $openai;
    private IntelligenceEmbeddingService $embedder;
    private array $config;

    public function __construct(PDO $pdo, ?OpenAIService $openai = null, ?IntelligenceEmbeddingService $embedder = null)
    {
        $this->pdo = $pdo;
        $this->openai = $openai ?? new OpenAIService([]);
        $this->embedder = $embedder ?? new IntelligenceEmbeddingService();
        $configPath = dirname(__DIR__, 3) . '/config/strategic_report.php';
        $this->config = is_file($configPath) ? require $configPath : [];
    }

    public function generateForWeek(?string $reportWeek = null): array
    {
        [$week, $start, $end] = $this->resolveWeek($reportWeek);
        $articles = $this->fetchWeekArticles($start, $end);
        $usedFallback = false;
        if ($articles === []) {
            $articles = $this->fetchRecentArticles(14);
            $usedFallback = $articles !== [];
        }
        if ($articles === []) {
            return [
                'success' => false,
                'error' => 'No intelligence articles for period',
                'period' => ['report_week' => $week, 'start' => $start, 'end' => $end],
            ];
        }
        $context = $this->buildContext($articles);
        $scqa = $this->generateScqa($context, $start, $end, count($articles));
        if ($scqa === null) {
            return ['success' => false, 'error' => 'SCQA generation failed'];
        }
        $scqa = $this->normalizeScqa($scqa);
        $meta = $this->buildMeta($articles, $scqa);
        if ($usedFallback) {
            $meta['period_fallback'] = 'last_14_days';
        }
        $reportId = $this->saveReport($week, $start, $end, $scqa, $articles, $meta);
        return ['success' => true, 'report_id' => $reportId, 'report_week' => $week, 'scqa' => $scqa, 'meta' => $meta];
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
}
