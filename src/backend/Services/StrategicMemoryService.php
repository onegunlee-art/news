<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\SupabaseService;
use PDO;

/**
 * 주간 Gist ↔ 검색 클러스터 entity/topic 메모리 연결 (Admin)
 */
class StrategicMemoryService
{
    private PDO $pdo;
    private SearchAnalysisService $analysis;
    private SupabaseService $supabase;

    public function __construct(
        PDO $pdo,
        ?SearchAnalysisService $analysis = null,
        ?SupabaseService $supabase = null
    ) {
        $this->pdo = $pdo;
        $this->analysis = $analysis ?? new SearchAnalysisService($pdo);
        $this->supabase = $supabase ?? new SupabaseService([]);
    }

    /**
     * @param list<int> $newsIds
     * @param list<string> $entities
     * @param list<string> $topicLabels
     * @return array<string, mixed>
     */
    public function compareClusterToWeeklyGist(
        array $newsIds,
        string $clusterName,
        ?array $entities = null,
        ?array $topicLabels = null
    ): array {
        $newsIds = array_values(array_filter(array_map('intval', $newsIds), fn ($id) => $id > 0));
        if ($entities === null || $topicLabels === null) {
            $ctx = $this->analysis->buildClusterContext($newsIds);
            $entities = $ctx['entities'];
            $topicLabels = $ctx['topic_labels'];
        }

        $entitySet = $this->normalizeSet($entities);
        $topicSet = $this->normalizeSet($topicLabels);
        $newsSet = array_fill_keys($newsIds, true);

        $gistReports = $this->loadRecentGistReports(12);
        if ($gistReports === []) {
            return $this->emptyMemoryResult($clusterName, '저장된 위클리 Gist가 없습니다.');
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($gistReports as $report) {
            $gist = $report['gist'];
            if (!is_array($gist)) {
                continue;
            }
            $clusters = $gist['clusters'] ?? [];
            if (!is_array($clusters)) {
                continue;
            }

            foreach ($clusters as $cluster) {
                if (!is_array($cluster)) {
                    continue;
                }
                $sourceIds = array_values(array_filter(
                    array_map('intval', $cluster['source_article_ids'] ?? []),
                    fn ($id) => $id > 0
                ));
                $gistMeta = $this->collectRagMetadata($sourceIds);
                $gistEntities = $this->normalizeSet($gistMeta['entities']);
                $gistTopics = $this->normalizeSet($gistMeta['topic_labels']);

                $entityScore = $this->jaccard($entitySet, $gistEntities);
                $topicScore = $this->jaccard($topicSet, $gistTopics);
                $articleOverlap = 0.0;
                if ($sourceIds !== [] && $newsIds !== []) {
                    $overlap = count(array_intersect_key(array_fill_keys($sourceIds, true), $newsSet));
                    $articleOverlap = $overlap / max(count($newsSet), count($sourceIds));
                }

                $titleBonus = 0.0;
                $gistTitle = mb_strtolower(trim((string) ($cluster['title'] ?? '')));
                $currentName = mb_strtolower(trim($clusterName));
                if ($gistTitle !== '' && $currentName !== ''
                    && (mb_strpos($currentName, $gistTitle) !== false || mb_strpos($gistTitle, $currentName) !== false)) {
                    $titleBonus = 0.15;
                }

                $score = ($entityScore * 0.45) + ($topicScore * 0.35) + ($articleOverlap * 0.2) + $titleBonus;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $sharedEntities = array_values(array_intersect(array_keys($entitySet), array_keys($gistEntities)));
                    $sharedTopics = array_values(array_intersect(array_keys($topicSet), array_keys($gistTopics)));
                    $best = [
                        'gist_report_id' => (int) $report['id'],
                        'gist_period' => ($report['period_start'] ?? '') . ' ~ ' . ($report['period_end'] ?? ''),
                        'gist_headline' => (string) ($report['headline'] ?? $gist['headline'] ?? ''),
                        'weeks_ago' => $this->weeksAgo((string) ($report['period_end'] ?? '')),
                        'matched_cluster' => [
                            'cluster_id' => $cluster['cluster_id'] ?? null,
                            'title' => (string) ($cluster['title'] ?? ''),
                            'one_line_takeaway' => (string) ($cluster['one_line_takeaway'] ?? ''),
                            'narrative_excerpt' => mb_substr(
                                trim((string) ($cluster['narrative'] ?? '')),
                                0,
                                400
                            ),
                            'source_article_ids' => $sourceIds,
                        ],
                        'overlap' => [
                            'entities' => $sharedEntities,
                            'topic_labels' => $sharedTopics,
                            'entity_score' => round($entityScore, 3),
                            'topic_score' => round($topicScore, 3),
                            'article_overlap' => round($articleOverlap, 3),
                            'combined_score' => round($score, 3),
                        ],
                        'framing_then' => (string) ($cluster['one_line_takeaway'] ?? $cluster['title'] ?? ''),
                        'framing_now' => $clusterName,
                    ];
                }
            }
        }

        if ($best === null || $bestScore < 0.12) {
            return $this->emptyMemoryResult(
                $clusterName,
                '연결 가능한 위클리 Gist 클러스터를 찾지 못했습니다.',
                ['entities' => array_keys($entitySet), 'topic_labels' => array_keys($topicSet)]
            );
        }

        $best['matched'] = true;
        $best['diff_summary'] = $this->buildDiffSummary($best);

        return $best;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRecentGistReports(int $limit): array
    {
        try {
            $this->pdo->query('SELECT 1 FROM weekly_gist_reports LIMIT 1');
        } catch (\Throwable) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT id, period_start, period_end, headline, gist_json
             FROM weekly_gist_reports ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['gist'] = json_decode($row['gist_json'] ?? 'null', true);
            unset($row['gist_json']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<int> $newsIds
     * @return array{entities: list<string>, topic_labels: list<string>}
     */
    private function collectRagMetadata(array $newsIds): array
    {
        $entities = [];
        $topics = [];
        if (!$this->supabase->isConfigured() || $newsIds === []) {
            return ['entities' => [], 'topic_labels' => []];
        }
        foreach ($newsIds as $nid) {
            $rows = $this->supabase->select(
                'analysis_embeddings',
                'select=metadata&news_id=eq.' . (int) $nid . '&order=created_at.asc&limit=1',
                1
            );
            if (!is_array($rows) || count($rows) === 0) {
                continue;
            }
            $meta = $rows[0]['metadata'] ?? [];
            if (!is_array($meta)) {
                continue;
            }
            foreach ($meta['entities'] ?? [] as $ent) {
                $ent = trim((string) $ent);
                if ($ent !== '') {
                    $entities[$ent] = true;
                }
            }
            $tl = trim((string) ($meta['topic_label'] ?? ''));
            if ($tl !== '') {
                $topics[$tl] = true;
            }
        }

        return [
            'entities' => array_keys($entities),
            'topic_labels' => array_keys($topics),
        ];
    }

    /**
     * @param list<string> $items
     * @return array<string, true>
     */
    private function normalizeSet(array $items): array
    {
        $set = [];
        foreach ($items as $item) {
            $key = mb_strtolower(trim((string) $item));
            if ($key !== '') {
                $set[$key] = true;
            }
        }

        return $set;
    }

    /**
     * @param array<string, true> $a
     * @param array<string, true> $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] && $b === []) {
            return 0.0;
        }
        $inter = count(array_intersect_key($a, $b));
        $union = count($a + $b);

        return $union > 0 ? $inter / $union : 0.0;
    }

    private function weeksAgo(string $periodEnd): ?int
    {
        if ($periodEnd === '') {
            return null;
        }
        $end = strtotime($periodEnd);
        if ($end === false) {
            return null;
        }
        $days = (int) floor((time() - $end) / 86400);

        return max(0, (int) ceil($days / 7));
    }

    /**
     * @param array<string, mixed> $match
     */
    private function buildDiffSummary(array $match): string
    {
        $period = (string) ($match['gist_period'] ?? '');
        $weeks = $match['weeks_ago'];
        $weekLabel = $weeks === null ? '이전' : ($weeks <= 1 ? '지난 주' : "{$weeks}주 전");
        $gistTitle = (string) ($match['matched_cluster']['title'] ?? '');
        $then = (string) ($match['framing_then'] ?? '');
        $now = (string) ($match['framing_now'] ?? '');
        $entities = $match['overlap']['entities'] ?? [];
        $entityLine = is_array($entities) && $entities !== []
            ? implode(', ', array_slice($entities, 0, 5))
            : '공통 entity 없음';

        return "{$weekLabel}({$period}) 위클리 Gist 「{$gistTitle}」에서는 「{$then}」로 프레이밍했습니다. "
            . "이번 클러스터 「{$now}」와 entity/topic 겹침(점수 {$match['overlap']['combined_score']}) — 공통: {$entityLine}.";
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function emptyMemoryResult(string $clusterName, string $reason, array $context = []): array
    {
        return array_merge([
            'matched' => false,
            'framing_now' => $clusterName,
            'diff_summary' => $reason,
            'gist_report_id' => null,
            'gist_period' => null,
            'gist_headline' => null,
            'matched_cluster' => null,
            'overlap' => null,
        ], $context);
    }
}
