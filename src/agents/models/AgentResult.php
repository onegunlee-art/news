<?php
/**
 * Agent Result Model
 * 
 * Agent 처리 결과를 담는 불변 객체
 * 
 * @package Agents\Models
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Models;

final class AgentResult
{
    public function __construct(
        private readonly bool $success,
        private readonly array $data,
        private readonly array $errors = [],
        private readonly array $metadata = []
    ) {}

    /**
     * 성공 여부
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * 결과 데이터
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 특정 데이터 키 값 반환
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 에러 목록
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 첫 번째 에러 메시지
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0]['message'] ?? null;
    }

    /**
     * 메타데이터
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * JSON 직렬화용 배열 변환
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'errors' => $this->errors,
            'metadata' => $this->metadata
        ];
    }

    /**
     * JSON 문자열 변환
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 성공 결과 생성 팩토리
     */
    public static function success(array $data, array $metadata = []): self
    {
        return new self(
            success: true,
            data: $data,
            errors: [],
            metadata: $metadata
        );
    }

    /**
     * 실패 결과 생성 팩토리
     */
    public static function failure(string $error, string $agent = '', array $metadata = []): self
    {
        return new self(
            success: false,
            data: [],
            errors: [['message' => $error, 'agent' => $agent, 'timestamp' => date('c')]],
            metadata: $metadata
        );
    }
}
