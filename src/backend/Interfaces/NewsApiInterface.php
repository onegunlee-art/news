<?php
/**
 * 뉴스 API 인터페이스
 * 
 * 외부 뉴스 API 클라이언트가 구현해야 하는 계약을 정의합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Interfaces;

/**
 * NewsApiInterface
 * 
 * 뉴스 검색 및 조회에 필요한 메서드를 정의합니다.
 */
interface NewsApiInterface
{
    /**
     * 키워드로 뉴스 검색
     * 
     * @param string $query 검색어
     * @param int $display 표시할 결과 수
     * @param int $start 검색 시작 위치
     * @param string $sort 정렬 기준 (sim: 정확도, date: 날짜)
     * @return array 검색 결과
     */
    public function search(
        string $query,
        int $display = 10,
        int $start = 1,
        string $sort = 'date'
    ): array;

    /**
     * 최신 뉴스 조회
     * 
     * @param int $limit 조회할 개수
     * @return array 최신 뉴스 목록
     */
    public function getLatest(int $limit = 10): array;

    /**
     * 카테고리별 뉴스 조회
     * 
     * @param string $category 카테고리
     * @param int $limit 조회할 개수
     * @return array 뉴스 목록
     */
    public function getByCategory(string $category, int $limit = 10): array;
}
