<?php
/**
 * Web Scraper Service
 * 
 * URL에서 기사 콘텐츠 추출
 * Readability 알고리즘 적용
 * 
 * @package Agents\Services
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Services;

use Agents\Models\ArticleData;

class WebScraperService
{
    private array $config;
    private string $userAgent;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->userAgent = $config['user_agent'] ?? 'TheGist-NewsBot/1.0 (+https://thegist.ai)';
    }

    /**
     * URL에서 기사 데이터 추출
     */
    public function scrape(string $url): ArticleData
    {
        $html = $this->fetchHtml($url);
        
        if (empty($html)) {
            throw new \RuntimeException("Failed to fetch URL: {$url}");
        }

        return $this->parseHtml($url, $html);
    }

    /**
     * HTML 가져오기
     */
    private function fetchHtml(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7'
            ],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("HTTP error {$httpCode}: {$error}");
        }

        return $html ?: '';
    }

    /**
     * HTML 파싱하여 기사 데이터 추출
     */
    private function parseHtml(string $url, string $html): ArticleData
    {
        // DOM 파싱
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // 메타데이터 추출
        $title = $this->extractTitle($xpath);
        $description = $this->extractMetaContent($xpath, 'description');
        $author = $this->extractMetaContent($xpath, 'author');
        $publishedAt = $this->extractPublishedDate($xpath);
        $imageUrl = $this->extractMetaContent($xpath, 'og:image');
        $language = $this->detectLanguage($xpath, $html);

        // 본문 추출 (Readability 스타일)
        $content = $this->extractContent($xpath, $doc);

        return new ArticleData(
            url: $url,
            title: $title,
            content: $content,
            description: $description,
            author: $author,
            publishedAt: $publishedAt,
            imageUrl: $imageUrl,
            language: $language,
            metadata: [
                'scraped_at' => date('c'),
                'content_length' => strlen($content)
            ]
        );
    }

    /**
     * 제목 추출
     */
    private function extractTitle(\DOMXPath $xpath): string
    {
        // og:title 우선
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            return trim($ogTitle->item(0)->nodeValue);
        }

        // twitter:title
        $twitterTitle = $xpath->query('//meta[@name="twitter:title"]/@content');
        if ($twitterTitle->length > 0) {
            return trim($twitterTitle->item(0)->nodeValue);
        }

        // h1 태그
        $h1 = $xpath->query('//h1');
        if ($h1->length > 0) {
            return trim($h1->item(0)->textContent);
        }

        // title 태그
        $title = $xpath->query('//title');
        if ($title->length > 0) {
            return trim($title->item(0)->textContent);
        }

        return '';
    }

    /**
     * 메타 태그 내용 추출
     */
    private function extractMetaContent(\DOMXPath $xpath, string $name): ?string
    {
        // property 속성 (Open Graph)
        $og = $xpath->query("//meta[@property='{$name}']/@content");
        if ($og->length > 0) {
            return trim($og->item(0)->nodeValue);
        }

        // name 속성
        $meta = $xpath->query("//meta[@name='{$name}']/@content");
        if ($meta->length > 0) {
            return trim($meta->item(0)->nodeValue);
        }

        return null;
    }

    /**
     * 발행일 추출
     */
    private function extractPublishedDate(\DOMXPath $xpath): ?string
    {
        $dateSelectors = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@name="pubdate"]/@content',
            '//meta[@name="date"]/@content',
            '//time[@datetime]/@datetime',
            '//time[@pubdate]/@datetime'
        ];

        foreach ($dateSelectors as $selector) {
            $result = $xpath->query($selector);
            if ($result->length > 0) {
                $date = trim($result->item(0)->nodeValue);
                if (!empty($date)) {
                    return $date;
                }
            }
        }

        return null;
    }

    /**
     * 언어 감지
     */
    private function detectLanguage(\DOMXPath $xpath, string $html): string
    {
        // html lang 속성
        $lang = $xpath->query('//html/@lang');
        if ($lang->length > 0) {
            $langCode = trim($lang->item(0)->nodeValue);
            return substr($langCode, 0, 2); // en-US -> en
        }

        // Content-Language 메타
        $contentLang = $xpath->query('//meta[@http-equiv="Content-Language"]/@content');
        if ($contentLang->length > 0) {
            return substr(trim($contentLang->item(0)->nodeValue), 0, 2);
        }

        // 간단한 휴리스틱: 한글 포함 여부
        if (preg_match('/[\xEA-\xED][\x80-\xBF]{2}/u', $html)) {
            return 'ko';
        }

        return 'en'; // 기본값
    }

    /**
     * 본문 콘텐츠 추출 (Readability 스타일)
     */
    private function extractContent(\DOMXPath $xpath, \DOMDocument $doc): string
    {
        // 불필요한 요소 제거
        $removeSelectors = [
            '//script', '//style', '//nav', '//header', '//footer',
            '//aside', '//form', '//iframe', '//noscript',
            '//*[contains(@class, "ad")]', '//*[contains(@class, "advertisement")]',
            '//*[contains(@class, "sidebar")]', '//*[contains(@class, "comment")]',
            '//*[contains(@class, "related")]', '//*[contains(@class, "share")]',
            '//*[contains(@id, "ad")]', '//*[contains(@id, "comment")]'
        ];

        foreach ($removeSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $element->parentNode->removeChild($element);
            }
        }

        // 본문 후보 찾기
        $contentSelectors = [
            '//article',
            '//*[contains(@class, "article-body")]',
            '//*[contains(@class, "article-content")]',
            '//*[contains(@class, "post-content")]',
            '//*[contains(@class, "entry-content")]',
            '//*[contains(@class, "story-body")]',
            '//*[@itemprop="articleBody"]',
            '//main',
            '//*[contains(@class, "content")]'
        ];

        foreach ($contentSelectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements->length > 0) {
                $content = $this->extractTextFromNode($elements->item(0));
                if (strlen($content) > 200) { // 충분한 길이의 콘텐츠
                    return $content;
                }
            }
        }

        // Fallback: 가장 긴 p 태그들의 부모
        $paragraphs = $xpath->query('//p');
        $bestParent = null;
        $maxLength = 0;

        foreach ($paragraphs as $p) {
            $parent = $p->parentNode;
            if ($parent) {
                $text = $this->extractTextFromNode($parent);
                $length = strlen($text);
                if ($length > $maxLength) {
                    $maxLength = $length;
                    $bestParent = $parent;
                }
            }
        }

        if ($bestParent) {
            return $this->extractTextFromNode($bestParent);
        }

        // 최후의 수단: body 전체
        $body = $xpath->query('//body');
        if ($body->length > 0) {
            return $this->extractTextFromNode($body->item(0));
        }

        return '';
    }

    /**
     * 노드에서 텍스트 추출
     */
    private function extractTextFromNode(\DOMNode $node): string
    {
        $text = $node->textContent;
        
        // 정리
        $text = preg_replace('/\s+/', ' ', $text); // 연속 공백 제거
        $text = preg_replace('/\n\s*\n/', "\n\n", $text); // 연속 줄바꿈 정리
        $text = trim($text);
        
        return $text;
    }

    /**
     * URL 유효성 검사
     */
    public function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        
        // 차단된 도메인 체크
        $blockedDomains = $this->config['blocked_domains'] ?? ['localhost', '127.0.0.1'];
        if (in_array($parsed['host'] ?? '', $blockedDomains)) {
            return false;
        }

        // 허용된 스킴 체크
        $allowedSchemes = ['http', 'https'];
        if (!in_array($parsed['scheme'] ?? '', $allowedSchemes)) {
            return false;
        }

        return true;
    }

    /**
     * URL 접근 가능 여부 확인 (HEAD 요청)
     */
    public function isAccessible(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true, // HEAD 요청
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => $this->userAgent
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }
}
