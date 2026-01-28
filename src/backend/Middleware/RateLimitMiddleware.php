<?php
/**
 * Rate Limit 미들웨어
 * 
 * API 요청 속도 제한을 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * RateLimitMiddleware 클래스
 */
final class RateLimitMiddleware
{
    private static string $cacheDir = '';

    /**
     * Rate Limit 미들웨어 생성
     * 
     * @param int $maxRequests 최대 요청 수
     * @param int $perSeconds 기간 (초)
     */
    public static function create(int $maxRequests = 100, int $perSeconds = 60): Closure
    {
        return function (Request $request, Closure $next) use ($maxRequests, $perSeconds): Response {
            $key = self::getKey($request);
            $data = self::getData($key);
            
            $now = time();
            $windowStart = $now - $perSeconds;
            
            // 윈도우 내 요청 필터링
            $requests = array_filter($data['requests'] ?? [], fn($t) => $t > $windowStart);
            
            // 제한 확인
            if (count($requests) >= $maxRequests) {
                $retryAfter = min($requests) + $perSeconds - $now;
                
                return Response::error('요청이 너무 많습니다. 잠시 후 다시 시도해주세요.', 429)
                    ->setHeader('Retry-After', (string) max(1, $retryAfter))
                    ->setHeader('X-RateLimit-Limit', (string) $maxRequests)
                    ->setHeader('X-RateLimit-Remaining', '0')
                    ->setHeader('X-RateLimit-Reset', (string) (min($requests) + $perSeconds));
            }
            
            // 요청 기록
            $requests[] = $now;
            self::saveData($key, ['requests' => $requests]);
            
            // 응답에 Rate Limit 헤더 추가
            $response = $next();
            
            if ($response instanceof Response) {
                $remaining = $maxRequests - count($requests);
                $response->setHeader('X-RateLimit-Limit', (string) $maxRequests)
                         ->setHeader('X-RateLimit-Remaining', (string) max(0, $remaining))
                         ->setHeader('X-RateLimit-Reset', (string) ($now + $perSeconds));
            }
            
            return $response;
        };
    }

    /**
     * Rate Limit 키 생성
     */
    private static function getKey(Request $request): string
    {
        $ip = $request->getClientIp();
        $token = $request->bearerToken();
        
        if ($token) {
            return 'rate_limit_' . md5($token);
        }
        
        return 'rate_limit_' . md5($ip);
    }

    /**
     * 캐시 데이터 조회
     */
    private static function getData(string $key): array
    {
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = @file_get_contents($file);
        
        if ($content === false) {
            return [];
        }
        
        return json_decode($content, true) ?? [];
    }

    /**
     * 캐시 데이터 저장
     */
    private static function saveData(string $key, array $data): void
    {
        $file = self::getCacheFile($key);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * 캐시 파일 경로
     */
    private static function getCacheFile(string $key): string
    {
        if (empty(self::$cacheDir)) {
            self::$cacheDir = dirname(__DIR__, 3) . '/storage/cache/rate_limit';
        }
        
        return self::$cacheDir . '/' . $key . '.json';
    }
}
