<?php
/**
 * Rate Limit 미들웨어
 * 
 * API 요청 속도 제한을 처리합니다.
 * Redis 사용 가능 시 Redis 기반, 없으면 파일 기반 fallback
 * 
 * @author News Context Analysis Team
 * @version 2.0.0
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimitService;
use Closure;

/**
 * RateLimitMiddleware 클래스
 */
final class RateLimitMiddleware
{
    /**
     * Rate Limit 미들웨어 생성
     *
     * @param int $maxRequests 최대 요청 수
     * @param int $perSeconds 기간 (초)
     * @param string $bucket 카운터 분리용 접두사(전역·로그인 등 서로 다른 버킷)
     */
    public static function create(int $maxRequests = 100, int $perSeconds = 60, string $bucket = 'global'): Closure
    {
        return function (Request $request, Closure $next) use ($maxRequests, $perSeconds, $bucket): Response {
            $key = self::getKey($request, $bucket);
            $service = RateLimitService::getInstance();
            $result = $service->check($key, $maxRequests, $perSeconds);
            
            // 제한 확인
            if (!$result['allowed']) {
                return Response::error('요청이 너무 많습니다. 잠시 후 다시 시도해주세요.', 429)
                    ->setHeader('Retry-After', (string) ($result['retry_after'] ?? 60))
                    ->setHeader('X-RateLimit-Limit', (string) $maxRequests)
                    ->setHeader('X-RateLimit-Remaining', '0')
                    ->setHeader('X-RateLimit-Reset', (string) $result['reset']);
            }
            
            // 응답에 Rate Limit 헤더 추가
            $response = $next();
            
            if ($response instanceof Response) {
                $response->setHeader('X-RateLimit-Limit', (string) $maxRequests)
                         ->setHeader('X-RateLimit-Remaining', (string) max(0, $result['remaining']))
                         ->setHeader('X-RateLimit-Reset', (string) $result['reset']);
            }
            
            return $response;
        };
    }

    /**
     * Rate Limit 키 생성 (버킷별로 분리해 전역 한도와 라우트 한도가 같은 카운터를 쓰지 않음)
     */
    private static function getKey(Request $request, string $bucket): string
    {
        $ip = $request->getClientIp();
        $token = $request->bearerToken();

        if ($token) {
            return $bucket . ':user_' . md5($token);
        }

        return $bucket . ':ip_' . $ip;
    }
}
