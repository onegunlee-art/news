<?php
/**
 * 데이터베이스 연결 관리 클래스
 * 
 * Singleton 패턴을 사용하여 PDO 연결을 관리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Database 클래스
 * 
 * PDO 기반 데이터베이스 연결 및 쿼리 실행을 담당합니다.
 */
final class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $config;
    private int $transactionLevel = 0;

    /**
     * 생성자 (private - Singleton 패턴)
     */
    private function __construct()
    {
        $configPath = dirname(__DIR__, 3) . '/config/database.php';
        
        if (!file_exists($configPath)) {
            throw new RuntimeException('Database configuration file not found');
        }
        
        $this->config = require $configPath;
    }

    /**
     * 복제 방지
     */
    private function __clone(): void
    {
    }

    /**
     * 역직렬화 방지
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }

    /**
     * 인스턴스 반환 (Singleton)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * PDO 연결 반환
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        
        return $this->connection;
    }

    /**
     * 데이터베이스 연결
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['driver'] ?? 'mysql',
                $this->config['host'] ?? 'localhost',
                $this->config['port'] ?? '3306',
                $this->config['database'] ?? '',
                $this->config['charset'] ?? 'utf8mb4'
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                $this->config['options'] ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * SELECT 쿼리 실행 (단일 결과)
     * 
     * @param string $sql SQL 쿼리
     * @param array $params 바인딩 파라미터
     * @return array|null 결과 또는 null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->executeQuery($sql, $params);
        $result = $stmt->fetch();
        
        return $result !== false ? $result : null;
    }

    /**
     * SELECT 쿼리 실행 (다중 결과)
     * 
     * @param string $sql SQL 쿼리
     * @param array $params 바인딩 파라미터
     * @return array 결과 배열
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->executeQuery($sql, $params);
        
        return $stmt->fetchAll();
    }

    /**
     * SELECT 쿼리 실행 (단일 컬럼)
     * 
     * @param string $sql SQL 쿼리
     * @param array $params 바인딩 파라미터
     * @return mixed 결과 값 또는 false
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->executeQuery($sql, $params);
        
        return $stmt->fetchColumn();
    }

    /**
     * INSERT 쿼리 실행
     * 
     * @param string $table 테이블명
     * @param array $data 삽입할 데이터 [컬럼 => 값]
     * @return int 삽입된 행의 ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        $this->executeQuery($sql, $data);
        
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * UPDATE 쿼리 실행
     * 
     * @param string $table 테이블명
     * @param array $data 업데이트할 데이터 [컬럼 => 값]
     * @param array $where 조건 [컬럼 => 값]
     * @return int 영향받은 행 수
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClauses = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $paramName = 'set_' . $column;
            $setClauses[] = $this->quoteIdentifier($column) . ' = :' . $paramName;
            $params[$paramName] = $value;
        }
        
        $whereClauses = [];
        foreach ($where as $column => $value) {
            $paramName = 'where_' . $column;
            $whereClauses[] = $this->quoteIdentifier($column) . ' = :' . $paramName;
            $params[$paramName] = $value;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );
        
        $stmt = $this->executeQuery($sql, $params);
        
        return $stmt->rowCount();
    }

    /**
     * DELETE 쿼리 실행
     * 
     * @param string $table 테이블명
     * @param array $where 조건 [컬럼 => 값]
     * @return int 삭제된 행 수
     */
    public function delete(string $table, array $where): int
    {
        $whereClauses = [];
        $params = [];
        
        foreach ($where as $column => $value) {
            $whereClauses[] = $this->quoteIdentifier($column) . ' = :' . $column;
            $params[$column] = $value;
        }
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(' AND ', $whereClauses)
        );
        
        $stmt = $this->executeQuery($sql, $params);
        
        return $stmt->rowCount();
    }

    /**
     * 쿼리 실행
     * 
     * @param string $sql SQL 쿼리
     * @param array $params 바인딩 파라미터
     * @return PDOStatement
     */
    public function executeQuery(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->getConnection()->prepare($sql);
        
        foreach ($params as $key => $value) {
            $paramKey = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $paramType = $this->getParamType($value);
            $stmt->bindValue($paramKey, $value, $paramType);
        }
        
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * 트랜잭션 시작
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $this->getConnection()->beginTransaction();
        } else {
            $this->getConnection()->exec('SAVEPOINT trans' . $this->transactionLevel);
        }
        
        $this->transactionLevel++;
        
        return true;
    }

    /**
     * 트랜잭션 커밋
     */
    public function commit(): bool
    {
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->getConnection()->commit();
        }
        
        return true;
    }

    /**
     * 트랜잭션 롤백
     */
    public function rollback(): bool
    {
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->getConnection()->rollBack();
        }
        
        $this->getConnection()->exec('ROLLBACK TO trans' . $this->transactionLevel);
        
        return true;
    }

    /**
     * 트랜잭션 실행 (클로저)
     * 
     * @param callable $callback 트랜잭션 내에서 실행할 콜백
     * @return mixed 콜백 반환값
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 파라미터 타입 결정
     */
    private function getParamType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * 식별자 인용 (SQL Injection 방지)
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * 연결 종료
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * 소멸자
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
