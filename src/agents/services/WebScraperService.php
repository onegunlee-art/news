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
        // 실제 브라우저 User-Agent 사용 (봇 차단 우회)
        $this->userAgent = $config['user_agent']
            ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
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
     * HTML 가져오기 (브라우저 수준 헤더로 페이월/봇 차단 우회)
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
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9,ko-KR;q=0.8,ko;q=0.7',
                'Accept-Encoding: gzip, deflate, br',
                'Cache-Control: no-cache',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
            ],
            CURLOPT_ENCODING => '',  // 자동 gzip/deflate 처리
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
            CURLOPT_COOKIEJAR => '',   // 쿠키 허용
            CURLOPT_COOKIEFILE => '',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            // 403/451 등 → 봇 차단 가능성
            if ($httpCode === 403 || $httpCode === 451) {
                throw new \RuntimeException("기사 접근이 차단되었습니다 (HTTP {$httpCode}). 페이월이나 봇 차단이 적용된 사이트일 수 있습니다.");
            }
            throw new \RuntimeException("HTTP error {$httpCode}: {$error}");
        }

        return $html ?: '';
    }

    /**
     * HTML 파싱하여 기사 데이터 추출
     */
    private function parseHtml(string $url, string $html): ArticleData
    {
        // DOM 파싱 (PHP 8.2+ 호환: HTML-ENTITIES 사용 안함)
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        // UTF-8 메타 태그 삽입으로 인코딩 지정
        $htmlWithMeta = '<?xml encoding="UTF-8">' . $html;
        $doc->loadHTML($htmlWithMeta, LIBXML_NOERROR | LIBXML_NOWARNING);
        // 삽입한 xml 프로세싱 인스트럭션 제거
        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === XML_PI_NODE) {
                $doc->removeChild($child);
                break;
            }
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // 메타데이터 추출
        $title = $this->extractTitle($xpath);
        $description = $this->extractMetaContent($xpath, 'description');
        $author = $this->extractAuthor($xpath);
        $publishedAt = $this->extractPublishedDate($xpath, $html);
        $imageUrl = $this->extractMetaContent($xpath, 'og:image');
        $language = $this->detectLanguage($xpath, $html);
        $source = $this->extractSource($xpath, $url);

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
            source: $source,
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
     * 출처 추출: og:site_name 우선, 없으면 URL 호스트(도메인)
     */
    private function extractSource(\DOMXPath $xpath, string $url): ?string
    {
        $siteName = $this->extractMetaContent($xpath, 'og:site_name');
        if ($siteName !== null && $siteName !== '') {
            return $siteName;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null || trim((string) $host) === '') {
            return null;
        }
        return trim((string) $host);
    }

    /**
     * 작성자 추출: author, article:author, byline 순으로 시도
     */
    private function extractAuthor(\DOMXPath $xpath): ?string
    {
        $author = $this->extractMetaContent($xpath, 'author');
        if ($author !== null && $author !== '') {
            return $author;
        }
        $author = $this->extractMetaContent($xpath, 'article:author');
        if ($author !== null && $author !== '') {
            return $author;
        }
        return $this->extractMetaContent($xpath, 'byline');
    }

    /**
     * 발행일 추출 (메타/time 우선, JSON-LD datePublished 보강)
     */
    private function extractPublishedDate(\DOMXPath $xpath, string $html): ?string
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
                if ($date !== '') {
                    return $date;
                }
            }
        }

        // JSON-LD에서 datePublished 추출
        if (preg_match_all('/<script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $decoded = json_decode(trim($jsonStr), true);
                if (!is_array($decoded)) {
                    continue;
                }
                $items = isset($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
                foreach ($items as $item) {
                    if (isset($item['datePublished']) && is_string($item['datePublished']) && $item['datePublished'] !== '') {
                        return $item['datePublished'];
                    }
                    if (isset($item['@type']) && (strcasecmp((string) $item['@type'], 'NewsArticle') === 0 || strcasecmp((string) $item['@type'], 'Article') === 0)
                        && isset($item['datePublished']) && is_string($item['datePublished'])) {
                        return $item['datePublished'];
                    }
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

        // 본문 후보 찾기 (Foreign Affairs, NYT, Reuters 등 주요 매체 대응)
        $contentSelectors = [
            '//*[@itemprop="articleBody"]',
            '//article',
            '//*[contains(@class, "article-body")]',
            '//*[contains(@class, "article-content")]',
            '//*[contains(@class, "article__body")]',
            '//*[contains(@class, "article-text")]',
            '//*[contains(@class, "post-content")]',
            '//*[contains(@class, "entry-content")]',
            '//*[contains(@class, "story-body")]',
            '//*[contains(@class, "paywall")]',
            '//main',
            '//*[contains(@class, "content")]',
            '//*[@role="main"]',
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
     * URL 접근 가능 여부 확인 (HEAD → GET fallback)
     */
    public function isAccessible(string $url): bool
    {
        // HEAD 요청 먼저 시도
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400) {
            return true;
        }

        // HEAD가 실패하면 GET으로 재시도 (일부 사이트는 HEAD를 차단)
        if ($httpCode === 403 || $httpCode === 405 || $httpCode === 0) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ],
                CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 400;
        }

        return false;
    }
}
