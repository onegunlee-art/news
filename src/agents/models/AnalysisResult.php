<?php
/**
 * Analysis Result Model
 * 
 * AI 분석 결과를 담는 불변 객체
 * 
 * @package Agents\Models
 * @author The Gist AI System
 * @version 2.0.0
 */

declare(strict_types=1);

namespace Agents\Models;

final class AnalysisResult
{
    public function __construct(
        private readonly string $translationSummary,
        private readonly array $keyPoints,
        private readonly array $criticalAnalysis,
        private readonly ?string $audioUrl = null,
        private readonly array $metadata = [],
        private readonly ?string $newsTitle = null,
        private readonly ?string $narration = null,
        private readonly ?string $contentSummary = null,
        private readonly ?string $originalTitle = null,
        private readonly ?string $author = null
    ) {}

    public function getTranslationSummary(): string
    {
        return $this->translationSummary;
    }

    public function getKeyPoints(): array
    {
        return $this->keyPoints;
    }

    public function getCriticalAnalysis(): array
    {
        return $this->criticalAnalysis;
    }

    public function getWhyImportant(): ?string
    {
        return $this->criticalAnalysis['why_important'] ?? null;
    }

    public function getFuturePrediction(): ?string
    {
        return $this->criticalAnalysis['future_prediction'] ?? null;
    }

    public function getAudioUrl(): ?string
    {
        return $this->audioUrl;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getNewsTitle(): ?string
    {
        return $this->newsTitle;
    }

    public function getNarration(): ?string
    {
        return $this->narration;
    }

    public function getContentSummary(): ?string
    {
        return $this->contentSummary;
    }

    public function getOriginalTitle(): ?string
    {
        return $this->originalTitle;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * 오디오 URL 추가된 새 인스턴스 반환
     */
    public function withAudioUrl(string $audioUrl): self
    {
        return new self(
            translationSummary: $this->translationSummary,
            keyPoints: $this->keyPoints,
            criticalAnalysis: $this->criticalAnalysis,
            audioUrl: $audioUrl,
            metadata: $this->metadata,
            newsTitle: $this->newsTitle,
            narration: $this->narration,
            contentSummary: $this->contentSummary,
            originalTitle: $this->originalTitle,
            author: $this->author
        );
    }

    /**
     * 메타데이터 추가된 새 인스턴스 반환
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            translationSummary: $this->translationSummary,
            keyPoints: $this->keyPoints,
            criticalAnalysis: $this->criticalAnalysis,
            audioUrl: $this->audioUrl,
            metadata: array_merge($this->metadata, $metadata),
            newsTitle: $this->newsTitle,
            narration: $this->narration,
            contentSummary: $this->contentSummary,
            originalTitle: $this->originalTitle,
            author: $this->author
        );
    }

    /**
     * 배열 변환
     */
    public function toArray(): array
    {
        return [
            'news_title' => $this->newsTitle,
            'original_title' => $this->originalTitle,
            'author' => $this->author,
            'translation_summary' => $this->translationSummary,
            'key_points' => $this->keyPoints,
            'narration' => $this->narration,
            'content_summary' => $this->contentSummary,
            'critical_analysis' => $this->criticalAnalysis,
            'audio_url' => $this->audioUrl,
            'metadata' => $this->metadata
        ];
    }

    /**
     * JSON 변환
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 배열에서 생성
     */
    public static function fromArray(array $data): self
    {
        return new self(
            translationSummary: $data['translation_summary'] ?? '',
            keyPoints: $data['key_points'] ?? [],
            criticalAnalysis: $data['critical_analysis'] ?? [],
            audioUrl: $data['audio_url'] ?? null,
            metadata: $data['metadata'] ?? [],
            newsTitle: $data['news_title'] ?? null,
            narration: $data['narration'] ?? null,
            contentSummary: $data['content_summary'] ?? null,
            originalTitle: $data['original_title'] ?? null,
            author: $data['author'] ?? null
        );
    }

    /**
     * 빈 결과 생성
     */
    public static function empty(): self
    {
        return new self(
            translationSummary: '',
            keyPoints: [],
            criticalAnalysis: [],
            contentSummary: null
        );
    }
}
