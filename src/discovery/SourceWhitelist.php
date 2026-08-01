<?php
declare(strict_types=1);

namespace Discovery;

final class SourceWhitelist
{
    /** @var list<string> */
    private array $domains;

    /** @var list<string> */
    private array $blocklist;

    /**
     * @param list<string> $domains
     * @param list<string> $blocklist
     */
    public function __construct(array $domains, array $blocklist = [])
    {
        $this->domains = $this->normalizeDomains($domains);
        $this->blocklist = $this->normalizeDomains($blocklist);
    }

    public function isBlockedUrl(string $url): bool
    {
        $host = $this->normalizeHost($url);
        if ($host === '') {
            return false;
        }

        foreach ($this->blocklist as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        if (preg_match('/\.go\.kr$/', $host) === 1) {
            return true;
        }

        return false;
    }

    public function isWhitelistedUrl(string $url): bool
    {
        if ($this->isBlockedUrl($url)) {
            return false;
        }

        $host = $this->normalizeHost($url);
        if ($host === '') {
            return false;
        }

        foreach ($this->domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        if (preg_match('/\.gov$/', $host) === 1) {
            return true;
        }
        if (preg_match('/\.int$/', $host) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param list<array{name?:string,url?:string,article_title?:string}> $sources
     */
    public function hasBlockedSource(array $sources): bool
    {
        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            if ($url !== '' && $this->isBlockedUrl($url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{name?:string,url?:string,article_title?:string}> $sources
     */
    public function hasWhitelistedSource(array $sources): bool
    {
        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            if ($url !== '' && $this->isWhitelistedUrl($url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $sources
     * @return list<array<string,mixed>>
     */
    public function filterWhitelistedSources(array $sources): array
    {
        return array_values(array_filter(
            $sources,
            fn(array $s) => $this->isWhitelistedUrl(trim((string) ($s['url'] ?? '')))
        ));
    }

    public function hostLabel(string $url): string
    {
        $host = $this->normalizeHost($url);

        return $host !== '' ? $host : '?';
    }

    /** @param list<string> $domains @return list<string> */
    private function normalizeDomains(array $domains): array
    {
        return array_values(array_unique(array_map(
            static fn(string $d) => strtolower(ltrim(trim($d), '.')),
            $domains
        )));
    }

    private function normalizeHost(string $url): string
    {
        $host = strtolower((string) (parse_url(trim($url), PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return '';
        }

        return (string) preg_replace('/^www\./', '', $host);
    }
}
