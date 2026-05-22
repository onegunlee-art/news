<?php
declare(strict_types=1);

namespace App\Services;

class JinaFetchService
{
    private string $baseUrl;
    private int $delayMs;

    public function __construct(array $config = [])
    {
        $default = file_exists(dirname(__DIR__, 3) . '/config/intelligence.php')
            ? require dirname(__DIR__, 3) . '/config/intelligence.php'
            : [];
        $jina = array_merge($default['jina'] ?? [], $config);
        $this->baseUrl = rtrim((string) ($jina['base_url'] ?? 'https://r.jina.ai/'), '/') . '/';
        $this->delayMs = (int) ($jina['delay_ms'] ?? 1000);
    }

    public function fetchContent(string $url): ?array
    {
        $target = $this->baseUrl . $url;
        $ch = curl_init($target);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
        $text = trim((string) $body);
        if ($text === '') {
            return null;
        }
        $title = '';
        $content = $text;
        if (preg_match('/^Title:\s*(.+)$/m', $text, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/\nMarkdown Content:\n([\s\S]*)$/', $text, $m)) {
            $content = trim($m[1]);
        }
        return [
            'title' => $title,
            'content' => $content,
            'description' => mb_substr(strip_tags($content), 0, 500),
        ];
    }

    /** @param string[] $urls */
    public function fetchBatch(array $urls): array
    {
        $results = [];
        foreach ($urls as $url) {
            $results[$url] = $this->fetchContent($url);
            if ($this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }
        }
        return $results;
    }
}
