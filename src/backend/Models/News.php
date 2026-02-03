<?php
/**
 * News 모델 클래스
 * 
 * 뉴스 엔티티를 표현합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Models;

/**
 * News 모델
 * 
 * 뉴스 기사 데이터와 관련 비즈니스 로직을 캡슐화합니다.
 */
final class News
{
    private ?int $id = null;
    private ?string $externalId = null;
    private string $title;
    private ?string $description = null;
    private ?string $content = null;
    private ?string $source = null;
    private ?string $author = null;
    private string $url;
    private ?string $imageUrl = null;
    private ?string $category = null;
    private ?\DateTimeImmutable $publishedAt = null;
    private ?\DateTimeImmutable $fetchedAt = null;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * 생성자
     */
    public function __construct(string $title, string $url)
    {
        $this->title = $title;
        $this->url = $url;
    }

    /**
     * 배열로부터 News 객체 생성
     */
    public static function fromArray(array $data): self
    {
        $news = new self(
            $data['title'] ?? '',
            $data['url'] ?? $data['link'] ?? ''
        );
        
        $news->id = isset($data['id']) ? (int) $data['id'] : null;
        $news->externalId = $data['external_id'] ?? null;
        $news->description = $data['description'] ?? null;
        $news->content = $data['content'] ?? null;
        $news->source = $data['source'] ?? null;
        $news->author = $data['author'] ?? null;
        $news->imageUrl = $data['image_url'] ?? null;
        $news->category = $data['category'] ?? null;
        
        if (isset($data['published_at'])) {
            $news->publishedAt = new \DateTimeImmutable($data['published_at']);
        } elseif (isset($data['pubDate'])) {
            $news->publishedAt = new \DateTimeImmutable($data['pubDate']);
        }
        
        if (isset($data['fetched_at'])) {
            $news->fetchedAt = new \DateTimeImmutable($data['fetched_at']);
        }
        
        if (isset($data['created_at'])) {
            $news->createdAt = new \DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $news->updatedAt = new \DateTimeImmutable($data['updated_at']);
        }
        
        return $news;
    }

    // ==================== Getters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ==================== Setters ====================

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    // ==================== 비즈니스 로직 ====================

    /**
     * 분석용 텍스트 반환 (제목 + 본문)
     */
    public function getAnalysisText(): string
    {
        $text = $this->title;
        
        if ($this->content) {
            $text .= "\n\n" . $this->content;
        } elseif ($this->description) {
            $text .= "\n\n" . $this->description;
        }
        
        return $text;
    }

    /**
     * 발행 후 경과 시간 반환
     */
    public function getTimeSincePublished(): ?string
    {
        if (!$this->publishedAt) {
            return null;
        }
        
        $diff = $this->publishedAt->diff(new \DateTimeImmutable());
        
        if ($diff->days > 30) {
            return $this->publishedAt->format('Y-m-d');
        }
        
        if ($diff->days > 0) {
            return $diff->days . '일 전';
        }
        
        if ($diff->h > 0) {
            return $diff->h . '시간 전';
        }
        
        if ($diff->i > 0) {
            return $diff->i . '분 전';
        }
        
        return '방금 전';
    }

    /**
     * 배열로 변환 (데이터베이스 저장용)
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'source' => $this->source,
            'author' => $this->author,
            'url' => $this->url,
            'image_url' => $this->imageUrl,
            'category' => $this->category,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
            'fetched_at' => $this->fetchedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * JSON 직렬화용 배열로 변환 (API 응답용)
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'source' => $this->source,
            'author' => $this->author,
            'url' => $this->url,
            'image_url' => $this->imageUrl,
            'category' => $this->category,
            'published_at' => $this->publishedAt?->format('c'),
            'time_ago' => $this->getTimeSincePublished(),
        ];
    }
}
