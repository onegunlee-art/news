<?php
/**
 * JWT 유틸리티 클래스
 * 
 * JSON Web Token 생성 및 검증을 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Utils;

use RuntimeException;

/**
 * JWT 클래스
 * 
 * HS256 알고리즘을 사용한 JWT 처리를 담당합니다.
 */
final class JWT
{
    private string $secret;
    private int $expiry;
    private string $algorithm = 'HS256';

    /**
     * 생성자
     */
    public function __construct(?string $secret = null, ?int $expiry = null)
    {
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        
        $this->secret = $secret ?? ($config['security']['jwt_secret'] ?? '');
        $this->expiry = $expiry ?? ($config['security']['jwt_expiry'] ?? 3600);
        
        if (empty($this->secret)) {
            throw new RuntimeException('JWT secret is not configured');
        }
    }

    /**
     * JWT 토큰 생성
     * 
     * @param array $payload 페이로드 데이터
     * @param int|null $expiry 만료 시간 (초)
     * @return string JWT 토큰
     */
    public function encode(array $payload, ?int $expiry = null): string
    {
        $expiry = $expiry ?? $this->expiry;
        
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm,
        ];
        
        $payload['iat'] = $payload['iat'] ?? time();
        $payload['exp'] = $payload['exp'] ?? (time() + $expiry);
        
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        
        $signingInput = implode('.', $segments);
        $signature = $this->sign($signingInput);
        $segments[] = $this->base64UrlEncode($signature);
        
        return implode('.', $segments);
    }

    /**
     * JWT 토큰 디코딩 및 검증
     * 
     * @param string $token JWT 토큰
     * @return array 페이로드 데이터
     * @throws RuntimeException 검증 실패 시
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token structure');
        }
        
        [$headerB64, $payloadB64, $signatureB64] = $parts;
        
        // 헤더 검증
        $header = json_decode($this->base64UrlDecode($headerB64), true);
        
        if ($header === null || ($header['alg'] ?? '') !== $this->algorithm) {
            throw new RuntimeException('Invalid token header');
        }
        
        // 서명 검증
        $signingInput = "{$headerB64}.{$payloadB64}";
        $signature = $this->base64UrlDecode($signatureB64);
        
        if (!$this->verify($signingInput, $signature)) {
            throw new RuntimeException('Invalid token signature');
        }
        
        // 페이로드 디코딩
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        
        if ($payload === null) {
            throw new RuntimeException('Invalid token payload');
        }
        
        // 만료 검증
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new RuntimeException('Token has expired');
        }
        
        // nbf (Not Before) 검증
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new RuntimeException('Token is not yet valid');
        }
        
        return $payload;
    }

    /**
     * 토큰 유효성 검증 (예외 없이)
     * 
     * @param string $token JWT 토큰
     * @return bool 유효 여부
     */
    public function isValid(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 토큰 만료 여부 확인
     * 
     * @param string $token JWT 토큰
     * @return bool 만료 여부
     */
    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->decode($token);
            return isset($payload['exp']) && $payload['exp'] < time();
        } catch (RuntimeException) {
            return true;
        }
    }

    /**
     * 토큰에서 페이로드 추출 (검증 없이)
     * 
     * @param string $token JWT 토큰
     * @return array|null 페이로드 또는 null
     */
    public function getPayloadWithoutVerification(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        
        return $payload ?: null;
    }

    /**
     * 리프레시 토큰 생성
     * 
     * @param int $userId 사용자 ID
     * @return string 리프레시 토큰
     */
    public function createRefreshToken(int $userId): string
    {
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        $refreshExpiry = $config['security']['jwt_refresh_expiry'] ?? 604800;
        
        return $this->encode([
            'user_id' => $userId,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
        ], $refreshExpiry);
    }

    /**
     * 액세스 토큰 생성
     * 
     * @param int $userId 사용자 ID
     * @param array $claims 추가 클레임
     * @return string 액세스 토큰
     */
    public function createAccessToken(int $userId, array $claims = []): string
    {
        return $this->encode(array_merge([
            'user_id' => $userId,
            'type' => 'access',
        ], $claims));
    }

    /**
     * 서명 생성
     */
    private function sign(string $input): string
    {
        return hash_hmac('sha256', $input, $this->secret, true);
    }

    /**
     * 서명 검증
     */
    private function verify(string $input, string $signature): bool
    {
        $expected = $this->sign($input);
        
        return hash_equals($expected, $signature);
    }

    /**
     * Base64 URL 인코딩
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL 디코딩
     */
    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
