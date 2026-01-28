<?php
/**
 * Base Repository 클래스
 * 
 * 모든 Repository의 기본 클래스입니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Interfaces\RepositoryInterface;

/**
 * BaseRepository 클래스
 * 
 * 공통 CRUD 작업을 제공합니다.
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(array $criteria): array
    {
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $column => $value) {
            $conditions[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }
        
        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause}";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function findOneBy(array $criteria): ?array
    {
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $column => $value) {
            $conditions[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }
        
        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} LIMIT 1";
        
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data): bool
    {
        $affectedRows = $this->db->update($this->table, $data, [$this->primaryKey => $id]);
        
        return $affectedRows > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $affectedRows = $this->db->delete($this->table, [$this->primaryKey => $id]);
        
        return $affectedRows > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $criteria = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                $conditions[] = "`{$column}` = :{$column}";
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        return (int) $this->db->fetchColumn($sql, $params);
    }

    /**
     * 존재 여부 확인
     */
    public function exists(int $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        
        return $this->db->fetchColumn($sql, ['id' => $id]) !== false;
    }

    /**
     * 트랜잭션 내에서 콜백 실행
     */
    protected function transaction(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }

    /**
     * 페이지네이션 적용 조회
     */
    public function paginate(int $page = 1, int $perPage = 20, array $criteria = [], string $orderBy = 'id', string $order = 'DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                $conditions[] = "`{$column}` = :{$column}";
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY `{$orderBy}` {$order} LIMIT {$perPage} OFFSET {$offset}";
        
        $items = $this->db->fetchAll($sql, $params);
        $total = $this->count($criteria);
        
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
