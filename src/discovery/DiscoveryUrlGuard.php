<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryUrlGuard
{
    public function looksHallucinated(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }

        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $url) === 1) {
            return true;
        }

        if (preg_match('#/content/[0-9a-f-]{20,}#i', $url) === 1) {
            return true;
        }

        if (preg_match('#/\d{8,}(?:/|$)#', $url) === 1 && preg_match('#(reuters|bbc|apnews|ft\.com)#i', $url) === 1) {
            return preg_match('#/(world|news|article|technology|business)-\d{6,}#i', $url) !== 1;
        }

        return false;
    }

    /** @param list<string> $allowedUrls */
    public function isAllowedUrl(string $url, array $allowedUrls): bool
    {
        $url = $this->normalizeUrl($url);
        if ($url === '') {
            return false;
        }

        foreach ($allowedUrls as $allowed) {
            if ($this->normalizeUrl($allowed) === $url) {
                return true;
            }
        }

        return false;
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower((string) preg_replace('/^www\./', '', (string) $parts['host']));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return strtolower(($parts['scheme'] ?? 'https') . '://' . $host . $path . $query);
    }
}
