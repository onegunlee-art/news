<?php
/**
 * 인증 서비스 클래스
 * 
 * 사용자 인증 및 세션 관리를 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Utils\JWT;
use RuntimeException;

/**
 * AuthService 클래스
 * 
 * JWT 기반 인증 처리를 담당합니다.
 */
final class AuthService
{
    private UserRepository $userRepository;
    private JWT $jwt;
    private KakaoAuthService $kakaoAuth;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->jwt = new JWT();
        $this->kakaoAuth = new KakaoAuthService();
    }

    /**
     * 카카오 로그인 URL 반환
     */
    public function getKakaoLoginUrl(): string
    {
        // CSRF 방지를 위한 state 생성
        $state = bin2hex(random_bytes(16));
        
        // 세션에 state 저장
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth_state'] = $state;
        
        return $this->kakaoAuth->getAuthorizationUrl($state);
    }

    /**
     * 카카오 콜백 처리 (인가 코드로 로그인)
     */
    public function handleKakaoCallback(string $code, ?string $state = null): array
    {
        // State 검증 (CSRF 방지)
        if ($state !== null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $savedState = $_SESSION['oauth_state'] ?? null;
            unset($_SESSION['oauth_state']);
            
            if ($savedState !== $state) {
                throw new RuntimeException('Invalid state parameter');
            }
        }
        
        // 1. 인가 코드로 액세스 토큰 발급
        $tokenData = $this->kakaoAuth->getAccessToken($code);
        
        // 2. 액세스 토큰으로 사용자 정보 조회
        $kakaoUser = $this->kakaoAuth->getUserInfo($tokenData['access_token']);
        
        // 3. 사용자 생성 또는 업데이트
        $userId = $this->userRepository->createOrUpdateFromKakao($kakaoUser);
        
        // 4. 사용자 정보 조회
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData) {
            throw new RuntimeException('Failed to retrieve user data');
        }
        
        // 5. JWT 토큰 발급
        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        
        $refreshToken = $this->jwt->createRefreshToken($userId);
        
        // 6. 리프레시 토큰 저장
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);
        
        return [
            'user' => User::fromArray($userData)->toJson(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400, // 24시간
        ];
    }

    /**
     * 토큰 갱신
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        // 1. 리프레시 토큰 검증
        $tokenData = $this->userRepository->findRefreshToken($refreshToken);
        
        if (!$tokenData) {
            throw new RuntimeException('Invalid or expired refresh token');
        }
        
        // 2. JWT 페이로드 검증
        $payload = $this->jwt->decode($refreshToken);
        
        if (($payload['type'] ?? '') !== 'refresh') {
            throw new RuntimeException('Invalid token type');
        }
        
        $userId = (int) $payload['user_id'];
        
        // 3. 사용자 정보 조회
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData || $userData['status'] !== 'active') {
            throw new RuntimeException('User not found or inactive');
        }
        
        // 4. 기존 리프레시 토큰 폐기
        $this->userRepository->revokeRefreshToken($refreshToken);
        
        // 5. 새 토큰 발급
        $newAccessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        
        $newRefreshToken = $this->jwt->createRefreshToken($userId);
        
        // 6. 새 리프레시 토큰 저장
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $newRefreshToken, $refreshExpiry);
        
        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ];
    }

    /**
     * 로그아웃
     */
    public function logout(string $accessToken, ?string $refreshToken = null): bool
    {
        try {
            // 액세스 토큰에서 사용자 ID 추출
            $payload = $this->jwt->decode($accessToken);
            $userId = (int) $payload['user_id'];
            
            // 리프레시 토큰이 제공된 경우 해당 토큰만 폐기
            if ($refreshToken) {
                $this->userRepository->revokeRefreshToken($refreshToken);
            } else {
                // 아니면 모든 토큰 폐기
                $this->userRepository->revokeAllTokens($userId);
            }
            
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 토큰에서 사용자 정보 추출
     */
    public function getUserFromToken(string $accessToken): ?array
    {
        try {
            $payload = $this->jwt->decode($accessToken);
            
            if (($payload['type'] ?? '') !== 'access') {
                return null;
            }
            
            $userId = (int) $payload['user_id'];
            $userData = $this->userRepository->findById($userId);
            
            if (!$userData || $userData['status'] !== 'active') {
                return null;
            }
            
            return User::fromArray($userData)->toJson();
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * 토큰 유효성 검증
     */
    public function validateAccessToken(string $accessToken): bool
    {
        try {
            $payload = $this->jwt->decode($accessToken);
            
            return ($payload['type'] ?? '') === 'access';
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 현재 인증된 사용자 ID 반환
     */
    public function getAuthenticatedUserId(string $accessToken): ?int
    {
        try {
            $payload = $this->jwt->decode($accessToken);
            
            if (($payload['type'] ?? '') !== 'access') {
                return null;
            }
            
            return (int) $payload['user_id'];
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * 사용자 ID로 사용자 정보 조회
     */
    public function getUserById(int $userId): ?array
    {
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData) {
            return null;
        }
        
        return User::fromArray($userData)->toJson();
    }

    /**
     * 이메일/비밀번호 로그인
     */
    public function loginWithEmail(string $email, string $password): array
    {
        $userData = $this->userRepository->findByEmail($email);
        
        if (!$userData || $userData['status'] !== 'active') {
            throw new RuntimeException('이메일 또는 비밀번호가 올바르지 않습니다.');
        }
        
        $passwordHash = $userData['password_hash'] ?? null;
        if (!$passwordHash || !password_verify($password, $passwordHash)) {
            throw new RuntimeException('이메일 또는 비밀번호가 올바르지 않습니다.');
        }
        
        $userId = (int) $userData['id'];
        $this->userRepository->updateLastLogin($userId);
        
        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        $refreshToken = $this->jwt->createRefreshToken($userId);
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);
        
        $user = User::fromArray($userData)->toJson();
        
        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ];
    }

    /**
     * 이메일/비밀번호 회원가입
     */
    public function registerWithEmail(string $email, string $password, string $nickname): array
    {
        if ($this->userRepository->findByEmail($email)) {
            throw new RuntimeException('이미 사용 중인 이메일입니다.');
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('비밀번호 처리 중 오류가 발생했습니다.');
        }
        
        $userId = $this->userRepository->createWithPassword($email, $passwordHash, $nickname);
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData) {
            throw new RuntimeException('회원가입 처리 중 오류가 발생했습니다.');
        }
        
        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        $refreshToken = $this->jwt->createRefreshToken($userId);
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);
        
        $user = User::fromArray($userData)->toJson();
        
        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ];
    }
}
