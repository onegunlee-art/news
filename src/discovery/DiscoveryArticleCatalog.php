<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryArticleCatalog
{
    private int $timeoutSec;

    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly SourceWhitelist $whitelist,
        int $timeoutSec = 20,
    ) {
        $this->timeoutSec = $timeoutSec;
    }

    /**
     * @return list<array{index:int,title:string,url:string,description:string,source_name:string,published_at:string}>
     */
    public function fetch(string $editionDate): array
    {
        $maxAgeHours = (int) ($this->config['max_age_hours'] ?? 48);
        $cutoff = (new \DateTimeImmutable($editionDate . ' 23:59:59', new \DateTimeZone('Asia/Seoul')))
            ->modify(sprintf('-%d hours', $maxAgeHours));

        $articles = [];
        $seenUrls = [];
        $feeds = $this->config['rss_feeds'] ?? [];

        foreach ($feeds as $feed) {
            if (!is_array($feed)) {
                continue;
            }
            $feedName = (string) ($feed['name'] ?? 'Source');
            $feedUrl = (string) ($feed['url'] ?? '');
            if ($feedUrl === '') {
                continue;
            }

            foreach ($this->parseFeed($feedUrl) as $item) {
                $url = trim((string) ($item['url'] ?? ''));
                $title = trim((string) ($item['title'] ?? ''));
                if ($url === '' || $title === '') {
                    continue;
                }
                if (!$this->whitelist->isWhitelistedUrl($url) || $this->whitelist->isBlockedUrl($url)) {
                    continue;
                }

                $publishedAt = $this->parseDate((string) ($item['published_at'] ?? ''));
                if ($publishedAt !== null && $publishedAt < $cutoff) {
                    continue;
                }

                $norm = (new DiscoveryUrlGuard())->normalizeUrl($url);
                if (isset($seenUrls[$norm])) {
                    continue;
                }
                $seenUrls[$norm] = true;

                $articles[] = [
                    'index' => count($articles),
                    'title' => $title,
                    'url' => $url,
                    'description' => mb_substr(trim((string) ($item['description'] ?? '')), 0, 1200),
                    'source_name' => $feedName,
                    'published_at' => $publishedAt?->format('Y-m-d H:i:s') ?? '',
                ];
            }
        }

        return $articles;
    }

    /** @return list<array{title:string,url:string,description:string,published_at:string}> */
    private function parseFeed(string $feedUrl): array
    {
        $xml = $this->fetchUrl($feedUrl);
        if ($xml === null) {
            return [];
        }

        $root = @simplexml_load_string($xml);
        if ($root === false) {
            return [];
        }

        $items = [];
        if (isset($root->channel->item)) {
            foreach ($root->channel->item as $item) {
                $items[] = $this->normalizeRssItem($item);
            }
        } elseif (isset($root->entry)) {
            foreach ($root->entry as $entry) {
                $items[] = $this->normalizeAtomEntry($entry);
            }
        }

        return $items;
    }

    /** @return array{title:string,url:string,description:string,published_at:string} */
    private function normalizeRssItem(\SimpleXMLElement $item): array
    {
        $link = (string) ($item->link ?? '');
        if ($link === '' && isset($item->guid)) {
            $link = (string) $item->guid;
        }

        return [
            'title' => html_entity_decode(strip_tags((string) ($item->title ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url' => $link,
            'description' => html_entity_decode(strip_tags((string) ($item->description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'published_at' => (string) ($item->pubDate ?? ''),
        ];
    }

    /** @return array{title:string,url:string,description:string,published_at:string} */
    private function normalizeAtomEntry(\SimpleXMLElement $entry): array
    {
        $link = '';
        foreach ($entry->link ?? [] as $linkNode) {
            $attrs = $linkNode->attributes();
            if ($attrs === null) {
                continue;
            }
            $rel = (string) ($attrs['rel'] ?? 'alternate');
            if ($rel === 'alternate' || $link === '') {
                $link = (string) ($attrs['href'] ?? '');
            }
        }

        return [
            'title' => html_entity_decode(strip_tags((string) ($entry->title ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url' => $link,
            'description' => html_entity_decode(strip_tags((string) ($entry->summary ?? $entry->content ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'published_at' => (string) ($entry->published ?? $entry->updated ?? ''),
        ];
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GistDiscoveryCatalog/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
        ]);
        if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $httpCode < 200 || $httpCode >= 400) {
            return null;
        }

        return (string) $body;
    }
}
