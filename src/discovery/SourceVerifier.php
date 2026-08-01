<?php
declare(strict_types=1);

namespace Discovery;

final class SourceVerifier
{
    private int $timeoutSec;

    public function __construct(int $timeoutSec = 15)
    {
        $this->timeoutSec = $timeoutSec;
    }

    /**
     * @param list<array{name:string,url:string,article_title?:string}> $sources
     * @return list<array{name:string,url:string,article_title?:string,verified:bool,fail_reason?:string}>
     */
    public function verify(array $sources, string $changeTitle): array
    {
        $verified = [];

        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            $name = trim((string) ($source['name'] ?? ''));
            $articleTitle = trim((string) ($source['article_title'] ?? ''));

            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                $verified[] = array_merge($source, [
                    'verified' => false,
                    'fail_reason' => 'invalid_url',
                ]);
                continue;
            }

            $result = $this->fetchUrl($url);
            if ($result === null) {
                $verified[] = array_merge($source, [
                    'verified' => false,
                    'fail_reason' => 'http_fetch_failed',
                ]);
                continue;
            }

            [$html, $httpCode] = $result;
            if ($httpCode < 200 || $httpCode >= 400) {
                $verified[] = array_merge($source, [
                    'verified' => false,
                    'fail_reason' => 'http_' . $httpCode,
                ]);
                continue;
            }

            $plain = $this->htmlToText($html);

            // Strategy: Use English article_title for primary matching (same language),
            // with numbers/proper nouns from Korean title as fallback
            $primaryKeywords = $articleTitle !== '' ? $this->extractKeywords($articleTitle) : [];
            $fallbackTokens = $this->extractUniversalTokens($changeTitle);

            $matched = false;
            if ($primaryKeywords !== [] && $this->containsKeywords($plain, $primaryKeywords)) {
                $matched = true;
            } elseif ($fallbackTokens !== [] && $this->containsUniversalTokens($plain, $fallbackTokens)) {
                $matched = true;
            } elseif ($primaryKeywords === [] && $fallbackTokens === [] && mb_strlen($plain) > 200) {
                $matched = true;
            }

            if (!$matched) {
                $verified[] = array_merge($source, [
                    'verified' => false,
                    'fail_reason' => 'keyword_mismatch',
                ]);
                continue;
            }

            $verified[] = array_merge($source, [
                'name' => $name !== '' ? $name : $this->guessSourceName($url),
                'verified' => true,
            ]);
        }

        return $verified;
    }

    /** @return list<string> */
    private function extractKeywords(string $title): array
    {
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? $title);
        $parts = preg_split('/\s+/u', $clean) ?: [];
        $stop = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were'];
        $keywords = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3 || in_array($part, $stop, true)) {
                continue;
            }
            $keywords[] = $part;
        }
        return array_slice(array_unique($keywords), 0, 8);
    }

    /**
     * Extract language-agnostic tokens: numbers, proper nouns (ASCII), percentage patterns.
     * These can match regardless of Korean vs English text.
     * @return list<string>
     */
    private function extractUniversalTokens(string $title): array
    {
        $tokens = [];

        // Numbers with optional comma/decimal (e.g., "60,000", "3.1", "2026")
        if (preg_match_all('/\d[\d,\.]*/', $title, $matches)) {
            foreach ($matches[0] as $num) {
                $num = trim($num, '.,');
                if (strlen($num) >= 2) {
                    $tokens[] = $num;
                }
            }
        }

        // Proper nouns: capitalized ASCII words (e.g., "BP", "Anthropic", "Hamas", "NATO")
        if (preg_match_all('/[A-Z][A-Za-z]{1,}/', $title, $matches)) {
            foreach ($matches[0] as $word) {
                if (strlen($word) >= 2) {
                    $tokens[] = mb_strtolower($word);
                }
            }
        }

        // Percentage patterns
        if (preg_match_all('/\d+(?:\.\d+)?%/', $title, $matches)) {
            foreach ($matches[0] as $pct) {
                $tokens[] = $pct;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Check if text contains universal tokens (numbers, proper nouns).
     * Requires at least 1 match since these are high-signal tokens.
     * @param list<string> $tokens
     */
    private function containsUniversalTokens(string $text, array $tokens): bool
    {
        if ($tokens === []) {
            return false;
        }
        $lower = mb_strtolower($text);
        $hits = 0;
        foreach ($tokens as $tok) {
            if (mb_strpos($lower, $tok) !== false) {
                $hits++;
            }
        }
        return $hits >= 1;
    }

    /** @param list<string> $keywords */
    private function containsKeywords(string $text, array $keywords): bool
    {
        if ($keywords === []) {
            return false;
        }
        $lower = mb_strtolower($text);
        $hits = 0;
        foreach ($keywords as $kw) {
            if (mb_strpos($lower, $kw) !== false) {
                $hits++;
            }
        }
        return $hits >= min(2, count($keywords));
    }

    /** @return array{0:string,1:int}|null */
    private function fetchUrl(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GistDiscoveryVerifier/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        ]);
        if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return null;
        }
        return [(string) $body, $httpCode];
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function guessSourceName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'Source';
        return preg_replace('/^www\./', '', $host) ?? $host;
    }
}
