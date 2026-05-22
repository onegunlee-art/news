<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\NYTNewsService;
use PDO;

class IntelligenceCollectorService
{
    private PDO $pdo;
    private array $config;
    private TextCleanerService $cleaner;
    private QualityFilterService $qualityFilter;
    private DeduplicationService $deduper;
    private CategorizerService $categorizer;
    private SemanticChunkerService $chunker;
    private IntelligenceEmbeddingService $embedder;
    private JinaFetchService $jina;
    private GuardianNewsService $guardian;
    private NYTNewsService $nyt;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $default = file_exists(dirname(__DIR__, 3) . '/config/intelligence.php')
            ? require dirname(__DIR__, 3) . '/config/intelligence.php'
            : [];
        $this->config = array_merge($default, $config);
        $this->cleaner = new TextCleanerService();
        $this->qualityFilter = new QualityFilterService($this->config);
        $this->deduper = new DeduplicationService($this->config);
        $this->categorizer = new CategorizerService();
        $this->chunker = new SemanticChunkerService();
        $this->embedder = new IntelligenceEmbeddingService();
        $this->jina = new JinaFetchService($this->config['jina'] ?? []);
        $this->guardian = new GuardianNewsService();
        $this->nyt = new NYTNewsService();
    }

    public function runDaily(): array
    {
        $collected = $this->collect();
        $processed = $this->processPipeline();
        return ['collected' => $collected, 'processed' => $processed];
    }

    public function collect(): array
    {
        $stats = ['inserted' => 0, 'skipped' => 0, 'sources' => [], 'errors' => []];
        $stats['sources']['nyt'] = $this->collectNyt($stats['errors']);
        $stats['sources']['guardian'] = $this->collectGuardian($stats['errors']);
        $stats['sources']['rss'] = $this->collectRss($stats['errors']);
        $stats['inserted'] = array_sum($stats['sources']);
        return $stats;
    }

    private function collectNyt(array &$errors): int
    {
        $count = 0;
        foreach ((array) ($this->config['nyt_sections'] ?? ['world']) as $section) {
            $result = $this->nyt->getTopStories((string) $section);
            if (!($result['success'] ?? false)) {
                $errors['nyt'][] = [
                    'section' => (string) $section,
                    'error' => (string) ($result['error'] ?? 'request_failed'),
                ];
                continue;
            }
            $articles = $this->nyt->normalizeNews($result['data'], 'top_stories');
            foreach ($articles as $article) {
                if ($this->insertArticle('nyt', $article, (string) ($this->config['trust_tier']['nyt'] ?? 'A'))) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function collectGuardian(array &$errors): int
    {
        if (!$this->guardian->isConfigured()) {
            $raw = trim((string) ($_ENV['GUARDIAN_API_KEY'] ?? getenv('GUARDIAN_API_KEY') ?: ''));
            if ($raw !== '' && ($raw[0] === '{' || str_starts_with($raw, '{"response"'))) {
                $errors['guardian'][] = ['error' => 'invalid_api_key_json_pasted_use_key_from_developer_portal'];
            } else {
                $errors['guardian'][] = ['error' => 'api_key_missing'];
            }
            return 0;
        }
        $count = 0;
        foreach ((array) ($this->config['guardian_sections'] ?? ['world']) as $section) {
            $result = $this->guardian->searchArticles((string) $section, 15);
            if (!($result['success'] ?? false)) {
                $errors['guardian'][] = [
                    'section' => (string) $section,
                    'error' => (string) ($result['error'] ?? 'request_failed'),
                ];
                continue;
            }
            foreach ($result['articles'] as $article) {
                if ($this->insertArticle('guardian', $article, (string) ($this->config['trust_tier']['guardian'] ?? 'A'))) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function collectRss(array &$errors): int
    {
        $count = 0;
        foreach ((array) ($this->config['rss_feeds'] ?? []) as $feed) {
            $url = (string) ($feed['url'] ?? '');
            $xml = $this->fetchUrl($url);
            if ($xml === null) {
                $errors['rss'][] = [
                    'name' => (string) ($feed['name'] ?? $url),
                    'error' => 'fetch_failed',
                ];
                continue;
            }
            $rss = @simplexml_load_string($xml);
            if ($rss === false) {
                continue;
            }
            $items = $rss->channel->item ?? $rss->entry ?? [];
            foreach ($items as $item) {
                $article = [
                    'id' => md5((string) ($item->link ?? $item->guid ?? '')),
                    'title' => (string) ($item->title ?? ''),
                    'description' => (string) ($item->description ?? $item->summary ?? ''),
                    'content' => (string) ($item->description ?? $item->summary ?? ''),
                    'url' => (string) ($item->link ?? ''),
                    'published_at' => (string) ($item->pubDate ?? $item->published ?? date('c')),
                ];
                if ($this->insertArticle('rss', $article, (string) ($feed['trust_tier'] ?? 'B'))) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function insertArticle(string $sourceApi, array $article, string $trustTier): bool
    {
        $externalId = (string) ($article['id'] ?? md5((string) ($article['url'] ?? '')));
        $url = trim((string) ($article['url'] ?? ''));
        $title = trim((string) ($article['title'] ?? ''));
        if ($externalId === '' || $url === '' || $title === '') {
            return false;
        }
        $publishedAt = $this->normalizeDate((string) ($article['published_at'] ?? ''));
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO intelligence_source_items
             (source_api, external_id, url, published_at, title, description, trust_tier)
             VALUES (:source_api, :external_id, :url, :published_at, :title, :description, :trust_tier)'
        );
        $stmt->execute([
            'source_api' => $sourceApi,
            'external_id' => mb_substr($externalId, 0, 255),
            'url' => mb_substr($url, 0, 1000),
            'published_at' => $publishedAt,
            'title' => mb_substr($title, 0, 500),
            'description' => (string) ($article['description'] ?? ''),
            'trust_tier' => in_array($trustTier, ['A', 'B', 'C'], true) ? $trustTier : 'B',
        ]);
        return $stmt->rowCount() > 0;
    }

    public function processPipeline(int $limit = 50): array
    {
        $stats = ['fetched' => 0, 'cleaned' => 0, 'categorized' => 0, 'embedded' => 0, 'skipped' => 0];
        $rows = $this->fetchPendingRows($limit);
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($this->processFetch($row)) {
                $stats['fetched']++;
            }
            $row = $this->getRow($id) ?? $row;
            if (!$this->processCleanAndFilter($row)) {
                $stats['skipped']++;
                continue;
            }
            $stats['cleaned']++;
            $row = $this->getRow($id) ?? $row;
            if ($this->processCategorize($row)) {
                $stats['categorized']++;
            }
            $row = $this->getRow($id) ?? $row;
            if ($this->processEmbed($row)) {
                $stats['embedded']++;
            }
        }
        return $stats;
    }

    private function fetchPendingRows(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM intelligence_source_items
             WHERE embed_status IN ('pending', 'failed')
               AND duplicate_of IS NULL
             ORDER BY published_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function getRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM intelligence_source_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function processFetch(array $row): bool
    {
        if (($row['fetch_status'] ?? '') === 'fetched' && !empty($row['raw_content'])) {
            return true;
        }
        $url = (string) ($row['url'] ?? '');
        $fetched = $this->jina->fetchContent($url);
        if ($fetched === null) {
            $this->updateRow((int) $row['id'], [
                'fetch_status' => 'failed',
                'pipeline_errors' => $this->mergeError($row, 'fetch', 'jina_fetch_failed'),
            ]);
            return false;
        }
        $this->updateRow((int) $row['id'], [
            'raw_content' => (string) ($fetched['content'] ?? ''),
            'fetch_status' => 'fetched',
        ]);
        return true;
    }

    private function processCleanAndFilter(array $row): bool
    {
        if (!empty($row['duplicate_of'])) {
            $this->updateRow((int) $row['id'], ['embed_status' => 'skipped', 'clean_status' => 'done']);
            return false;
        }
        $raw = (string) ($row['raw_content'] ?? $row['description'] ?? '');
        $clean = $this->cleaner->clean($raw, (string) ($row['title'] ?? ''));
        $wordCount = $this->cleaner->wordCount($clean);
        $trustTier = (string) ($row['trust_tier'] ?? 'B');
        $quality = $this->qualityFilter->evaluate($wordCount, $trustTier);
        if (!$quality['passed']) {
            $this->updateRow((int) $row['id'], [
                'clean_text' => $clean,
                'word_count' => $wordCount,
                'quality_score' => $quality['quality_score'],
                'clean_status' => 'done',
                'embed_status' => 'skipped',
                'pipeline_errors' => $this->mergeError($row, 'quality', 'below_minimum_word_count'),
            ]);
            return false;
        }
        $dupId = $this->deduper->findDuplicateId((string) $row['title'], $this->recentCandidates((int) $row['id']));
        $update = [
            'clean_text' => $clean,
            'word_count' => $wordCount,
            'quality_score' => $quality['quality_score'],
            'clean_status' => 'done',
        ];
        if ($dupId !== null) {
            $update['duplicate_of'] = $dupId;
            $update['embed_status'] = 'skipped';
        }
        $this->updateRow((int) $row['id'], $update);
        return $dupId === null;
    }

    private function processCategorize(array $row): bool
    {
        if (($row['categorize_status'] ?? '') === 'done') {
            return true;
        }
        $lead = mb_substr((string) ($row['clean_text'] ?? $row['description'] ?? ''), 0, 800);
        $result = $this->categorizer->categorize((string) ($row['title'] ?? ''), $lead);
        $this->updateRow((int) $row['id'], [
            'region' => json_encode($result['region'], JSON_UNESCAPED_UNICODE),
            'topic' => json_encode($result['topic'], JSON_UNESCAPED_UNICODE),
            'event_type' => $result['event_type'],
            'entities' => json_encode($result['entities'], JSON_UNESCAPED_UNICODE),
            'relevance_score' => $result['relevance_score'],
            'categorize_status' => 'done',
        ]);
        return true;
    }

    private function processEmbed(array $row): bool
    {
        if (($row['embed_status'] ?? '') === 'done') {
            return true;
        }
        if ((int) ($row['relevance_score'] ?? 0) < (int) ($this->config['min_relevance_score'] ?? 60)) {
            $this->updateRow((int) $row['id'], ['embed_status' => 'skipped']);
            return false;
        }
        $chunks = $this->chunker->chunk((string) ($row['clean_text'] ?? ''));
        $week = date('o-\\WW', strtotime((string) ($row['published_at'] ?? 'now')));
        $metadata = [
            'region' => json_decode((string) ($row['region'] ?? '[]'), true) ?: [],
            'topic' => json_decode((string) ($row['topic'] ?? '[]'), true) ?: [],
            'event_type' => (string) ($row['event_type'] ?? 'incident'),
            'relevance_score' => (int) ($row['relevance_score'] ?? 0),
            'trust_tier' => (string) ($row['trust_tier'] ?? 'B'),
            'published_at' => date('c', strtotime((string) ($row['published_at'] ?? 'now'))),
            'week' => $week,
            'source_api' => (string) ($row['source_api'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
        ];
        $entities = json_decode((string) ($row['entities'] ?? '{}'), true);
        if (is_array($entities)) {
            $metadata['entities_countries'] = $entities['countries'] ?? [];
            $metadata['entities_leaders'] = $entities['leaders'] ?? [];
            $metadata['entities_orgs'] = $entities['orgs'] ?? [];
        }
        $stored = $this->embedder->storeArticle((int) $row['id'], (string) ($row['source_api'] ?? ''), $chunks, $metadata);
        $this->updateRow((int) $row['id'], [
            'chunk_count' => $stored,
            'embed_status' => $stored > 0 ? 'done' : 'failed',
        ]);
        return $stored > 0;
    }

    private function recentCandidates(int $excludeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title FROM intelligence_source_items
             WHERE id != :id AND duplicate_of IS NULL
               AND published_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             ORDER BY published_at DESC LIMIT 200"
        );
        $stmt->execute(['id' => $excludeId]);
        return $stmt->fetchAll() ?: [];
    }

    private function updateRow(int $id, array $fields): void
    {
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            $sets[] = "`$key` = :$key";
            $params[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }
        $sql = 'UPDATE intelligence_source_items SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function mergeError(array $row, string $stage, string $message): string
    {
        $errors = json_decode((string) ($row['pipeline_errors'] ?? '{}'), true);
        if (!is_array($errors)) {
            $errors = [];
        }
        $errors[$stage] = $message;
        return json_encode($errors, JSON_UNESCAPED_UNICODE);
    }

    private function normalizeDate(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            return date('Y-m-d H:i:s');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private function fetchUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'thegist-intelligence/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
        return (string) $body;
    }
}

