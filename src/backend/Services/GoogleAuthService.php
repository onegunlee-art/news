<?php
/**
 * Google OAuth 2.0 인증 서비스
 */

declare(strict_types=1);

namespace App\Services;

use App\Utils\HttpClient;
use RuntimeException;

final class GoogleAuthService
{
    private array $config;
    private HttpClient $httpClient;

    public function __construct()
    {
        $configPath = dirname(__DIR__, 3) . '/config/google.php';
        if (!file_exists($configPath)) {
            throw new RuntimeException('Google configuration not found');
        }
        $this->config = require $configPath;
        $this->httpClient = new HttpClient();
    }

    private function assertGoogleCredentialsConfigured(): void
    {
        if (
            ($this->config['client_id'] ?? '') === ''
            || ($this->config['client_secret'] ?? '') === ''
        ) {
            throw new RuntimeException(
                'Google OAuth 설정 누락: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET 환경변수를 확인하세요.'
            );
        }
    }

    public function getAuthorizationUrl(string $state): string
    {
        $this->assertGoogleCredentialsConfigured();
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['oauth']['redirect_uri'],
            'response_type' => 'code',
            'scope' => $this->config['oauth']['scope'],
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
        return $this->config['oauth']['authorize_url'] . '?' . http_build_query($params);
    }

    public function getAccessToken(string $code): array
    {
        $this->assertGoogleCredentialsConfigured();
        $params = [
            'code' => $code,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['oauth']['redirect_uri'],
            'grant_type' => 'authorization_code',
        ];
        $response = $this->httpClient->postForm($this->config['oauth']['token_url'], $params);
        if (!$response->isSuccess()) {
            $error = $response->json();
            throw new RuntimeException('Failed to get access token: ' . ($error['error_description'] ?? 'Unknown error'));
        }
        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new RuntimeException('Invalid token response');
        }
        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    public function getUserInfo(string $accessToken): array
    {
        $response = $this->httpClient->get($this->config['userinfo_url'], [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        if (!$response->isSuccess()) {
            throw new RuntimeException('Failed to get user info');
        }
        $data = $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException('Invalid user info response');
        }
        return $data;
    }
}
