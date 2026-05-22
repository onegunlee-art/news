<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use PDO;

class StrategicReportService
{
    private PDO $pdo;
    private OpenAIService $openai;
    private IntelligenceEmbeddingService $embedder;

    public function __construct(PDO $pdo, ?OpenAIService $openai = null, ?IntelligenceEmbeddingService $embedder = null)
    {
        $this->pdo = $pdo;
        $this->openai = $openai ?? new OpenAIService([]);
        $this->embedder = $embedder ?? new IntelligenceEmbeddingService();
    }

    public function generateForWeek(?string $reportWeek = null): array
    {
        [$week, $start, $end] = $this->resolveWeek($reportWeek);
        $articles = $this->fetchWeekArticles($start, $end);
        $usedFallback = false;
        if ($articles === []) {
            // 첫 실행·목요일 이전 수집 등: 이번 주에 없으면 최근 14일 embedded 기사 사용
            $articles = $this->fetchRecentArticles(14);
            $usedFallback = $articles !== [];
        }
        if ($articles === []) {
            return [
                'success' => false,
                'error' => 'No intelligence articles for period',
                'period' => ['report_week' => $week, 'start' => $start, 'end' => $end],
                'hint' => 'embed_status=done 기사가 기간 밖일 수 있습니다. MySQL: SELECT MIN(published_at), MAX(published_at) FROM intelligence_source_items WHERE embed_status=\'done\';',
            ];
        }
        $context = $this->buildContext($articles);
        $scqa = $this->generateScqa($context, $start, $end, count($articles));
        if ($scqa === null) {
            return ['success' => false, 'error' => 'SCQA generation failed'];
        }
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
        // 기본: 이번 ISO 주(월~일). 월~수 수집 → 목 레포트 cadence와 일치
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
             ORDER BY relevance_score DESC, published_at DESC
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
             ORDER BY relevance_score DESC, published_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function buildContext(array $articles): string
    {
        $ragHits = $this->embedder->search('geopolitical structural shifts trade security diplomacy', [
            'match_count' => 12,
            'min_relevance' => 55,
        ]);
        $blocks = ["=== RAG Intelligence Chunks ==="];
        foreach ($ragHits as $hit) {
            $blocks[] = sprintf('[article_id:%s score:%.3f] %s',
                (string) ($hit['article_id'] ?? ''),
                (float) ($hit['final_score'] ?? 0),
                mb_substr((string) ($hit['chunk_text'] ?? ''), 0, 600)
            );
        }
        $blocks[] = "\n=== Source Articles ===";
        foreach ($articles as $article) {
            $blocks[] = sprintf('[id:%d source:%s relevance:%d] %s\n%s',
                (int) $article['id'],
                (string) $article['source_api'],
                (int) $article['relevance_score'],
                (string) $article['title'],
                mb_substr((string) ($article['clean_text'] ?? $article['description'] ?? ''), 0, 900)
            );
        }
        return implode("\n\n", $blocks);
    }

    private function generateScqa(string $context, string $start, string $end, int $count): ?array
    {
        if (!$this->openai->isConfigured()) {
            return null;
        }
        $system = 'You are a McKinsey-style geopolitical strategist. Output valid JSON only.';
        $user = <<<PROMPT
Create a weekly strategic SCQA report for {$start} to {$end} using ONLY the provided intelligence context.

Rules:
1. Every timeline item and perspective MUST include source_id (intelligence_source_items.id)
2. why_it_matters_chain must be an array with at least 3 steps
3. No markdown outside JSON
4. Do not invent facts not supported by context

JSON schema:
{
  "core_question": "",
  "executive_summary": "",
  "situation": {"narrative":"","timeline":[{"date":"","event":"","source_id":0}],"anchor_entities":[]},
  "complication": {"trigger":"","perspectives":[{"viewpoint":"","source_id":0,"quote":""}]},
  "question": "",
  "answer": {
    "implication": "",
    "why_it_matters_chain": ["", "", ""],
    "scenarios": [{"type":"base","probability":60,"outcome":"","prediction_signal":""}],
    "action_matrix": {"watch":[],"consider":[],"act":[]}
  },
  "meta": {"source_count":{},"confidence":"medium"}
}

Context ({$count} articles):
{$context}
PROMPT;
        try {
            $raw = $this->openai->chat($system, $user, [
                'model' => 'gpt-4o',
                'temperature' => 0.4,
                'max_tokens' => 3500,
                'json_mode' => true,
                'timeout' => 120,
            ]);
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            error_log('StrategicReportService: ' . $e->getMessage());
            return null;
        }
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
        $confidence = 'medium';
        if ($diversity >= 0.5 && $perspectives >= 2) {
            $confidence = 'high';
        } elseif ($diversity < 0.3 || $perspectives < 2) {
            $confidence = 'low';
        }
        if ($premiumRatio >= 0.4 && $perspectives >= 2) {
            $confidence = $confidence === 'low' ? 'medium' : $confidence;
        }
        return [
            'source_count' => $sourceCount,
            'source_diversity' => $diversity,
            'perspective_count' => $perspectives,
            'confidence' => $confidence,
            'article_total' => count($articles),
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
               status = VALUES(status),
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
