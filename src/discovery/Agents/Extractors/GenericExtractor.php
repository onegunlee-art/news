<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

class GenericExtractor implements ExtractorInterface
{
    public function supports(string $domain): bool
    {
        return true;
    }

    public function extract(string $url, int $timeoutSec = 20): string
    {
        $html = $this->fetchHtml($url, $timeoutSec);
        if ($html === null) {
            throw new ExtractionException('Failed to fetch URL: ' . $url);
        }

        $text = $this->extractMainText($html);
        if (mb_strlen(trim($text)) < 200) {
            throw new ExtractionException('Extracted text too short');
        }

        return $text;
    }

    protected function fetchHtml(string $url, int $timeoutSec): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GistDiscoveryExtractor/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
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

    protected function extractMainText(string $html): string
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!$doc->loadHTML('<?xml encoding="UTF-8">' . $html)) {
            libxml_clear_errors();
            return $this->fallbackStripTags($html);
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $selectors = [
            '//article',
            '//*[contains(@class,"article-body")]',
            '//*[contains(@class,"article__body")]',
            '//*[contains(@class,"story-body")]',
            '//*[contains(@class,"entry-content")]',
            '//*[contains(@class,"post-content")]',
            '//*[@role="main"]',
            '//main',
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes === false || $nodes->length === 0) {
                continue;
            }
            $text = $this->nodeToText($nodes->item(0));
            if (mb_strlen(trim($text)) >= 200) {
                return $text;
            }
        }

        return $this->fallbackStripTags($html);
    }

    private function nodeToText(?\DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }
        $text = $node->textContent ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function fallbackStripTags(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
