<?php
/**
 * User Repository 클래스
 * 
 * 사용자 데이터 접근을 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * UserRepository 클래스
 */
final class UserRepository extends BaseRepository
{
    protected string $table = 'users';

    /**
     * 카카오 ID로 사용자 조회
     */
    public function findByKakaoId(int $kakaoId): ?array
    {
        return $this->findOneBy(['kakao_id' => $kakaoId]);
    }

    /**
     * 이메일로 사용자 조회
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * 이메일/비밀번호로 사용자 생성 (회원가입)
     */
    public function createWithPassword(string $email, string $passwordHash, string $nickname): int
    {
        $userData = [
            'email' => $email,
            'password_hash' => $passwordHash,
            'nickname' => $nickname,
            'role' => 'user',
            'status' => 'active',
        ];
        return $this->create($userData);
    }

    /**
     * 카카오 로그인으로 사용자 생성 또는 업데이트
     * @return array{0: int, 1: bool} [userId, isNewUser]
     */
    public function createOrUpdateFromKakao(array $kakaoData): array
    {
        $user = User::fromKakaoData($kakaoData);
        $existingUser = $this->findByKakaoId($user->getKakaoId());
        
        if ($existingUser) {
            $this->update($existingUser['id'], [
                'nickname' => $user->getNickname(),
                'profile_image' => $user->getProfileImage(),
                'email' => $user->getEmail(),
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
            return [(int) $existingUser['id'], false];
        }
        
        $userData = $user->toArray();
        $userData['last_login_at'] = date('Y-m-d H:i:s');
        $userId = $this->create($userData);
        return [$userId, true];
    }

    /**
     * 마지막 로그인 시간 업데이트
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 활성 사용자 목록 조회
     */
    public function findActiveUsers(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active' 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * 최근 로그인 사용자 조회
     */
    public function findRecentlyLoggedIn(int $days = 7, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'active'
                ORDER BY last_login_at DESC 
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            'days' => $days,
            'limit' => $limit,
        ]);
    }

    /**
     * 사용자 상태 업데이트
     */
    public function updateStatus(int $userId, string $status): bool
    {
        return $this->update($userId, ['status' => $status]);
    }

    /**
     * 사용자 역할 업데이트
     */
    public function updateRole(int $userId, string $role): bool
    {
        return $this->update($userId, ['role' => $role]);
    }

    /**
     * 리프레시 토큰 저장
     */
    public function saveRefreshToken(int $userId, string $token, \DateTimeImmutable $expiresAt): int
    {
        return $this->db->insert('user_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'token_type' => 'refresh',
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 리프레시 토큰 조회
     */
    public function findRefreshToken(string $token): ?array
    {
        $sql = "SELECT * FROM user_tokens 
                WHERE token = :token 
                AND token_type = 'refresh'
                AND revoked_at IS NULL
                AND expires_at > NOW()
                LIMIT 1";
        
        return $this->db->fetchOne($sql, ['token' => $token]);
    }

    /**
     * 리프레시 토큰 폐기
     */
    public function revokeRefreshToken(string $token): bool
    {
        $sql = "UPDATE user_tokens SET revoked_at = NOW() WHERE token = :token";
        
        return $this->db->executeQuery($sql, ['token' => $token])->rowCount() > 0;
    }

    /**
     * 사용자의 모든 토큰 폐기
     */
    public function revokeAllTokens(int $userId): int
    {
        $sql = "UPDATE user_tokens SET revoked_at = NOW() 
                WHERE user_id = :user_id AND revoked_at IS NULL";
        
        return $this->db->executeQuery($sql, ['user_id' => $userId])->rowCount();
    }
}
