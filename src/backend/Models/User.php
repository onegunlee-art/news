<?php
/**
 * User 모델 클래스
 * 
 * 사용자 엔티티를 표현합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Models;

/**
 * User 모델
 * 
 * 사용자 데이터와 관련 비즈니스 로직을 캡슐화합니다.
 */
final class User
{
    private ?int $id = null;
    private ?int $kakaoId = null;
    private ?string $email = null;
    private string $nickname;
    private ?string $profileImage = null;
    private string $role = 'user';
    private string $status = 'active';
    private ?\DateTimeImmutable $lastLoginAt = null;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * 생성자
     */
    public function __construct(string $nickname)
    {
        $this->nickname = $nickname;
    }

    /**
     * 배열로부터 User 객체 생성
     */
    public static function fromArray(array $data): self
    {
        $user = new self($data['nickname'] ?? 'Unknown');
        
        $user->id = isset($data['id']) ? (int) $data['id'] : null;
        $user->kakaoId = isset($data['kakao_id']) ? (int) $data['kakao_id'] : null;
        $user->email = $data['email'] ?? null;
        $user->profileImage = $data['profile_image'] ?? null;
        $user->role = $data['role'] ?? 'user';
        $user->status = $data['status'] ?? 'active';
        
        if (isset($data['last_login_at'])) {
            $user->lastLoginAt = new \DateTimeImmutable($data['last_login_at']);
        }
        
        if (isset($data['created_at'])) {
            $user->createdAt = new \DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $user->updatedAt = new \DateTimeImmutable($data['updated_at']);
        }
        
        return $user;
    }

    /**
     * 카카오 사용자 데이터로부터 User 객체 생성
     */
    public static function fromKakaoData(array $kakaoData): self
    {
        $profile = $kakaoData['kakao_account']['profile'] ?? [];
        
        $user = new self($profile['nickname'] ?? 'Kakao User');
        $user->kakaoId = (int) $kakaoData['id'];
        $user->email = $kakaoData['kakao_account']['email'] ?? null;
        $user->profileImage = $profile['profile_image_url'] ?? $profile['thumbnail_image_url'] ?? null;
        
        return $user;
    }

    // ==================== Getters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKakaoId(): ?int
    {
        return $this->kakaoId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
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

    public function setKakaoId(?int $kakaoId): self
    {
        $this->kakaoId = $kakaoId;
        return $this;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setNickname(string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function setProfileImage(?string $profileImage): self
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    // ==================== 비즈니스 로직 ====================

    /**
     * 관리자인지 확인
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * 활성 사용자인지 확인
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 차단된 사용자인지 확인
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    /**
     * 배열로 변환 (데이터베이스 저장용)
     */
    public function toArray(): array
    {
        return [
            'kakao_id' => $this->kakaoId,
            'email' => $this->email,
            'nickname' => $this->nickname,
            'profile_image' => $this->profileImage,
            'role' => $this->role,
            'status' => $this->status,
        ];
    }

    /**
     * JSON 직렬화용 배열로 변환 (API 응답용)
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'profile_image' => $this->profileImage,
            'role' => $this->role,
            'created_at' => $this->createdAt?->format('c'),
        ];
    }
}
