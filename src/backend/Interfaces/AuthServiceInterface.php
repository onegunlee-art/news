<?php
/**
 * 인증 서비스 인터페이스
 * 
 * 인증 관련 서비스가 구현해야 하는 계약을 정의합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Interfaces;

/**
 * AuthServiceInterface
 * 
 * OAuth 및 인증 처리에 필요한 메서드를 정의합니다.
 */
interface AuthServiceInterface
{
    /**
     * 인가 URL 생성
     * 
     * @param string|null $state CSRF 방지를 위한 상태값
     * @return string 인가 요청 URL
     */
    public function getAuthorizationUrl(?string $state = null): string;

    /**
     * 인가 코드로 액세스 토큰 발급
     * 
     * @param string $code 인가 코드
     * @return array 토큰 정보 ['access_token', 'refresh_token', 'expires_in', ...]
     */
    public function getAccessToken(string $code): array;

    /**
     * 액세스 토큰으로 사용자 정보 조회
     * 
     * @param string $accessToken 액세스 토큰
     * @return array 사용자 정보
     */
    public function getUserInfo(string $accessToken): array;

    /**
     * 토큰 갱신
     * 
     * @param string $refreshToken 리프레시 토큰
     * @return array 새로운 토큰 정보
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * 로그아웃 처리
     * 
     * @param string $accessToken 액세스 토큰
     * @return bool 성공 여부
     */
    public function logout(string $accessToken): bool;
}
