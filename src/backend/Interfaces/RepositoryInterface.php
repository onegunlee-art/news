<?php
/**
 * Repository 인터페이스
 * 
 * 모든 Repository가 구현해야 하는 기본 인터페이스입니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Interfaces;

/**
 * RepositoryInterface
 * 
 * CRUD 작업의 기본 계약을 정의합니다.
 * 
 * @template T of object
 */
interface RepositoryInterface
{
    /**
     * ID로 엔티티 조회
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array;

    /**
     * 모든 엔티티 조회
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * 조건에 맞는 엔티티 조회
     * 
     * @param array $criteria
     * @return array
     */
    public function findBy(array $criteria): array;

    /**
     * 조건에 맞는 단일 엔티티 조회
     * 
     * @param array $criteria
     * @return array|null
     */
    public function findOneBy(array $criteria): ?array;

    /**
     * 엔티티 생성
     * 
     * @param array $data
     * @return int 생성된 엔티티의 ID
     */
    public function create(array $data): int;

    /**
     * 엔티티 수정
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * 엔티티 삭제
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * 전체 개수 조회
     * 
     * @param array $criteria
     * @return int
     */
    public function count(array $criteria = []): int;
}
