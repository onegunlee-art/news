<?php
/**
 * 카카오 인증 서비스 클래스
 * 
 * 카카오 OAuth 2.0 인증 플로우를 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 * @see https://developers.kakao.com/docs/latest/ko/kakaologin/rest-api
 */

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AuthServiceInterface;
use App\Utils\HttpClient;
use RuntimeException;

/**
 * KakaoAuthService 클래스
 * 
 * 카카오 로그인 API와의 통신을 담당합니다.
 */
final class KakaoAuthService implements AuthServiceInterface
{
    private array $config;
    private HttpClient $httpClient;

    /**
     * 생성자
     */
    public function __construct()
    {
        $configPath = dirname(__DIR__, 3) . '/config/kakao.php';
        
        if (!file_exists($configPath)) {
            throw new RuntimeException('Kakao configuration file not found');
        }
        
        $this->config = require $configPath;
        $this->httpClient = new HttpClient();
    }

    /**
     * {@inheritdoc}
     * 
     * 카카오 인가 URL 생성
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $params = [
            'client_id' => $this->config['rest_api_key'],
            'redirect_uri' => $this->config['oauth']['redirect_uri'],
            'response_type' => 'code',
        ];
        
        // 선택적 scope 추가
        if (!empty($this->config['oauth']['scope'])) {
            $params['scope'] = implode(',', $this->config['oauth']['scope']);
        }
        
        // CSRF 방지를 위한 state 파라미터
        if ($state !== null) {
            $params['state'] = $state;
        }
        
        return $this->config['oauth']['authorize_url'] . '?' . http_build_query($params);
    }

    /**
     * {@inheritdoc}
     * 
     * 인가 코드로 액세스 토큰 발급
     */
    public function getAccessToken(string $code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['rest_api_key'],
            'redirect_uri' => $this->config['oauth']['redirect_uri'],
            'code' => $code,
        ];

        if (!empty($this->config['client_secret'])) {
            $params['client_secret'] = $this->config['client_secret'];
        }

        $response = $this->httpClient->postForm($this->config['oauth']['token_url'], $params);
        
        if (!$response->isSuccess()) {
            $error = $response->json();
            throw new RuntimeException(
                'Failed to get access token: ' . ($error['error_description'] ?? 'Unknown error')
            );
        }
        
        $data = $response->json();
        
        if (!isset($data['access_token'])) {
            throw new RuntimeException('Invalid token response: access_token not found');
        }
        
        return [
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'refresh_token_expires_in' => $data['refresh_token_expires_in'] ?? null,
            'scope' => $data['scope'] ?? null,
        ];
    }

    /**
     * {@inheritdoc}
     * 
     * 액세스 토큰으로 사용자 정보 조회
     */
    public function getUserInfo(string $accessToken): array
    {
        $url = $this->config['api']['base_url'] . $this->config['api']['user_info'];
        
        $response = $this->httpClient
            ->setDefaultHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
            ])
            ->post($url, http_build_query([
                'property_keys' => json_encode($this->config['property_keys']),
            ]));
        
        if (!$response->isSuccess()) {
            $error = $response->json();
            throw new RuntimeException(
                'Failed to get user info: ' . ($error['msg'] ?? 'Unknown error')
            );
        }
        
        $data = $response->json();
        
        if (!isset($data['id'])) {
            throw new RuntimeException('Invalid user info response: id not found');
        }
        
        return $data;
    }

    /**
     * {@inheritdoc}
     * 
     * 리프레시 토큰으로 새 액세스 토큰 발급
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->httpClient->postForm($this->config['oauth']['token_url'], [
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['rest_api_key'],
            'refresh_token' => $refreshToken,
        ]);
        
        if (!$response->isSuccess()) {
            $error = $response->json();
            throw new RuntimeException(
                'Failed to refresh token: ' . ($error['error_description'] ?? 'Unknown error')
            );
        }
        
        $data = $response->json();
        
        return [
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'refresh_token' => $data['refresh_token'] ?? $refreshToken,
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    /**
     * {@inheritdoc}
     * 
     * 카카오 로그아웃 (토큰 만료)
     */
    public function logout(string $accessToken): bool
    {
        $url = $this->config['api']['base_url'] . $this->config['api']['logout'];
        
        $response = $this->httpClient
            ->setDefaultHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])
            ->post($url);
        
        return $response->isSuccess();
    }

    /**
     * 카카오 연결 해제 (회원 탈퇴)
     */
    public function unlink(string $accessToken): bool
    {
        $url = $this->config['api']['base_url'] . $this->config['api']['unlink'];
        
        $response = $this->httpClient
            ->setDefaultHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])
            ->post($url);
        
        return $response->isSuccess();
    }

    /**
     * 액세스 토큰 정보 조회
     */
    public function getTokenInfo(string $accessToken): array
    {
        $url = $this->config['api']['base_url'] . $this->config['api']['token_info'];
        
        $response = $this->httpClient
            ->setDefaultHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])
            ->get($url);
        
        if (!$response->isSuccess()) {
            throw new RuntimeException('Invalid access token');
        }
        
        return $response->json() ?? [];
    }

    /**
     * 토큰 유효성 검증
     */
    public function validateToken(string $accessToken): bool
    {
        try {
            $tokenInfo = $this->getTokenInfo($accessToken);
            
            // expires_in이 0보다 큰지 확인
            return isset($tokenInfo['expires_in']) && $tokenInfo['expires_in'] > 0;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 카카오계정과 함께 로그아웃 URL 생성
     */
    public function getLogoutUrl(string $logoutRedirectUri): string
    {
        $params = [
            'client_id' => $this->config['rest_api_key'],
            'logout_redirect_uri' => $logoutRedirectUri,
        ];
        
        return $this->config['oauth']['logout_url'] . '?' . http_build_query($params);
    }

    /**
     * 사용자 정보 가공
     */
    public function parseUserInfo(array $kakaoUser): array
    {
        $profile = $kakaoUser['kakao_account']['profile'] ?? [];
        $account = $kakaoUser['kakao_account'] ?? [];
        
        return [
            'id' => $kakaoUser['id'],
            'nickname' => $profile['nickname'] ?? 'User',
            'profile_image' => $profile['profile_image_url'] ?? $profile['thumbnail_image_url'] ?? null,
            'email' => $account['email'] ?? null,
            'email_verified' => $account['is_email_verified'] ?? false,
            'has_email' => $account['has_email'] ?? false,
            'connected_at' => $kakaoUser['connected_at'] ?? null,
        ];
    }
}
