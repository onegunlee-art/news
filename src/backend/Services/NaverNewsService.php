<?php
/**
 * 네이버 뉴스 서비스 클래스
 * 
 * 네이버 뉴스 검색 API와의 통신을 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 * @see https://developers.naver.com/docs/search/news/
 */

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\NewsApiInterface;
use App\Models\News;
use App\Utils\HttpClient;
use RuntimeException;

/**
 * NaverNewsService 클래스
 */
final class NaverNewsService implements NewsApiInterface
{
    private array $config;
    private HttpClient $httpClient;

    /**
     * 생성자
     */
    public function __construct()
    {
        $configPath = dirname(__DIR__, 3) . '/config/naver.php';
        
        if (!file_exists($configPath)) {
            throw new RuntimeException('Naver configuration file not found');
        }
        
        $this->config = require $configPath;
        $this->httpClient = (new HttpClient())
            ->setDefaultHeaders([
                'X-Naver-Client-Id' => $this->config['client_id'],
                'X-Naver-Client-Secret' => $this->config['client_secret'],
            ])
            ->setTimeout($this->config['http']['timeout'] ?? 10);
    }

    /**
     * {@inheritdoc}
     */
    public function search(
        string $query,
        int $display = 10,
        int $start = 1,
        string $sort = 'date'
    ): array {
        // 파라미터 검증
        $display = min($display, $this->config['news']['max_display']);
        $start = min($start, $this->config['news']['max_start']);
        $sort = in_array($sort, ['sim', 'date']) ? $sort : 'date';
        
        $response = $this->httpClient->get($this->config['news']['base_url'], [
            'query' => $query,
            'display' => $display,
            'start' => $start,
            'sort' => $sort,
        ]);
        
        if (!$response->isSuccess()) {
            $error = $response->json();
            throw new RuntimeException(
                'Naver API Error: ' . ($error['errorMessage'] ?? 'Unknown error')
            );
        }
        
        $data = $response->json();
        
        // 응답 데이터 가공
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = $this->parseNewsItem($item);
        }
        
        return [
            'total' => $data['total'] ?? 0,
            'start' => $data['start'] ?? $start,
            'display' => $data['display'] ?? $display,
            'items' => $items,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getLatest(int $limit = 10): array
    {
        // 주요 뉴스 카테고리로 최신 뉴스 검색
        $result = $this->search('속보', $limit, 1, 'date');
        
        return $result['items'];
    }

    /**
     * {@inheritdoc}
     */
    public function getByCategory(string $category, int $limit = 10): array
    {
        // 카테고리를 검색어로 사용
        $categoryKeywords = [
            'politics' => '정치',
            'economy' => '경제',
            'society' => '사회',
            'culture' => '문화',
            'world' => '국제',
            'sports' => '스포츠',
            'it' => 'IT 기술',
            'science' => '과학',
        ];
        
        $query = $categoryKeywords[$category] ?? $category;
        $result = $this->search($query, $limit, 1, 'date');
        
        // 카테고리 정보 추가
        foreach ($result['items'] as &$item) {
            $item['category'] = $category;
        }
        
        return $result['items'];
    }

    /**
     * 뉴스 아이템 파싱
     */
    private function parseNewsItem(array $item): array
    {
        return [
            'title' => $this->cleanHtml($item['title'] ?? ''),
            'description' => $this->cleanHtml($item['description'] ?? ''),
            'url' => $item['originallink'] ?? $item['link'] ?? '',
            'link' => $item['link'] ?? '',
            'source' => $this->extractSource($item['originallink'] ?? ''),
            'published_at' => $this->parseDate($item['pubDate'] ?? ''),
        ];
    }

    /**
     * HTML 태그 및 엔티티 제거
     */
    private function cleanHtml(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * URL에서 출처(언론사) 추출
     */
    private function extractSource(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!$host) {
            return null;
        }
        
        // www. 제거
        $host = preg_replace('/^www\./', '', $host);
        
        // 알려진 언론사 매핑
        $sources = [
            'chosun.com' => '조선일보',
            'donga.com' => '동아일보',
            'joongang.co.kr' => '중앙일보',
            'hani.co.kr' => '한겨레',
            'khan.co.kr' => '경향신문',
            'hankyung.com' => '한국경제',
            'mk.co.kr' => '매일경제',
            'sedaily.com' => '서울경제',
            'mt.co.kr' => '머니투데이',
            'edaily.co.kr' => '이데일리',
            'yonhapnews.co.kr' => '연합뉴스',
            'newsis.com' => '뉴시스',
            'news1.kr' => '뉴스1',
            'yna.co.kr' => '연합뉴스',
            'ytn.co.kr' => 'YTN',
            'sbs.co.kr' => 'SBS',
            'kbs.co.kr' => 'KBS',
            'mbc.co.kr' => 'MBC',
            'jtbc.co.kr' => 'JTBC',
            'tvchosun.com' => 'TV조선',
            'nocutnews.co.kr' => '노컷뉴스',
            'ohmynews.com' => '오마이뉴스',
            'mediatoday.co.kr' => '미디어오늘',
            'etnews.com' => '전자신문',
            'zdnet.co.kr' => 'ZDNet Korea',
        ];
        
        foreach ($sources as $domain => $name) {
            if (str_contains($host, $domain)) {
                return $name;
            }
        }
        
        // 매핑되지 않은 경우 도메인 반환
        return $host;
    }

    /**
     * 날짜 문자열 파싱
     */
    private function parseDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }
        
        try {
            $date = new \DateTimeImmutable($dateStr);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * News 모델 객체 배열로 변환
     */
    public function searchAsModels(
        string $query,
        int $display = 10,
        int $start = 1,
        string $sort = 'date'
    ): array {
        $result = $this->search($query, $display, $start, $sort);
        
        $models = [];
        foreach ($result['items'] as $item) {
            $models[] = News::fromNaverApi($item);
        }
        
        return [
            'total' => $result['total'],
            'items' => $models,
        ];
    }

    /**
     * 여러 키워드로 뉴스 검색
     */
    public function searchMultiple(array $keywords, int $limitPerKeyword = 5): array
    {
        $allItems = [];
        
        foreach ($keywords as $keyword) {
            try {
                $result = $this->search($keyword, $limitPerKeyword, 1, 'date');
                
                foreach ($result['items'] as $item) {
                    $item['search_keyword'] = $keyword;
                    $allItems[] = $item;
                }
            } catch (RuntimeException) {
                // 개별 검색 실패는 무시하고 계속 진행
                continue;
            }
        }
        
        return $allItems;
    }

    /**
     * API 호출 가능 여부 확인
     */
    public function isAvailable(): bool
    {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            return false;
        }
        
        try {
            $this->search('test', 1, 1, 'date');
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
