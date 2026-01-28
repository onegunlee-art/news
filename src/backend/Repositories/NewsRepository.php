<?php
/**
 * News Repository 클래스
 * 
 * 뉴스 데이터 접근을 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\News;

/**
 * NewsRepository 클래스
 */
final class NewsRepository extends BaseRepository
{
    protected string $table = 'news';

    /**
     * URL로 뉴스 조회
     */
    public function findByUrl(string $url): ?array
    {
        return $this->findOneBy(['url' => $url]);
    }

    /**
     * 외부 ID로 뉴스 조회
     */
    public function findByExternalId(string $externalId): ?array
    {
        return $this->findOneBy(['external_id' => $externalId]);
    }

    /**
     * 최신 뉴스 조회
     */
    public function findLatest(int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->table} 
                ORDER BY published_at DESC, created_at DESC 
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, ['limit' => $limit]);
    }

    /**
     * 카테고리별 뉴스 조회
     */
    public function findByCategory(string $category, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE category = :category 
                ORDER BY published_at DESC 
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            'category' => $category,
            'limit' => $limit,
        ]);
    }

    /**
     * 출처별 뉴스 조회
     */
    public function findBySource(string $source, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE source = :source 
                ORDER BY published_at DESC 
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            'source' => $source,
            'limit' => $limit,
        ]);
    }

    /**
     * 키워드 검색
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE MATCH(title, description, content) AGAINST(:query IN NATURAL LANGUAGE MODE)
                ORDER BY published_at DESC 
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'query' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * LIKE 검색 (Fulltext 대안)
     */
    public function searchLike(string $query, int $limit = 20, int $offset = 0): array
    {
        $likeQuery = '%' . $query . '%';
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE title LIKE :query OR description LIKE :query 
                ORDER BY published_at DESC 
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'query' => $likeQuery,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * 검색 결과 수 조회
     */
    public function countSearch(string $query): int
    {
        $likeQuery = '%' . $query . '%';
        
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE title LIKE :query OR description LIKE :query";
        
        return (int) $this->db->fetchColumn($sql, ['query' => $likeQuery]);
    }

    /**
     * 기간별 뉴스 조회
     */
    public function findByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE published_at BETWEEN :start AND :end 
                ORDER BY published_at DESC 
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ]);
    }

    /**
     * 뉴스 저장 (중복 체크)
     */
    public function saveNews(News $news): int
    {
        // URL로 중복 체크
        $existing = $this->findByUrl($news->getUrl());
        
        if ($existing) {
            return (int) $existing['id'];
        }
        
        return $this->create($news->toArray());
    }

    /**
     * 다중 뉴스 저장
     */
    public function saveMany(array $newsItems): array
    {
        $savedIds = [];
        
        $this->transaction(function () use ($newsItems, &$savedIds) {
            foreach ($newsItems as $item) {
                $news = $item instanceof News ? $item : News::fromArray($item);
                $savedIds[] = $this->saveNews($news);
            }
        });
        
        return $savedIds;
    }

    /**
     * 출처 목록 조회
     */
    public function getSources(): array
    {
        $sql = "SELECT DISTINCT source FROM {$this->table} 
                WHERE source IS NOT NULL 
                ORDER BY source";
        
        return array_column($this->db->fetchAll($sql), 'source');
    }

    /**
     * 카테고리 목록 조회
     */
    public function getCategories(): array
    {
        $sql = "SELECT DISTINCT category FROM {$this->table} 
                WHERE category IS NOT NULL 
                ORDER BY category";
        
        return array_column($this->db->fetchAll($sql), 'category');
    }

    /**
     * 오래된 뉴스 삭제
     */
    public function deleteOldNews(int $days = 90): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        return $this->db->executeQuery($sql, ['days' => $days])->rowCount();
    }

    /**
     * 사용자 북마크 뉴스 조회
     */
    public function findUserBookmarks(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT n.*, b.created_at as bookmarked_at, b.memo 
                FROM {$this->table} n
                INNER JOIN bookmarks b ON n.id = b.news_id
                WHERE b.user_id = :user_id
                ORDER BY b.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * 북마크 추가
     */
    public function addBookmark(int $userId, int $newsId, ?string $memo = null): int
    {
        return $this->db->insert('bookmarks', [
            'user_id' => $userId,
            'news_id' => $newsId,
            'memo' => $memo,
        ]);
    }

    /**
     * 북마크 삭제
     */
    public function removeBookmark(int $userId, int $newsId): bool
    {
        $sql = "DELETE FROM bookmarks WHERE user_id = :user_id AND news_id = :news_id";
        
        return $this->db->executeQuery($sql, [
            'user_id' => $userId,
            'news_id' => $newsId,
        ])->rowCount() > 0;
    }

    /**
     * 북마크 여부 확인
     */
    public function isBookmarked(int $userId, int $newsId): bool
    {
        $sql = "SELECT 1 FROM bookmarks WHERE user_id = :user_id AND news_id = :news_id";
        
        return $this->db->fetchColumn($sql, [
            'user_id' => $userId,
            'news_id' => $newsId,
        ]) !== false;
    }
}
