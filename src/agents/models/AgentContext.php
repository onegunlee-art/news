<?php
/**
 * Agent Context Model
 * 
 * Agent 간 데이터 전달을 위한 컨텍스트 객체
 * Immutable 패턴으로 구현하여 데이터 무결성 보장
 * 
 * @package Agents\Models
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Models;

final class AgentContext
{
    private string $url;
    private ?ArticleData $articleData = null;
    private ?AnalysisResult $analysisResult = null;
    private array $metadata = [];
    private array $errors = [];
    private bool $isValid = true;
    private array $processedBy = [];
    private float $startTime;

    public function __construct(string $url)
    {
        $this->url = $url;
        $this->startTime = microtime(true);
        $this->metadata['created_at'] = date('c');
    }

    /**
     * URL 반환
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * 기사 데이터 설정
     */
    public function withArticleData(ArticleData $articleData): self
    {
        $clone = clone $this;
        $clone->articleData = $articleData;
        return $clone;
    }

    /**
     * 기사 데이터 반환
     */
    public function getArticleData(): ?ArticleData
    {
        return $this->articleData;
    }

    /**
     * 분석 결과 설정
     */
    public function withAnalysisResult(AnalysisResult $result): self
    {
        $clone = clone $this;
        $clone->analysisResult = $result;
        return $clone;
    }

    /**
     * 분석 결과 반환
     */
    public function getAnalysisResult(): ?AnalysisResult
    {
        return $this->analysisResult;
    }

    /**
     * 메타데이터 추가
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;
        return $clone;
    }

    /**
     * 메타데이터 반환
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 에러 추가
     */
    public function withError(string $error, string $agent = ''): self
    {
        $clone = clone $this;
        $clone->errors[] = [
            'message' => $error,
            'agent' => $agent,
            'timestamp' => date('c')
        ];
        $clone->isValid = false;
        return $clone;
    }

    /**
     * 에러 목록 반환
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 유효성 상태 반환
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * 처리한 Agent 기록
     */
    public function markProcessedBy(string $agentName): self
    {
        $clone = clone $this;
        $clone->processedBy[] = [
            'agent' => $agentName,
            'timestamp' => date('c'),
            'elapsed_ms' => round((microtime(true) - $this->startTime) * 1000, 2)
        ];
        return $clone;
    }

    /**
     * 처리 이력 반환
     */
    public function getProcessedBy(): array
    {
        return $this->processedBy;
    }

    /**
     * 전체 처리 시간 (ms)
     */
    public function getElapsedMs(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    /**
     * 최종 결과 생성
     */
    public function getResult(): AgentResult
    {
        return new AgentResult(
            success: $this->isValid,
            data: $this->analysisResult?->toArray() ?? [],
            errors: $this->errors,
            metadata: array_merge($this->metadata, [
                'processed_by' => $this->processedBy,
                'elapsed_ms' => $this->getElapsedMs()
            ])
        );
    }
}
