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

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->newsRepository = new NewsRepository();
    }

    /**
     * 뉴스 검색 (DB)
     */
    public function search(string $query, int $page = 1, int $perPage = 20): array
    {
        $items = $this->newsRepository->searchLike($query, $perPage, ($page - 1) * $perPage);
        $total = $this->newsRepository->countSearch($query);

        return [
            'items' => array_map(fn($item) => News::fromArray($item)->toJson(), $items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
            'source' => 'database',
        ];
    }

    /**
     * 최신 뉴스 목록 조회 (DB)
     */
    public function getLatestNews(int $limit = 20): array
    {
        $dbItems = $this->newsRepository->findLatest($limit);
        return array_map(fn($item) => News::fromArray($item)->toJson(), $dbItems);
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
     * 카테고리별 뉴스 조회 (DB)
     */
    public function getNewsByCategory(string $category, int $limit = 20): array
    {
        $dbItems = $this->newsRepository->findByCategory($category, $limit);
        return array_map(fn($item) => News::fromArray($item)->toJson(), $dbItems);
    }

    /**
     * 트렌딩 뉴스 조회 (DB 최신 목록)
     */
    public function getTrendingNews(int $limit = 20): array
    {
        $dbItems = $this->newsRepository->findLatest($limit);
        return array_map(fn($item) => News::fromArray($item)->toJson(), $dbItems);
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
