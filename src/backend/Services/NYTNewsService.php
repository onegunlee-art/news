<?php
/**
 * New York Times News Service
 * 
 * NYT API와 연동하여 뉴스 데이터를 가져오는 서비스
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

namespace App\Services;

use App\Utils\HttpClient;

class NYTNewsService
{
    private string $apiKey;
    private array $config;
    private array $cache = [];
    
    public function __construct()
    {
        $this->config = require __DIR__ . '/../../../config/nyt.php';
        $this->apiKey = $this->config['api_key'];
    }
    
    /**
     * 기사 검색
     * 
     * @param string $query 검색어
     * @param array $options 추가 옵션 (page, sort, begin_date, end_date, fq)
     * @return array
     */
    public function searchArticles(string $query, array $options = []): array
    {
        $params = [
            'q' => $query,
            'api-key' => $this->apiKey,
            'page' => $options['page'] ?? 0,
            'sort' => $options['sort'] ?? 'newest',
        ];
        
        // 날짜 필터
        if (!empty($options['begin_date'])) {
            $params['begin_date'] = $options['begin_date']; // YYYYMMDD 형식
        }
        if (!empty($options['end_date'])) {
            $params['end_date'] = $options['end_date'];
        }
        
        // 필터 쿼리 (fq)
        if (!empty($options['fq'])) {
            $params['fq'] = $options['fq'];
        }
        
        $url = $this->config['endpoints']['article_search'] . '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'search_' . md5($query . json_encode($options)));
    }
    
    /**
     * 섹션별 Top Stories 가져오기
     * 
     * @param string $section 섹션명 (home, world, us, politics, business, technology 등)
     * @return array
     */
    public function getTopStories(string $section = 'home'): array
    {
        // 유효한 섹션인지 확인
        if (!in_array($section, $this->config['sections'])) {
            $section = 'home';
        }
        
        $url = str_replace('{section}', $section, $this->config['endpoints']['top_stories']);
        $url .= '?api-key=' . $this->apiKey;
        
        return $this->makeRequest($url, 'top_' . $section);
    }
    
    /**
     * 가장 인기 있는 기사 가져오기
     * 
     * @param string $type 타입 (viewed, shared, emailed)
     * @param int $period 기간 (1, 7, 30)
     * @return array
     */
    public function getMostPopular(string $type = 'viewed', int $period = 1): array
    {
        // 유효성 검사
        if (!in_array($type, $this->config['most_popular']['types'])) {
            $type = 'viewed';
        }
        if (!in_array($period, $this->config['most_popular']['periods'])) {
            $period = 1;
        }
        
        $url = str_replace(
            ['{type}', '{period}'],
            [$type, $period],
            $this->config['endpoints']['most_popular']
        );
        $url .= '?api-key=' . $this->apiKey;
        
        return $this->makeRequest($url, 'popular_' . $type . '_' . $period);
    }
    
    /**
     * 아카이브 기사 가져오기 (특정 연월)
     * 
     * @param int $year 연도 (예: 2026)
     * @param int $month 월 (1-12)
     * @return array
     */
    public function getArchive(int $year, int $month): array
    {
        $url = str_replace(
            ['{year}', '{month}'],
            [$year, $month],
            $this->config['endpoints']['archive']
        );
        $url .= '?api-key=' . $this->apiKey;
        
        return $this->makeRequest($url, 'archive_' . $year . '_' . $month);
    }
    
    /**
     * 뉴스를 표준 형식으로 변환
     * 
     * @param array $rawData NYT API 응답 데이터
     * @param string $type 데이터 타입 (search, top_stories, popular)
     * @return array
     */
    public function normalizeNews(array $rawData, string $type = 'search'): array
    {
        $articles = [];
        
        switch ($type) {
            case 'search':
                $docs = $rawData['response']['docs'] ?? [];
                foreach ($docs as $doc) {
                    $articles[] = [
                        'id' => $doc['_id'] ?? '',
                        'title' => $doc['headline']['main'] ?? '',
                        'description' => $doc['abstract'] ?? $doc['lead_paragraph'] ?? '',
                        'content' => $doc['lead_paragraph'] ?? '',
                        'url' => $doc['web_url'] ?? '',
                        'image' => $this->extractImage($doc['multimedia'] ?? []),
                        'source' => 'New York Times',
                        'section' => $doc['section_name'] ?? '',
                        'author' => $this->extractAuthor($doc['byline'] ?? []),
                        'published_at' => $doc['pub_date'] ?? '',
                        'keywords' => $this->extractKeywords($doc['keywords'] ?? []),
                    ];
                }
                break;
                
            case 'top_stories':
            case 'popular':
                $results = $rawData['results'] ?? [];
                foreach ($results as $result) {
                    $articles[] = [
                        'id' => $result['uri'] ?? $result['url'] ?? '',
                        'title' => $result['title'] ?? '',
                        'description' => $result['abstract'] ?? '',
                        'content' => $result['abstract'] ?? '',
                        'url' => $result['url'] ?? '',
                        'image' => $this->extractImageFromResults($result['multimedia'] ?? []),
                        'source' => 'New York Times',
                        'section' => $result['section'] ?? '',
                        'author' => $result['byline'] ?? '',
                        'published_at' => $result['published_date'] ?? '',
                        'keywords' => $result['des_facet'] ?? [],
                    ];
                }
                break;
        }
        
        return $articles;
    }
    
    /**
     * API 요청 실행 (캐시 지원)
     */
    private function makeRequest(string $url, string $cacheKey): array
    {
        // 캐시 확인
        if ($this->config['cache']['enabled'] && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() - $cached['time'] < $this->config['cache']['ttl']) {
                return $cached['data'];
            }
        }
        
        try {
            $response = HttpClient::get($url);
            
            if (!$response['success']) {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'API request failed',
                    'data' => []
                ];
            }
            
            $data = $response['data'];
            
            // 캐시 저장
            if ($this->config['cache']['enabled']) {
                $this->cache[$cacheKey] = [
                    'data' => $data,
                    'time' => time()
                ];
            }
            
            return [
                'success' => true,
                'data' => $data
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 검색 결과에서 이미지 추출
     */
    private function extractImage(array $multimedia): ?string
    {
        foreach ($multimedia as $media) {
            if (isset($media['url'])) {
                return 'https://www.nytimes.com/' . $media['url'];
            }
        }
        return null;
    }
    
    /**
     * Top Stories/Popular 결과에서 이미지 추출
     */
    private function extractImageFromResults(array $multimedia): ?string
    {
        foreach ($multimedia as $media) {
            if (isset($media['url']) && $media['format'] === 'Large Thumbnail') {
                return $media['url'];
            }
        }
        // 첫 번째 이미지 반환
        return $multimedia[0]['url'] ?? null;
    }
    
    /**
     * 저자 정보 추출
     */
    private function extractAuthor(array $byline): string
    {
        return $byline['original'] ?? '';
    }
    
    /**
     * 키워드 추출
     */
    private function extractKeywords(array $keywords): array
    {
        return array_map(function($kw) {
            return $kw['value'] ?? '';
        }, $keywords);
    }
    
    /**
     * API 키 유효성 테스트
     */
    public function testApiKey(): array
    {
        $result = $this->getTopStories('home');
        
        if ($result['success']) {
            return [
                'valid' => true,
                'message' => 'NYT API key is valid'
            ];
        }
        
        return [
            'valid' => false,
            'message' => $result['error'] ?? 'Invalid API key'
        ];
    }
}
