<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

/**
 * Generic article content extractor using Readability-style text density analysis.
 * Scores content blocks by paragraph density, text length, and link ratio.
 */
class GenericExtractor implements ExtractorInterface
{
    private const REAL_BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const NOISE_TAGS = [
        'script', 'style', 'nav', 'header', 'footer', 'aside', 'noscript',
        'form', 'button', 'input', 'select', 'textarea', 'iframe', 'svg',
    ];

    private const NOISE_CLASSES = [
        'nav', 'navigation', 'menu', 'sidebar', 'footer', 'header', 'comment',
        'social', 'share', 'related', 'advertisement', 'ad-', 'ads-', 'promo',
        'newsletter', 'subscribe', 'popup', 'modal', 'cookie', 'consent',
    ];

    public function supports(string $domain): bool
    {
        return true;
    }

    public function extract(string $url, int $timeoutSec = 20): string
    {
        $html = $this->fetchHtml($url, $timeoutSec);
        if ($html === null) {
            throw new ExtractionException("Failed to fetch URL: {$url}");
        }

        $text = $this->extractMainText($html);
        $cleanText = $this->cleanText($text);

        if (mb_strlen($cleanText) < 200) {
            throw new ExtractionException("Extracted text too short ({mb_strlen($cleanText)} chars)");
        }

        return $cleanText;
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
            CURLOPT_USERAGENT => self::REAL_BROWSER_UA,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
            ],
            CURLOPT_ENCODING => 'gzip, deflate',
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

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        if (!$doc->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR)) {
            libxml_clear_errors();
            return $this->fallbackStripTags($html);
        }
        libxml_clear_errors();

        $this->removeNoiseTags($doc);
        $this->removeNoiseByClass($doc);

        $best = $this->findBestContentBlock($doc);
        if ($best !== null && mb_strlen(trim($best)) >= 200) {
            return $best;
        }

        $articleText = $this->tryArticleSelectors($doc);
        if ($articleText !== null && mb_strlen(trim($articleText)) >= 200) {
            return $articleText;
        }

        return $this->fallbackStripTags($html);
    }

    private function removeNoiseTags(\DOMDocument $doc): void
    {
        foreach (self::NOISE_TAGS as $tag) {
            $nodes = $doc->getElementsByTagName($tag);
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
            }
            foreach ($toRemove as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function removeNoiseByClass(\DOMDocument $doc): void
    {
        $xpath = new \DOMXPath($doc);
        $toRemove = [];

        foreach (self::NOISE_CLASSES as $class) {
            $nodes = $xpath->query("//*[contains(@class, '{$class}')]");
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $toRemove[] = $node;
                }
            }
        }

        foreach ($toRemove as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Readability-style algorithm: score blocks by text density.
     */
    private function findBestContentBlock(\DOMDocument $doc): ?string
    {
        $xpath = new \DOMXPath($doc);
        $candidates = [];

        $blocks = $xpath->query('//div | //section | //article | //main');
        if ($blocks === false) {
            return null;
        }

        foreach ($blocks as $block) {
            if (!$block instanceof \DOMElement) {
                continue;
            }

            $score = $this->scoreBlock($block, $xpath);
            if ($score > 0) {
                $candidates[] = ['node' => $block, 'score' => $score];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        $best = $candidates[0]['node'];

        return $this->nodeToText($best);
    }

    private function scoreBlock(\DOMElement $block, \DOMXPath $xpath): float
    {
        $text = trim($block->textContent ?? '');
        $textLen = mb_strlen($text);

        if ($textLen < 200) {
            return 0;
        }

        $paragraphs = $xpath->query('.//p', $block);
        $pCount = $paragraphs !== false ? $paragraphs->length : 0;

        $links = $xpath->query('.//a', $block);
        $linkCount = $links !== false ? $links->length : 0;
        $linkTextLen = 0;
        if ($links !== false) {
            foreach ($links as $link) {
                $linkTextLen += mb_strlen(trim($link->textContent ?? ''));
            }
        }

        $linkDensity = $textLen > 0 ? $linkTextLen / $textLen : 0;
        if ($linkDensity > 0.5) {
            return 0;
        }

        $score = $textLen * 0.01;
        $score += $pCount * 10;
        $score *= (1 - $linkDensity);

        $class = strtolower($block->getAttribute('class') ?? '');
        $id = strtolower($block->getAttribute('id') ?? '');
        $combined = $class . ' ' . $id;

        if (preg_match('/(article|content|body|main|story|post|entry)/i', $combined)) {
            $score *= 1.5;
        }
        if (preg_match('/(comment|sidebar|footer|nav|menu|ad|promo)/i', $combined)) {
            $score *= 0.3;
        }

        return $score;
    }

    private function tryArticleSelectors(\DOMDocument $doc): ?string
    {
        $xpath = new \DOMXPath($doc);
        $selectors = [
            '//article//div[contains(@class,"content")]',
            '//article',
            '//*[contains(@class,"article-body")]',
            '//*[contains(@class,"article__body")]',
            '//*[contains(@class,"story-body")]',
            '//*[contains(@class,"post-content")]',
            '//*[contains(@class,"entry-content")]',
            '//*[contains(@itemprop,"articleBody")]',
            '//*[@data-component="text-block"]',
            '//main//div[contains(@class,"content")]',
            '//main',
            '//*[@role="main"]',
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

        return null;
    }

    protected function nodeToText(?\DOMNode $node): string
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
        $html = preg_replace('/<(script|style|nav|header|footer|aside)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    protected function cleanText(string $text): string
    {
        $text = preg_replace('/^\s*(Share|Tweet|Email|Print|Facebook|Twitter|LinkedIn|WhatsApp|Copy link|Skip to.*?content)\s*/im', '', $text) ?? $text;
        $text = preg_replace('/\s*(Share this article|Related articles|More from.*|Advertisement|Sponsored content|Newsletter signup|Subscribe to.*|Follow us on.*|Read more:?)\s*$/im', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
