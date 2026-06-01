<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * 검색 클러스터 분석 저장·재열람 (Admin)
 */
class SearchReportService
{
    private PDO $pdo;
    private SearchAnalysisService $analysis;

    public function __construct(PDO $pdo, ?SearchAnalysisService $analysis = null)
    {
        $this->pdo = $pdo;
        $this->analysis = $analysis ?? new SearchAnalysisService($pdo);
    }

    public function ensureTable(): void
    {
        $sqlFile = dirname(__DIR__, 3) . '/database/migrations/add_search_reports.sql';
        if (!is_file($sqlFile)) {
            return;
        }
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return;
        }
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
            if ($statement !== '' && stripos($statement, 'CREATE TABLE') !== false) {
                $this->pdo->exec($statement);
            }
        }
    }

    /**
     * @param list<int> $newsIds
     * @param array<string, mixed> $meta
     */
    public function save(
        string $clusterName,
        string $analysisText,
        array $newsIds,
        ?string $searchQuery = null,
        ?string $clusterQuestion = null,
        array $meta = []
    ): int {
        $this->ensureTable();
        $ctx = $this->analysis->buildClusterContext($newsIds);

        $jsonIds = json_encode(array_values($newsIds), JSON_UNESCAPED_UNICODE);
        $jsonTitles = json_encode($ctx['titles'], JSON_UNESCAPED_UNICODE);
        $jsonEntities = json_encode($ctx['entities'], JSON_UNESCAPED_UNICODE);
        $jsonTopics = json_encode($ctx['topic_labels'], JSON_UNESCAPED_UNICODE);
        $jsonMeta = json_encode($meta, JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            'INSERT INTO search_reports
            (search_query, cluster_name, cluster_question, analysis_text, news_ids_json,
             article_titles_json, entities_json, topic_labels_json, meta_json, article_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $searchQuery !== null && $searchQuery !== '' ? mb_substr($searchQuery, 0, 500) : null,
            mb_substr($clusterName, 0, 500),
            $clusterQuestion !== null && $clusterQuestion !== '' ? mb_substr($clusterQuestion, 0, 500) : null,
            $analysisText,
            $jsonIds,
            $jsonTitles,
            $jsonEntities,
            $jsonTopics,
            $jsonMeta,
            count($newsIds),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReports(int $limit = 50): array
    {
        $this->ensureTable();
        $limit = min(100, max(1, $limit));
        $stmt = $this->pdo->query(
            'SELECT id, search_query, cluster_name, cluster_question, article_count,
                    entities_json, topic_labels_json, created_at, updated_at,
                    LEFT(analysis_text, 200) AS analysis_preview
             FROM search_reports ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['entities'] = json_decode($row['entities_json'] ?? '[]', true) ?: [];
            $row['topic_labels'] = json_decode($row['topic_labels_json'] ?? '[]', true) ?: [];
            unset($row['entities_json'], $row['topic_labels_json']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetail(int $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM search_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'search_query' => $row['search_query'],
            'cluster_name' => $row['cluster_name'],
            'cluster_question' => $row['cluster_question'],
            'analysis_text' => $row['analysis_text'],
            'news_ids' => json_decode($row['news_ids_json'] ?? '[]', true) ?: [],
            'article_titles' => json_decode($row['article_titles_json'] ?? '{}', true) ?: [],
            'entities' => json_decode($row['entities_json'] ?? '[]', true) ?: [],
            'topic_labels' => json_decode($row['topic_labels_json'] ?? '[]', true) ?: [],
            'meta' => json_decode($row['meta_json'] ?? '{}', true) ?: [],
            'article_count' => (int) $row['article_count'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    public function updateAnalysis(int $id, string $analysisText, ?array $meta = null): bool
    {
        $this->ensureTable();
        if ($meta !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE search_reports SET analysis_text = ?, meta_json = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([
                $analysisText,
                json_encode($meta, JSON_UNESCAPED_UNICODE),
                $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE search_reports SET analysis_text = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$analysisText, $id]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<int> $newsIds
     * @return array{report: array<string, mixed>, memory: array<string, mixed>}
     */
    public function generateAndSave(
        array $newsIds,
        string $clusterName,
        ?string $searchQuery = null,
        ?string $clusterQuestion = null
    ): array {
        $memoryService = new StrategicMemoryService($this->pdo, $this->analysis);
        $ctx = $this->analysis->buildClusterContext($newsIds);
        $memory = $memoryService->compareClusterToWeeklyGist(
            $newsIds,
            $clusterName,
            $ctx['entities'],
            $ctx['topic_labels']
        );
        $analysisText = $this->analysis->generateAnalysis($clusterName, $newsIds);
        $id = $this->save(
            $clusterName,
            $analysisText,
            $newsIds,
            $searchQuery,
            $clusterQuestion,
            ['memory_diff' => $memory, 'source' => 'admin_generate']
        );
        $detail = $this->getDetail($id);

        return [
            'report' => $detail ?? [],
            'memory' => $memory,
        ];
    }
}
