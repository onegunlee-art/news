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
    /** @var int|null Last HTTP code from isAccessible (for debugging) */
    private ?int $lastHttpCode = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        // 실제 브라우저 User-Agent 사용 (봇 차단 우회)
        $this->userAgent = $config['user_agent']
            ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    }

    /** 페이월 우회 시 사용할 Googlebot UA */
    private const GOOGLEBOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    /** 페이월 우회를 시도할 도메인 목록 */
    private const PAYWALL_DOMAINS = ['economist.com', 'ft.com', 'foreignaffairs.com'];

    /**
     * URL에서 기사 데이터 추출.
     * 페이월 사이트에서 본문이 짧으면 JSON-LD → Googlebot UA 순으로 전문 재시도.
     */
    public function scrape(string $url): ArticleData
    {
        try {
            $html = $this->fetchHtmlDirect($url);
            if (empty($html)) {
                throw new \RuntimeException("Failed to fetch URL: {$url}");
            }
            $article = $this->parseHtml($url, $html);

            if ($this->isPaywalledDomain($url)
                && mb_strlen($article->getContent()) < self::MIN_CONTENT_LENGTH_WARNING
            ) {
                $enhanced = $this->retryWithPaywallBypass($url, $html, $article);
                if ($enhanced !== null) {
                    return $enhanced;
                }
            }

            return $article;
        } catch (\Throwable $e) {
            if ($this->config['jina_fallback'] ?? false) {
                return $this->scrapeViaJina($url);
            }
            throw $e;
        }
    }

    private function isPaywalledDomain(string $url): bool
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        foreach (self::PAYWALL_DOMAINS as $domain) {
            if (str_contains($host, $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 페이월로 본문이 짧을 때 전문 획득 시도:
     *  1) 원본 HTML의 JSON-LD articleBody
     *  2) Googlebot UA로 재요청
     */
    private function retryWithPaywallBypass(string $url, string $originalHtml, ArticleData $shortArticle): ?ArticleData
    {
        $originalLen = mb_strlen($shortArticle->getContent());

        // Strategy 1: JSON-LD articleBody (추가 요청 없이 원본 HTML에서 추출)
        $jsonLdBody = $this->extractArticleBodyFromJsonLd($originalHtml);
        if ($jsonLdBody !== null && mb_strlen($jsonLdBody) > $originalLen) {
            error_log("[WebScraperService] Paywall bypass: JSON-LD articleBody found (" . mb_strlen($jsonLdBody) . " chars) for {$url}");
            return $this->buildEnhancedArticle($shortArticle, $jsonLdBody, 'json-ld');
        }

        // Strategy 2: Googlebot UA로 재요청
        try {
            $botHtml = $this->fetchHtmlWithUA($url, self::GOOGLEBOT_UA);
            if (!empty($botHtml)) {
                $botArticle = $this->parseHtml($url, $botHtml);
                if (mb_strlen($botArticle->getContent()) > $originalLen) {
                    error_log("[WebScraperService] Paywall bypass: Googlebot UA got more content (" . mb_strlen($botArticle->getContent()) . " chars) for {$url}");
                    return $botArticle;
                }
                $jsonLdBody2 = $this->extractArticleBodyFromJsonLd($botHtml);
                if ($jsonLdBody2 !== null && mb_strlen($jsonLdBody2) > $originalLen) {
                    error_log("[WebScraperService] Paywall bypass: Googlebot JSON-LD articleBody (" . mb_strlen($jsonLdBody2) . " chars) for {$url}");
                    return $this->buildEnhancedArticle($shortArticle, $jsonLdBody2, 'googlebot-json-ld');
                }
            }
        } catch (\Throwable $e) {
            error_log("[WebScraperService] Googlebot UA retry failed for {$url}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * JSON-LD structured data에서 articleBody 추출
     */
    private function extractArticleBodyFromJsonLd(string $html): ?string
    {
        if (!preg_match_all('/<script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return null;
        }
        foreach ($matches[1] as $jsonStr) {
            $decoded = json_decode(trim($jsonStr), true);
            if (!is_array($decoded)) {
                continue;
            }
            $items = isset($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $body = $item['articleBody'] ?? null;
                if (is_string($body) && mb_strlen($body) > 200) {
                    return $this->cleanNewsletterBlocks(trim($body));
                }
            }
        }
        return null;
    }

    /**
     * 특정 User-Agent로 HTML 요청
     */
    private function fetchHtmlWithUA(string $url, string $ua): string
    {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 60,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
        ]);
        $html = \curl_exec($ch);
        $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($httpCode !== 200 || $html === false) {
            return '';
        }
        return $html;
    }

    /**
     * 짧은 ArticleData의 메타를 유지하면서 content만 교체한 새 ArticleData 생성
     */
    private function buildEnhancedArticle(ArticleData $original, string $fullContent, string $via): ArticleData
    {
        $metadata = [
            'scraped_at' => date('c'),
            'content_length' => mb_strlen($fullContent),
            'paywall_bypass' => $via,
        ];

        return new ArticleData(
            url: $original->getUrl(),
            title: $original->getTitle(),
            content: $fullContent,
            description: $original->getDescription(),
            author: $original->getAuthor(),
            publishedAt: $original->getPublishedAt(),
            imageUrl: $original->getImageUrl(),
            language: $original->getLanguage(),
            source: $original->getSource(),
            metadata: $metadata,
            subheadings: $original->getSubheadings()
        );
    }

    /**
     * HTML 가져오기 - cURL 직접 요청 (브라우저 수준 헤더로 페이월/봇 차단 우회)
     */
    private function fetchHtmlDirect(string $url): string
    {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 60,
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

        $html = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);
        \curl_close($ch);

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
     * Jina AI Reader로 URL 콘텐츠 가져오기 (cURL 차단 사이트 우회)
     * @return array{title?: string, description?: string, content?: string, url?: string, image?: string}
     */
    private function fetchViaJina(string $url): array
    {
        $jinaUrl = 'https://r.jina.ai/' . $url;
        $ch = \curl_init($jinaUrl);
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 60,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Return-Format: json',
            ],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
        ]);

        $body = \curl_exec($ch);
        $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || $body === false || $body === '') {
            throw new \RuntimeException("Jina Reader failed (HTTP {$httpCode}) for: {$url}");
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Jina Reader returned invalid JSON for: {$url}");
        }

        // 지원하는 응답 형식: { "data": { ... } } 또는 최상위 필드
        $data = $decoded['data'] ?? $decoded;
        if (!is_array($data)) {
            throw new \RuntimeException("Jina Reader response missing data for: {$url}");
        }

        return $data;
    }

    /**
     * Jina AI Reader로 기사 추출 후 ArticleData 구성
     */
    private function scrapeViaJina(string $url): ArticleData
    {
        $data = $this->fetchViaJina($url);

        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $description = $data['description'] ?? null;
        $imageUrl = null;
        if (!empty($data['image'])) {
            $imageUrl = is_string($data['image']) ? $data['image'] : ($data['image']['src'] ?? null);
        }
        if ($imageUrl === null && !empty($data['images']) && is_array($data['images'])) {
            $img = $data['images'][0] ?? $data['images'];
            $imageUrl = is_string($img) ? $img : ($img['src'] ?? null);
        }

        if ($title === '' && $content === '') {
            throw new \RuntimeException("Jina Reader returned no title or content for: {$url}");
        }

        if ($title === '' && $content !== '') {
            $title = mb_substr(strip_tags($content), 0, 80) . '…';
        }

        $metadata = ['via_jina' => true];
        $this->addShortContentWarning($url, $content, $metadata);

        return new ArticleData(
            url: $data['url'] ?? $url,
            title: $title,
            content: $content,
            description: $description,
            author: $data['author'] ?? null,
            publishedAt: $data['publishedDate'] ?? $data['published_at'] ?? null,
            imageUrl: $imageUrl,
            language: $data['language'] ?? null,
            source: $this->extractSourceFromUrl($url),
            metadata: $metadata,
            subheadings: []
        );
    }

    /**
     * URL에서 도메인을 소스명으로 추출
     */
    private function extractSourceFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return null;
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
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
        $title = $this->extractTitle($xpath, $url);
        $description = $this->extractDescription($xpath, $doc, $url);
        $author = $this->extractAuthor($xpath);
        $publishedAt = $this->extractPublishedDate($xpath, $html);
        $imageUrl = $this->extractMetaContent($xpath, 'og:image');
        $language = $this->detectLanguage($xpath, $html);
        $source = $this->extractSource($xpath, $url);

        // 본문 추출 (Readability 스타일)
        $content = $this->extractContent($xpath, $doc);

        // 소제목 별도 추출 (Foreign Affairs 볼드 등)
        $subheadings = $this->extractSubheadings($xpath, $doc, $url);

        $metadata = [
            'scraped_at' => date('c'),
            'content_length' => strlen($content),
        ];
        $this->addShortContentWarning($url, $content, $metadata);

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
            metadata: $metadata,
            subheadings: $subheadings
        );
    }

    /** 최소 본문 길이 (이하면 불완전 본문 경고) */
    private const MIN_CONTENT_LENGTH_WARNING = 500;

    /**
     * economist.com / ft.com 등에서 본문이 너무 짧으면 메타에 경고 추가 및 로그
     */
    private function addShortContentWarning(string $url, string $content, array &$metadata): void
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $len = mb_strlen($content);
        if ($len >= self::MIN_CONTENT_LENGTH_WARNING) {
            return;
        }
        if (!str_contains($host, 'economist.com') && !str_contains($host, 'ft.com')) {
            return;
        }
        $metadata['content_short_warning'] = true;
        error_log(sprintf(
            '[WebScraperService] Short or incomplete article body (%d chars) for %s - paywall or ad break may have truncated content.',
            $len,
            $url
        ));
    }

    /** The Economist 섹션 라벨 prefix (제목에서 제거, 단독이면 폐기) */
    private const ECONOMIST_SECTION_PREFIXES = [
        'Leaders | ',
        'Leaders |',
        'Briefing | ',
        'Briefing |',
        'Finance & economics | ',
        'Finance & economics |',
        'Science and technology | ',
        'Science and technology |',
        'Culture | ',
        'Culture |',
        'By invitation | ',
        'By invitation |',
        'Obituary | ',
        'Obituary |',
        'International | ',
        'International |',
        'United States | ',
        'United States |',
        'The Americas | ',
        'The Americas |',
        'Middle East & Africa | ',
        'Middle East & Africa |',
        'Europe | ',
        'Europe |',
        'Asia | ',
        'Asia |',
        'China | ',
        'China |',
        'Britain | ',
        'Britain |',
        'Print edition | ',
        'Print edition |',
    ];

    /**
     * Economist 제목 정규화: 섹션 prefix 제거, 단독 라벨이면 빈 문자열 반환
     */
    private function normalizeEconomistTitle(string $raw, string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (!str_contains($host, 'economist.com')) {
            return trim($raw);
        }
        $trimmed = trim($raw);
        foreach (self::ECONOMIST_SECTION_PREFIXES as $prefix) {
            if (stripos($trimmed, $prefix) === 0) {
                $trimmed = trim(mb_substr($trimmed, mb_strlen($prefix)));
                break;
            }
            $prefixNoSpace = rtrim($prefix);
            if (stripos($trimmed, $prefixNoSpace) === 0) {
                $trimmed = trim(mb_substr($trimmed, mb_strlen($prefixNoSpace)));
                break;
            }
        }
        // 단독 섹션 라벨(너무 짧거나 알려진 라벨만 있음)이면 제목으로 쓰지 않음
        if ($trimmed === '' || $this->isEconomistSectionLabelOnly($trimmed)) {
            return '';
        }
        return $trimmed;
    }

    /**
     * "Leaders", "Briefing" 등 섹션 이름만 있는지 여부
     */
    private function isEconomistSectionLabelOnly(string $title): bool
    {
        $t = trim($title);
        if ($t === '') {
            return true;
        }
        $len = mb_strlen($t);
        if ($len > 50) {
            return false;
        }
        $lower = mb_strtolower($t);
        $standaloneLabels = [
            'leaders', 'briefing', 'finance & economics', 'science and technology',
            'culture', 'by invitation', 'obituary', 'international', 'united states',
            'the americas', 'middle east & africa', 'europe', 'asia', 'china', 'britain',
            'print edition', 'daily summary', 'essay', 'special report',
        ];
        foreach ($standaloneLabels as $label) {
            if ($lower === $label) {
                return true;
            }
        }
        return false;
    }

    /**
     * 제목 추출 (Economist일 경우 섹션 prefix 제거, 단독 라벨 폐기)
     */
    private function extractTitle(\DOMXPath $xpath, string $url = ''): string
    {
        $candidates = [];

        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            $candidates[] = trim($ogTitle->item(0)->nodeValue);
        }
        $twitterTitle = $xpath->query('//meta[@name="twitter:title"]/@content');
        if ($twitterTitle->length > 0) {
            $candidates[] = trim($twitterTitle->item(0)->nodeValue);
        }
        $h1 = $xpath->query('//h1');
        if ($h1->length > 0) {
            $candidates[] = trim($h1->item(0)->textContent);
        }
        $titleTag = $xpath->query('//title');
        if ($titleTag->length > 0) {
            $candidates[] = trim($titleTag->item(0)->textContent);
        }

        $isEconomist = str_contains(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), 'economist.com');
        foreach ($candidates as $raw) {
            if ($raw === '') {
                continue;
            }
            $normalized = $isEconomist ? $this->normalizeEconomistTitle($raw, $url) : trim($raw);
            if ($normalized !== '') {
                return $normalized;
            }
        }
        return '';
    }

    /**
     * 설명/부제목 추출 (Economist일 경우 standfirst/deck 우선, 없으면 og:description 등)
     */
    private function extractDescription(\DOMXPath $xpath, \DOMDocument $doc, string $url): ?string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $isEconomist = str_contains($host, 'economist.com');

        if ($isEconomist) {
            $standfirst = $this->extractEconomistStandfirst($xpath, $doc);
            if ($standfirst !== null && trim($standfirst) !== '') {
                return trim($standfirst);
            }
        }

        $ogDesc = $this->extractMetaContent($xpath, 'og:description');
        if ($ogDesc !== null && trim($ogDesc) !== '') {
            return trim($ogDesc);
        }
        $twitterDesc = $this->extractMetaContent($xpath, 'twitter:description');
        if ($twitterDesc !== null && trim($twitterDesc) !== '') {
            return trim($twitterDesc);
        }
        $metaDesc = $this->extractMetaContent($xpath, 'description');
        if ($metaDesc !== null && trim($metaDesc) !== '') {
            return trim($metaDesc);
        }
        return null;
    }

    /**
     * The Economist 페이지에서 standfirst/deck(도입 요약) 추출
     */
    private function extractEconomistStandfirst(\DOMXPath $xpath, \DOMDocument $doc): ?string
    {
        $selectors = [
            "//*[contains(@class, 'article__standfirst')]",
            "//*[contains(@class, 'standfirst')]",
            "//*[contains(@class, 'article__deck')]",
            "//*[contains(@class, 'deck')]",
            "//*[contains(@data-testid, 'standfirst')]",
            "//*[contains(@data-testid, 'deck')]",
            "//p[contains(@class, 'article__lead')]",
            "//*[@itemprop='description']",
        ];
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent ?? '');
                if ($text !== '' && mb_strlen($text) >= 20) {
                    return $text;
                }
            }
        }
        return null;
    }

    /**
     * 소제목(Subheading) 별도 추출
     * - Foreign Affairs: 볼드(<strong>/<b>) 안의 ALL CAPS 텍스트
     * - 일반: <h2>, <h3> 등 헤딩 태그
     * @return string[]
     */
    private function extractSubheadings(\DOMXPath $xpath, \DOMDocument $doc, string $url): array
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $subheadings = [];

        // Foreign Affairs: 본문 내 볼드 ALL CAPS 추출
        if (str_contains($host, 'foreignaffairs.com')) {
            $boldNodes = $xpath->query('//article//strong | //article//b | //*[contains(@class, "article")]//strong | //*[contains(@class, "article")]//b');
            foreach ($boldNodes as $node) {
                $text = trim($node->textContent);
                if ($this->isLikelySubheading($text)) {
                    if (!in_array($text, $subheadings, true)) {
                        $subheadings[] = $text;
                    }
                }
            }
            return $subheadings;
        }

        // 일반 기사: h2, h3 태그에서 추출
        $headingNodes = $xpath->query('//article//h2 | //article//h3 | //*[contains(@class, "article")]//h2 | //*[contains(@class, "article")]//h3');
        foreach ($headingNodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '' && mb_strlen($text) >= 3 && mb_strlen($text) <= 100) {
                if (!in_array($text, $subheadings, true)) {
                    $subheadings[] = $text;
                }
            }
        }

        return $subheadings;
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
        // 불필요한 요소 제거 (구독/뉴스레터/광고 블록 포함, The Economist 등 강화)
        $removeSelectors = [
            '//script', '//style', '//nav', '//header', '//footer',
            '//aside', '//form', '//iframe', '//noscript',
            '//*[contains(@class, "ad")]', '//*[contains(@class, "advertisement")]',
            '//*[contains(@class, "sidebar")]', '//*[contains(@class, "comment")]',
            '//*[contains(@class, "related")]', '//*[contains(@class, "share")]',
            '//*[contains(@id, "ad")]', '//*[contains(@id, "comment")]',
            '//*[contains(@class, "newsletter")]', '//*[contains(@class, "subscribe")]',
            '//*[contains(@class, "signup")]', '//*[contains(@class, "email-signup")]',
            '//*[contains(@id, "newsletter")]', '//*[contains(@id, "subscribe")]',
            // The Economist / FT 등: CTA, 페이월 유도, 추천 블록
            '//*[contains(@class, "paywall")]', '//*[contains(@class, "paywall-cta")]',
            '//*[contains(@class, "cta")]', '//*[contains(@class, "recommended")]',
            '//*[contains(@class, "inline-ad")]', '//*[contains(@class, "article-recommendations")]',
            '//*[contains(@class, "sign-in-gate")]', '//*[contains(@data-component, "SignInGate")]',
        ];

        foreach ($removeSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $element->parentNode->removeChild($element);
            }
        }

        // Economist 전용 본문 셀렉터 (먼저 시도)
        $currentUrl = $doc->documentURI ?? '';
        $currentHost = strtolower(parse_url($currentUrl, PHP_URL_HOST) ?? '');
        
        if (str_contains($currentHost, 'economist.com')) {
            $economistSelectors = [
                '//*[contains(@class, "article__body")]',
                '//*[contains(@class, "article-body")]',
                '//*[contains(@class, "ds-body")]',
                '//*[@data-component="ArticleBody"]',
                '//article//*[contains(@class, "body")]',
                '//*[contains(@class, "layout-article-body")]',
                '//*[contains(@class, "css-") and contains(@class, "body")]',
            ];
            foreach ($economistSelectors as $selector) {
                $elements = $xpath->query($selector);
                if ($elements->length > 0) {
                    $content = $this->extractTextFromNode($elements->item(0));
                    if (strlen($content) > 200) {
                        return $this->cleanNewsletterBlocks($content);
                    }
                }
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
                    return $this->cleanNewsletterBlocks($content);
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
            return $this->cleanNewsletterBlocks($this->extractTextFromNode($bestParent));
        }

        // 최후의 수단: body 전체
        $body = $xpath->query('//body');
        if ($body->length > 0) {
            return $this->cleanNewsletterBlocks($this->extractTextFromNode($body->item(0)));
        }

        return '';
    }

    /**
     * 구독/뉴스레터 유도 블록 제거 (추출된 텍스트 후처리)
     */
    private function cleanNewsletterBlocks(string $text): string
    {
        $patterns = [
            '/Subscribe to\s+[^\n]{0,200}/iu',
            '/Sign Up\s*[→\->]?\s*/iu',
            '/Enter your email[^.]{0,100}\.?/iu',
            '/delivered to your inbox[^.]{0,100}\.?/iu',
            '/delivered free to your inbox[^.]{0,100}\.?/iu',
            '/Our editors\'?\s*top picks[^.]{0,150}\.?/iu',
            '/Get the latest[^.]{0,150}\.?/iu',
            // The Economist 전용 (강화)
            '/This article appeared in[^.]{0,200}\.?/iu',
            '/For more expert analysis[^.]{0,200}\.?/iu',
            '/Subscribe to The Economist[^.]{0,150}\.?/iu',
            '/Sign up for our[^.]{0,150}\.?/iu',
            '/Recommended for you[^.]{0,150}\.?/iu',
            '/Read more from[^.]{0,150}\.?/iu',
            '/This article is from the Economist[^.]{0,200}\.?/iu',
            '/View all\s+\d*\s*(?:stories|articles)?[^.]{0,100}\.?/iu',
            '/Explore more[^.]{0,100}\.?/iu',
            '/More from The Economist[^.]{0,150}\.?/iu',
            '/You\'ve seen the news[^.]{0,200}\.?/iu',
            '/Keep reading with one of these options[^.]{0,200}\.?/iu',
            '/Already a subscriber\?[^.]{0,100}\.?/iu',
            '/\bAdvertisement\s*[\s\S]{0,100}?(?=\n\n|\n[A-Z]|$)/iu',
        ];

        $result = $text;
        foreach ($patterns as $pattern) {
            $result = preg_replace($pattern, '', $result);
        }
        // 정리: 연속 빈 줄 3개 이상 → 2개로
        $result = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $result);
        return trim($result);
    }

    /**
     * 노드에서 텍스트 추출 (헤딩과 문단 경계 보존)
     */
    private function extractTextFromNode(\DOMNode $node): string
    {
        $text = $this->extractTextWithStructure($node);
        
        // 연속 줄바꿈 정리 (최대 2개까지 유지)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);
        
        return $text;
    }

    /**
     * DOM 노드에서 구조 보존하며 텍스트 추출
     * - h1~h6, p 태그 경계에서 줄바꿈 삽입
     * - ALL CAPS 라인은 별도 줄로 유지
     */
    private function extractTextWithStructure(\DOMNode $node): string
    {
        $result = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->textContent;
                // 연속 공백은 단일 공백으로 (줄바꿈 보존)
                $text = preg_replace('/[ \t]+/', ' ', $text);
                $result .= $text;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);
                
                // 블록 요소 전에 줄바꿈
                $blockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'article', 'section', 'blockquote', 'li', 'br'];
                if (in_array($tagName, $blockTags)) {
                    $result = rtrim($result) . "\n";
                }
                
                // 헤딩 태그는 별도 줄로 강조
                if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $headingText = trim($child->textContent);
                    if ($headingText !== '') {
                        // ALL CAPS 헤딩 또는 일반 헤딩 - 별도 줄로 보존
                        $result .= "\n[HEADING] " . $headingText . "\n\n";
                    }
                } elseif (in_array($tagName, ['strong', 'b'])) {
                    // Foreign Affairs 등: 볼드 안의 ALL CAPS 텍스트를 소제목으로 감지
                    $innerText = trim($child->textContent);
                    if ($this->isLikelySubheading($innerText)) {
                        $result .= "\n[SUBHEADING] " . $innerText . "\n\n";
                    } else {
                        $result .= $this->extractTextWithStructure($child);
                    }
                } else {
                    // 일반 요소에서 ALL CAPS 짧은 라인 감지 (소제목 후보)
                    $innerText = trim($child->textContent);
                    if ($this->isLikelySubheading($innerText)) {
                        $result .= "\n[SUBHEADING] " . $innerText . "\n\n";
                    } else {
                        $result .= $this->extractTextWithStructure($child);
                    }
                }
                
                // 블록 요소 후에 줄바꿈
                if (in_array($tagName, $blockTags)) {
                    $result = rtrim($result) . "\n";
                }
            }
        }
        
        return $result;
    }

    /**
     * ALL CAPS 또는 짧은 독립 라인이 소제목일 가능성 판단
     */
    private function isLikelySubheading(string $text): bool
    {
        $text = trim($text);
        
        // 빈 문자열이면 아님
        if ($text === '') {
            return false;
        }
        
        // 3~60자 사이의 짧은 텍스트
        $len = mb_strlen($text);
        if ($len < 3 || $len > 60) {
            return false;
        }
        
        // 문장 끝 마침표가 없어야 함 (문장이 아닌 헤딩)
        if (preg_match('/[.。!?]$/', $text)) {
            return false;
        }
        
        // ALL CAPS인지 확인 (영문 기준)
        $upperText = mb_strtoupper($text);
        $isAllCaps = ($text === $upperText) && preg_match('/[A-Z]/', $text);
        
        return $isAllCaps;
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
     * Last HTTP code from isAccessible (for debugging). Null before first call.
     */
    public function getLastHttpCode(): ?int
    {
        return $this->lastHttpCode;
    }

    /**
     * URL 접근 가능 여부 확인 (HEAD → GET fallback).
     * skip_head_domains에 포함된 호스트는 HEAD를 건너뛰고 GET만 사용 (일부 사이트 HEAD 차단 대응).
     */
    public function isAccessible(string $url): bool
    {
        $this->lastHttpCode = null;
        $host = parse_url($url, PHP_URL_HOST);
        $skipHeadDomains = $this->config['skip_head_domains'] ?? [];
        $skipHead = $host !== null && in_array($host, $skipHeadDomains, true);

        if ($skipHead) {
            $ok = $this->isAccessibleGetOnly($url);
            if ($ok) {
                return true;
            }
            if ($this->config['jina_fallback'] ?? false) {
                return true;
            }
            return false;
        }

        // HEAD 요청 먼저 시도
        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
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

        \curl_exec($ch);
        $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastHttpCode = $httpCode;
        \curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400) {
            return true;
        }

        // HEAD가 실패하면 무조건 GET으로 한 번 재시도 (HEAD를 막는 사이트 대응)
        $getOk = $this->isAccessibleGetOnly($url);
        if ($getOk) {
            return true;
        }
        // cURL 모두 실패했지만 Jina fallback이 켜져 있으면 접근 가능으로 간주
        if ($this->config['jina_fallback'] ?? false) {
            return true;
        }
        return false;
    }

    /**
     * GET 요청만으로 접근 가능 여부 확인 (HEAD 미사용)
     */
    private function isAccessibleGetOnly(string $url): bool
    {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
        ]);
        \curl_exec($ch);
        $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastHttpCode = $httpCode;
        \curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }
}
