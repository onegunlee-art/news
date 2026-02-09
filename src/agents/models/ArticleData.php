<?php
/**
 * Article Data Model
 * 
 * 기사 데이터를 담는 불변 객체
 * 
 * @package Agents\Models
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Models;

final class ArticleData
{
    public function __construct(
        private readonly string $url,
        private readonly string $title,
        private readonly string $content,
        private readonly ?string $description = null,
        private readonly ?string $author = null,
        private readonly ?string $publishedAt = null,
        private readonly ?string $imageUrl = null,
        private readonly ?string $language = null,
        private readonly array $metadata = []
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /**
     * 이미지 URL만 변경한 새 인스턴스 반환 (불변 객체)
     */
    public function withImageUrl(?string $imageUrl): self
    {
        return new self(
            url: $this->url,
            title: $this->title,
            content: $this->content,
            description: $this->description,
            author: $this->author,
            publishedAt: $this->publishedAt,
            imageUrl: $imageUrl,
            language: $this->language,
            metadata: $this->metadata
        );
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 콘텐츠 길이 (문자 수)
     */
    public function getContentLength(): int
    {
        return mb_strlen($this->content);
    }

    /**
     * 콘텐츠 단어 수 (대략적)
     */
    public function getWordCount(): int
    {
        return str_word_count($this->content);
    }

    /**
     * 배열 변환
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'content' => $this->content,
            'description' => $this->description,
            'author' => $this->author,
            'published_at' => $this->publishedAt,
            'image_url' => $this->imageUrl,
            'language' => $this->language,
            'metadata' => $this->metadata,
            'content_length' => $this->getContentLength(),
            'word_count' => $this->getWordCount()
        ];
    }

    /**
     * 배열에서 생성
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            title: $data['title'] ?? '',
            content: $data['content'] ?? '',
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            publishedAt: $data['published_at'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            language: $data['language'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
}
