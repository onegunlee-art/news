<?php
/**
 * 뉴스 서비스 클래스
 * 
 * 뉴스 관련 비즈니스 로직을 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\News;
use App\Repositories\NewsRepository;
use RuntimeException;

/**
 * NewsService 클래스
 */
final class NewsService
{
    private NewsRepository $newsRepository;
    private NaverNewsService $naverNewsService;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->newsRepository = new NewsRepository();
        $this->naverNewsService = new NaverNewsService();
    }

    /**
     * 뉴스 검색
     */
    public function search(string $query, int $page = 1, int $perPage = 20): array
    {
        // 1. 먼저 네이버 API에서 검색
        $start = (($page - 1) * $perPage) + 1;
        
        try {
            $apiResult = $this->naverNewsService->search($query, $perPage, $start, 'date');
            
            // 2. 검색 결과를 DB에 저장 (중복 제외)
            foreach ($apiResult['items'] as $item) {
                $news = News::fromArray($item);
                $this->newsRepository->saveNews($news);
            }
            
            return [
                'items' => $apiResult['items'],
                'total' => $apiResult['total'],
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($apiResult['total'] / $perPage),
                'source' => 'naver_api',
            ];
        } catch (RuntimeException $e) {
            // API 실패 시 DB에서 검색
            $dbResult = $this->newsRepository->paginate($page, $perPage, [], 'published_at', 'DESC');
            
            // LIKE 검색으로 필터링
            $items = $this->newsRepository->searchLike($query, $perPage, ($page - 1) * $perPage);
            $total = $this->newsRepository->countSearch($query);
            
            return [
                'items' => array_map(fn($item) => News::fromArray($item)->toJson(), $items),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'source' => 'database',
                'api_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 최신 뉴스 목록 조회
     */
    public function getLatestNews(int $limit = 20): array
    {
        try {
            // 네이버 API에서 최신 뉴스 가져오기
            $items = $this->naverNewsService->getLatest($limit);
            
            // DB에 저장
            foreach ($items as $item) {
                $news = News::fromArray($item);
                $this->newsRepository->saveNews($news);
            }
            
            return $items;
        } catch (RuntimeException) {
            // API 실패 시 DB에서 조회
            $dbItems = $this->newsRepository->findLatest($limit);
            
            return array_map(fn($item) => News::fromArray($item)->toJson(), $dbItems);
        }
    }

    /**
     * 뉴스 상세 조회
     */
    public function getNewsById(int $id): ?array
    {
        $news = $this->newsRepository->findById($id);
        
        if (!$news) {
            return null;
        }
        
        return News::fromArray($news)->toJson();
    }

    /**
     * URL로 뉴스 조회 또는 생성
     */
    public function getOrCreateByUrl(string $url, ?string $title = null): ?array
    {
        // 기존 뉴스 조회
        $existing = $this->newsRepository->findByUrl($url);
        
        if ($existing) {
            return News::fromArray($existing)->toJson();
        }
        
        // 새 뉴스 생성 (기본 정보만)
        if ($title) {
            $news = new News($title, $url);
            $id = $this->newsRepository->create($news->toArray());
            
            $created = $this->newsRepository->findById($id);
            return $created ? News::fromArray($created)->toJson() : null;
        }
        
        return null;
    }

    /**
     * 카테고리별 뉴스 조회
     */
    public function getNewsByCategory(string $category, int $limit = 20): array
    {
        try {
            // 네이버 API에서 카테고리별 뉴스 가져오기
            $items = $this->naverNewsService->getByCategory($category, $limit);
            
            // DB에 저장
            foreach ($items as $item) {
                $item['category'] = $category;
                $news = News::fromArray($item);
                $this->newsRepository->saveNews($news);
            }
            
            return $items;
        } catch (RuntimeException) {
            // API 실패 시 DB에서 조회
            $dbItems = $this->newsRepository->findByCategory($category, $limit);
            
            return array_map(fn($item) => News::fromArray($item)->toJson(), $dbItems);
        }
    }

    /**
     * 트렌딩 뉴스 조회 (여러 키워드로)
     */
    public function getTrendingNews(int $limit = 20): array
    {
        $keywords = ['속보', '이슈', '화제'];
        
        try {
            $items = $this->naverNewsService->searchMultiple($keywords, (int) ceil($limit / count($keywords)));
            
            // 중복 제거 (URL 기준)
            $unique = [];
            $urls = [];
            
            foreach ($items as $item) {
                $url = $item['url'] ?? '';
                if (!in_array($url, $urls)) {
                    $urls[] = $url;
                    $unique[] = $item;
                }
            }
            
            return array_slice($unique, 0, $limit);
        } catch (RuntimeException) {
            return $this->newsRepository->findLatest($limit);
        }
    }

    /**
     * 사용자 북마크 목록 조회
     */
    public function getUserBookmarks(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->newsRepository->findUserBookmarks($userId, $perPage, $offset);
        
        return [
            'items' => array_map(fn($item) => News::fromArray($item)->toJson(), $items),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 북마크 추가
     */
    public function addBookmark(int $userId, int $newsId, ?string $memo = null): bool
    {
        // 뉴스 존재 확인
        if (!$this->newsRepository->exists($newsId)) {
            throw new RuntimeException('뉴스를 찾을 수 없습니다.');
        }
        
        // 이미 북마크되어 있는지 확인
        if ($this->newsRepository->isBookmarked($userId, $newsId)) {
            throw new RuntimeException('이미 북마크에 추가된 뉴스입니다.');
        }
        
        $this->newsRepository->addBookmark($userId, $newsId, $memo);
        
        return true;
    }

    /**
     * 북마크 삭제
     */
    public function removeBookmark(int $userId, int $newsId): bool
    {
        return $this->newsRepository->removeBookmark($userId, $newsId);
    }

    /**
     * 북마크 여부 확인
     */
    public function isBookmarked(int $userId, int $newsId): bool
    {
        return $this->newsRepository->isBookmarked($userId, $newsId);
    }

    /**
     * 출처 목록 조회
     */
    public function getSources(): array
    {
        return $this->newsRepository->getSources();
    }

    /**
     * 카테고리 목록 조회
     */
    public function getCategories(): array
    {
        return [
            'politics' => '정치',
            'economy' => '경제',
            'society' => '사회',
            'culture' => '문화',
            'world' => '국제',
            'sports' => '스포츠',
            'it' => 'IT/과학',
        ];
    }
}
