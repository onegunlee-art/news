<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

/**
 * Guardian-specific article content extractor.
 * Guardian uses dcr-* prefixed classes and specific article structure.
 */
final class GuardianExtractor extends GenericExtractor
{
    public function supports(string $domain): bool
    {
        return str_contains($domain, 'theguardian.com');
    }

    public function extract(string $url, int $timeoutSec = 20): string
    {
        $html = $this->fetchHtml($url, $timeoutSec);
        if ($html === null) {
            throw new ExtractionException("Failed to fetch Guardian URL: {$url}");
        }

        $text = $this->extractGuardianContent($html);
        if ($text === null || mb_strlen($text) < 200) {
            $text = parent::extractMainText($html);
        }

        $text = $this->cleanGuardianText($text);

        if (mb_strlen($text) < 200) {
            throw new ExtractionException("Guardian extracted text too short");
        }

        return $text;
    }

    private function extractGuardianContent(string $html): ?string
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        if (!$doc->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR)) {
            libxml_clear_errors();
            return null;
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $guardianSelectors = [
            '//*[@id="maincontent"]//p',
            '//*[contains(@class,"article-body-viewer-selector")]//p',
            '//*[contains(@class,"dcr-")]//p[not(ancestor::aside)]',
            '//article//*[contains(@data-gu-name,"body")]//p',
            '//article//div[contains(@class,"content__article-body")]//p',
            '//*[@itemprop="articleBody"]//p',
        ];

        $paragraphs = [];
        foreach ($guardianSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes !== false && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $pText = trim($node->textContent ?? '');
                    if (mb_strlen($pText) > 30 && !$this->isGuardianNoise($pText)) {
                        $paragraphs[] = $pText;
                    }
                }
                if (count($paragraphs) >= 3) {
                    break;
                }
            }
        }

        if (count($paragraphs) >= 2) {
            return implode("\n\n", $paragraphs);
        }

        $articleNodes = $xpath->query('//article');
        if ($articleNodes !== false && $articleNodes->length > 0) {
            return $this->nodeToText($articleNodes->item(0));
        }

        return null;
    }

    private function isGuardianNoise(string $text): bool
    {
        $noisePatterns = [
            '/^(Sign up|Subscribe|Newsletter|Get the|Download the|Join the)/i',
            '/^(Share|Tweet|Email|Print)/i',
            '/^Photograph:/i',
            '/^(Related|See also|More on this topic)/i',
        ];

        foreach ($noisePatterns as $pattern) {
            if (preg_match($pattern, trim($text))) {
                return true;
            }
        }

        return false;
    }

    private function cleanGuardianText(string $text): string
    {
        $text = preg_replace('/Photograph:.*?(?=\n|$)/i', '', $text) ?? $text;
        $text = preg_replace('/\b(Sign up to|Subscribe to|Get the Guardian|Download the Guardian app)\b.*?(?=\n|$)/i', '', $text) ?? $text;
        $text = preg_replace('/\bRelated:.*?(?=\n|$)/i', '', $text) ?? $text;
        $text = preg_replace('/Getty Images|Reuters|PA|AFP|AP|Alamy/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return $this->cleanText(trim($text));
    }
}
