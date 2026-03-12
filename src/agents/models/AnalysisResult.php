<?php
/**
 * Analysis Result Model
 * 
 * AI 분석 결과를 담는 불변 객체
 * 
 * @package Agents\Models
 * @author The Gist AI System
 * @version 3.0.0 - 구조화된 섹션 분석 추가
 */

declare(strict_types=1);

namespace Agents\Models;

final class AnalysisResult
{
    /**
     * @param string $translationSummary 번역 요약 (하위 호환)
     * @param array $keyPoints 핵심 포인트 배열
     * @param array $criticalAnalysis 비판적 분석 (why_important 등)
     * @param string|null $audioUrl TTS 오디오 URL
     * @param array $metadata 메타데이터
     * @param string|null $newsTitle 뉴스 제목 (한글)
     * @param string|null $narration 내레이션 스크립트
     * @param string|null $contentSummary 콘텐츠 요약
     * @param string|null $originalTitle 원문 제목 (영문)
     * @param string|null $author 저자
     * @param array $sections 구조화된 섹션 배열 (하위 호환)
     * @param string|null $introductionSummary 서론 요약 (v3.0 신규)
     * @param array $sectionAnalysis 섹션별 분석 배열 (v3.0 신규)
     * @param string|null $geopoliticalImplication 지정학적 함의 (v3.0 신규)
     */
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
        private readonly ?string $author = null,
        private readonly array $sections = [],
        private readonly ?string $introductionSummary = null,
        private readonly array $sectionAnalysis = [],
        private readonly ?string $geopoliticalImplication = null
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
     * 구조화된 섹션 배열 반환 (하위 호환)
     * @return array 각 요소: ['original_heading' => string, 'translated_heading' => string, 'summary' => string]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * 서론 요약 반환 (v3.0)
     */
    public function getIntroductionSummary(): ?string
    {
        return $this->introductionSummary;
    }

    /**
     * 섹션별 분석 배열 반환 (v3.0)
     * @return array 각 요소: ['section_title' => string, 'section_title_ko' => string, 'summary' => string, 'key_insight' => string]
     */
    public function getSectionAnalysis(): array
    {
        return $this->sectionAnalysis;
    }

    /**
     * 지정학적 함의 반환 (v3.0)
     */
    public function getGeopoliticalImplication(): ?string
    {
        return $this->geopoliticalImplication;
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
            author: $this->author,
            sections: $this->sections,
            introductionSummary: $this->introductionSummary,
            sectionAnalysis: $this->sectionAnalysis,
            geopoliticalImplication: $this->geopoliticalImplication
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
            author: $this->author,
            sections: $this->sections,
            introductionSummary: $this->introductionSummary,
            sectionAnalysis: $this->sectionAnalysis,
            geopoliticalImplication: $this->geopoliticalImplication
        );
    }

    /**
     * Narration 업데이트된 새 인스턴스 반환 (v3.0)
     */
    public function withNarration(string $narration): self
    {
        return new self(
            translationSummary: $this->translationSummary,
            keyPoints: $this->keyPoints,
            criticalAnalysis: $this->criticalAnalysis,
            audioUrl: $this->audioUrl,
            metadata: $this->metadata,
            newsTitle: $this->newsTitle,
            narration: $narration,
            contentSummary: $this->contentSummary,
            originalTitle: $this->originalTitle,
            author: $this->author,
            sections: $this->sections,
            introductionSummary: $this->introductionSummary,
            sectionAnalysis: $this->sectionAnalysis,
            geopoliticalImplication: $this->geopoliticalImplication
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
            'sections' => $this->sections,
            'introduction_summary' => $this->introductionSummary,
            'section_analysis' => $this->sectionAnalysis,
            'geopolitical_implication' => $this->geopoliticalImplication,
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
            author: $data['author'] ?? null,
            sections: $data['sections'] ?? [],
            introductionSummary: $data['introduction_summary'] ?? null,
            sectionAnalysis: $data['section_analysis'] ?? [],
            geopoliticalImplication: $data['geopolitical_implication'] ?? null
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
            contentSummary: null,
            sections: [],
            sectionAnalysis: []
        );
    }
}
