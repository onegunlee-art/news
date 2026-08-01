<?php
declare(strict_types=1);

namespace Discovery\Agents\Extractors;

/**
 * BBC-specific article content extractor.
 * BBC uses data-component="text-block" for article paragraphs.
 */
final class BBCExtractor extends GenericExtractor
{
    public function supports(string $domain): bool
    {
        return str_contains($domain, 'bbc.co.uk') || str_contains($domain, 'bbc.com');
    }

    public function extract(string $url, int $timeoutSec = 20): string
    {
        $html = $this->fetchHtml($url, $timeoutSec);
        if ($html === null) {
            throw new ExtractionException("Failed to fetch BBC URL: {$url}");
        }

        $text = $this->extractBBCContent($html);
        if ($text === null || mb_strlen($text) < 200) {
            $text = parent::extractMainText($html);
        }

        $text = $this->cleanBBCText($text);

        if (mb_strlen($text) < 200) {
            throw new ExtractionException("BBC extracted text too short");
        }

        return $text;
    }

    private function extractBBCContent(string $html): ?string
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

        $bbcSelectors = [
            '//*[@data-component="text-block"]',
            '//article//*[contains(@class,"ssrcss")][contains(@class,"paragraph")]',
            '//article//p[contains(@class,"ssrcss")]',
            '//*[contains(@class,"article__body-content")]//p',
            '//*[contains(@class,"story-body__inner")]//p',
            '//article//div[contains(@class,"RichTextComponentWrapper")]//p',
        ];

        $paragraphs = [];
        foreach ($bbcSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes !== false && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $pText = trim($node->textContent ?? '');
                    if (mb_strlen($pText) > 30) {
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

    private function cleanBBCText(string $text): string
    {
        $text = preg_replace('/\b(BBC News|Related Topics|More on this story|Share this|Watch:?|Listen:?|Read more:?)\b.*?(?=\n|$)/iu', '', $text) ?? $text;
        $text = preg_replace('/Getty Images|Reuters|PA Media|AFP|AP/i', '', $text) ?? $text;
        $text = preg_replace('/Image source[,:]\s*\w+/i', '', $text) ?? $text;
        $text = preg_replace('/Image caption[,:]\s*.+?(?=\n|$)/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return $this->cleanText(trim($text));
    }
}
