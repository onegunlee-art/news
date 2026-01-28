<?php
/**
 * Analysis 모델 클래스
 * 
 * 분석 결과 엔티티를 표현합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Models;

/**
 * Analysis 모델
 * 
 * 뉴스 분석 결과 데이터와 관련 비즈니스 로직을 캡슐화합니다.
 */
final class Analysis
{
    private ?int $id = null;
    private ?int $userId = null;
    private ?int $newsId = null;
    private ?string $inputText = null;
    private array $keywords = [];
    private string $sentiment = 'neutral';
    private float $sentimentScore = 0.0;
    private ?array $sentimentDetails = null;
    private string $summary = '';
    private int $summaryLength = 0;
    private ?array $entities = null;
    private ?array $topics = null;
    private ?int $processingTimeMs = null;
    private string $status = 'pending';
    private ?string $errorMessage = null;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * 생성자
     */
    public function __construct()
    {
    }

    /**
     * 배열로부터 Analysis 객체 생성
     */
    public static function fromArray(array $data): self
    {
        $analysis = new self();
        
        $analysis->id = isset($data['id']) ? (int) $data['id'] : null;
        $analysis->userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $analysis->newsId = isset($data['news_id']) ? (int) $data['news_id'] : null;
        $analysis->inputText = $data['input_text'] ?? null;
        
        // JSON 필드 파싱
        $analysis->keywords = is_string($data['keywords'] ?? null) 
            ? json_decode($data['keywords'], true) ?? []
            : ($data['keywords'] ?? []);
            
        $analysis->sentiment = $data['sentiment'] ?? 'neutral';
        $analysis->sentimentScore = (float) ($data['sentiment_score'] ?? 0.0);
        
        $analysis->sentimentDetails = is_string($data['sentiment_details'] ?? null)
            ? json_decode($data['sentiment_details'], true)
            : ($data['sentiment_details'] ?? null);
            
        $analysis->summary = $data['summary'] ?? '';
        $analysis->summaryLength = (int) ($data['summary_length'] ?? mb_strlen($analysis->summary));
        
        $analysis->entities = is_string($data['entities'] ?? null)
            ? json_decode($data['entities'], true)
            : ($data['entities'] ?? null);
            
        $analysis->topics = is_string($data['topics'] ?? null)
            ? json_decode($data['topics'], true)
            : ($data['topics'] ?? null);
            
        $analysis->processingTimeMs = isset($data['processing_time_ms']) 
            ? (int) $data['processing_time_ms'] 
            : null;
        $analysis->status = $data['status'] ?? 'pending';
        $analysis->errorMessage = $data['error_message'] ?? null;
        
        if (isset($data['created_at'])) {
            $analysis->createdAt = new \DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['completed_at'])) {
            $analysis->completedAt = new \DateTimeImmutable($data['completed_at']);
        }
        
        return $analysis;
    }

    // ==================== Getters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getNewsId(): ?int
    {
        return $this->newsId;
    }

    public function getInputText(): ?string
    {
        return $this->inputText;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getSentiment(): string
    {
        return $this->sentiment;
    }

    public function getSentimentScore(): float
    {
        return $this->sentimentScore;
    }

    public function getSentimentDetails(): ?array
    {
        return $this->sentimentDetails;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getSummaryLength(): int
    {
        return $this->summaryLength;
    }

    public function getEntities(): ?array
    {
        return $this->entities;
    }

    public function getTopics(): ?array
    {
        return $this->topics;
    }

    public function getProcessingTimeMs(): ?int
    {
        return $this->processingTimeMs;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    // ==================== Setters ====================

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function setNewsId(?int $newsId): self
    {
        $this->newsId = $newsId;
        return $this;
    }

    public function setInputText(?string $inputText): self
    {
        $this->inputText = $inputText;
        return $this;
    }

    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function setSentiment(string $sentiment): self
    {
        $this->sentiment = $sentiment;
        return $this;
    }

    public function setSentimentScore(float $score): self
    {
        $this->sentimentScore = $score;
        return $this;
    }

    public function setSentimentDetails(?array $details): self
    {
        $this->sentimentDetails = $details;
        return $this;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = $summary;
        $this->summaryLength = mb_strlen($summary);
        return $this;
    }

    public function setEntities(?array $entities): self
    {
        $this->entities = $entities;
        return $this;
    }

    public function setTopics(?array $topics): self
    {
        $this->topics = $topics;
        return $this;
    }

    public function setProcessingTimeMs(?int $time): self
    {
        $this->processingTimeMs = $time;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setErrorMessage(?string $message): self
    {
        $this->errorMessage = $message;
        return $this;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    // ==================== 비즈니스 로직 ====================

    /**
     * 분석 완료 여부 확인
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * 분석 실패 여부 확인
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * 진행 중 여부 확인
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    /**
     * 긍정적 감정인지 확인
     */
    public function isPositive(): bool
    {
        return $this->sentiment === 'positive';
    }

    /**
     * 부정적 감정인지 확인
     */
    public function isNegative(): bool
    {
        return $this->sentiment === 'negative';
    }

    /**
     * 상위 키워드 반환
     */
    public function getTopKeywords(int $limit = 5): array
    {
        $keywords = $this->keywords;
        
        // score 기준 정렬
        usort($keywords, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        
        return array_slice($keywords, 0, $limit);
    }

    /**
     * 감정 레이블 (한글) 반환
     */
    public function getSentimentLabel(): string
    {
        return match ($this->sentiment) {
            'positive' => '긍정',
            'negative' => '부정',
            default => '중립',
        };
    }

    /**
     * 감정 색상 반환 (UI용)
     */
    public function getSentimentColor(): string
    {
        return match ($this->sentiment) {
            'positive' => '#2ecc71',
            'negative' => '#e74c3c',
            default => '#95a5a6',
        };
    }

    /**
     * 배열로 변환 (데이터베이스 저장용)
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'news_id' => $this->newsId,
            'input_text' => $this->inputText,
            'keywords' => json_encode($this->keywords, JSON_UNESCAPED_UNICODE),
            'sentiment' => $this->sentiment,
            'sentiment_score' => $this->sentimentScore,
            'sentiment_details' => $this->sentimentDetails 
                ? json_encode($this->sentimentDetails, JSON_UNESCAPED_UNICODE) 
                : null,
            'summary' => $this->summary,
            'summary_length' => $this->summaryLength,
            'entities' => $this->entities 
                ? json_encode($this->entities, JSON_UNESCAPED_UNICODE) 
                : null,
            'topics' => $this->topics 
                ? json_encode($this->topics, JSON_UNESCAPED_UNICODE) 
                : null,
            'processing_time_ms' => $this->processingTimeMs,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * JSON 직렬화용 배열로 변환 (API 응답용)
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'news_id' => $this->newsId,
            'keywords' => $this->getTopKeywords(10),
            'sentiment' => [
                'type' => $this->sentiment,
                'label' => $this->getSentimentLabel(),
                'score' => $this->sentimentScore,
                'color' => $this->getSentimentColor(),
                'details' => $this->sentimentDetails,
            ],
            'summary' => $this->summary,
            'entities' => $this->entities,
            'topics' => $this->topics,
            'status' => $this->status,
            'processing_time_ms' => $this->processingTimeMs,
            'created_at' => $this->createdAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
