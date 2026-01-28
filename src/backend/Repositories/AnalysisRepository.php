<?php
/**
 * Analysis Repository 클래스
 * 
 * 분석 결과 데이터 접근을 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Analysis;

/**
 * AnalysisRepository 클래스
 */
final class AnalysisRepository extends BaseRepository
{
    protected string $table = 'analyses';

    /**
     * 뉴스 ID로 분석 결과 조회
     */
    public function findByNewsId(int $newsId): ?array
    {
        return $this->findOneBy(['news_id' => $newsId, 'status' => 'completed']);
    }

    /**
     * 사용자의 분석 내역 조회
     */
    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT a.*, n.title as news_title, n.source as news_source
                FROM {$this->table} a
                LEFT JOIN news n ON a.news_id = n.id
                WHERE a.user_id = :user_id
                ORDER BY a.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * 사용자의 분석 내역 수 조회
     */
    public function countByUserId(int $userId): int
    {
        return $this->count(['user_id' => $userId]);
    }

    /**
     * 완료된 분석 결과 조회
     */
    public function findCompleted(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT a.*, n.title as news_title
                FROM {$this->table} a
                LEFT JOIN news n ON a.news_id = n.id
                WHERE a.status = 'completed'
                ORDER BY a.completed_at DESC
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * 분석 결과 저장
     */
    public function saveAnalysis(Analysis $analysis): int
    {
        return $this->create($analysis->toArray());
    }

    /**
     * 분석 상태 업데이트
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null): bool
    {
        $data = ['status' => $status];
        
        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        
        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }
        
        return $this->update($id, $data);
    }

    /**
     * 분석 결과 업데이트
     */
    public function updateAnalysisResult(int $id, Analysis $analysis): bool
    {
        $data = $analysis->toArray();
        $data['status'] = 'completed';
        $data['completed_at'] = date('Y-m-d H:i:s');
        
        return $this->update($id, $data);
    }

    /**
     * 감정별 분석 결과 조회
     */
    public function findBySentiment(string $sentiment, int $limit = 20): array
    {
        $sql = "SELECT a.*, n.title as news_title
                FROM {$this->table} a
                LEFT JOIN news n ON a.news_id = n.id
                WHERE a.sentiment = :sentiment AND a.status = 'completed'
                ORDER BY a.created_at DESC
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            'sentiment' => $sentiment,
            'limit' => $limit,
        ]);
    }

    /**
     * 기간별 분석 통계
     */
    public function getStatsByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative,
                    SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
                    AVG(processing_time_ms) as avg_processing_time
                FROM {$this->table}
                WHERE status = 'completed'
                AND created_at BETWEEN :start AND :end
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        return $this->db->fetchAll($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 인기 키워드 조회
     */
    public function getPopularKeywords(int $days = 7, int $limit = 20): array
    {
        // JSON_TABLE을 사용한 키워드 추출 (MySQL 8.0+)
        $sql = "SELECT 
                    kw.keyword,
                    COUNT(*) as count,
                    AVG(kw.score) as avg_score
                FROM {$this->table} a,
                JSON_TABLE(a.keywords, '\$[*]' COLUMNS (
                    keyword VARCHAR(100) PATH '\$.keyword',
                    score DECIMAL(5,4) PATH '\$.score'
                )) kw
                WHERE a.status = 'completed'
                AND a.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY kw.keyword
                ORDER BY count DESC, avg_score DESC
                LIMIT :limit";
        
        try {
            return $this->db->fetchAll($sql, [
                'days' => $days,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            // JSON_TABLE 미지원 시 빈 배열 반환
            return [];
        }
    }

    /**
     * 사용자별 오늘 분석 횟수 조회
     */
    public function countTodayByUserId(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE user_id = :user_id
                AND DATE(created_at) = CURDATE()";
        
        return (int) $this->db->fetchColumn($sql, ['user_id' => $userId]);
    }

    /**
     * 대기 중인 분석 요청 조회
     */
    public function findPending(int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE status IN ('pending', 'processing')
                ORDER BY created_at ASC
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, ['limit' => $limit]);
    }

    /**
     * 오래된 실패 분석 삭제
     */
    public function deleteFailedOld(int $days = 30): int
    {
        $sql = "DELETE FROM {$this->table}
                WHERE status = 'failed'
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        return $this->db->executeQuery($sql, ['days' => $days])->rowCount();
    }
}
