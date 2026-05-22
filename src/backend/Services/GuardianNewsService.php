<?php
declare(strict_types=1);

namespace App\Services;

class GuardianNewsService
{
    private string $apiKey = '';
    private array $config;

    public function __construct()
    {
        $this->config = file_exists(dirname(__DIR__, 3) . '/config/guardian.php')
            ? require dirname(__DIR__, 3) . '/config/guardian.php'
            : [];
    }

    private function apiKey(): string
    {
        if ($this->apiKey !== '') {
            return $this->apiKey;
        }
        $fromEnv = $_ENV['GUARDIAN_API_KEY'] ?? getenv('GUARDIAN_API_KEY');
        if (function_exists('guardianNormalizeApiKey')) {
            $this->apiKey = guardianNormalizeApiKey($fromEnv);
        } else {
            $this->apiKey = is_string($fromEnv) ? trim($fromEnv) : '';
        }
        if ($this->apiKey === '' && is_string($this->config['api_key'] ?? null)) {
            $this->apiKey = trim((string) $this->config['api_key']);
        }
        return $this->apiKey;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function searchArticles(string $section = 'world', int $pageSize = 20): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Guardian API key missing', 'articles' => []];
        }
        $params = http_build_query([
            'section' => $section,
            'page-size' => $pageSize,
            'show-fields' => 'headline,trailText,body,byline,thumbnail,short-url',
            'order-by' => 'newest',
            'api-key' => $this->apiKey(),
        ]);
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://content.guardianapis.com'), '/') . '/search?' . $params;
        return $this->request($url);
    }

    public function normalizeNews(array $rawData): array
    {
        $results = $rawData['response']['results'] ?? [];
        $articles = [];
        foreach ($results as $item) {
            $fields = $item['fields'] ?? [];
            $articles[] = [
                'id' => (string) ($item['id'] ?? ''),
                'title' => (string) ($fields['headline'] ?? $item['webTitle'] ?? ''),
                'description' => (string) ($fields['trailText'] ?? ''),
                'content' => (string) ($fields['body'] ?? $fields['trailText'] ?? ''),
                'url' => (string) ($item['webUrl'] ?? $fields['short-url'] ?? ''),
                'source' => 'The Guardian',
                'section' => (string) ($item['sectionName'] ?? ''),
                'author' => (string) ($fields['byline'] ?? ''),
                'published_at' => (string) ($item['webPublicationDate'] ?? ''),
                'keywords' => [],
            ];
        }
        return $articles;
    }

    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return ['success' => false, 'error' => $err ?: ('HTTP ' . $code), 'articles' => []];
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid JSON', 'articles' => []];
        }
        return ['success' => true, 'data' => $data, 'articles' => $this->normalizeNews($data)];
    }
}
