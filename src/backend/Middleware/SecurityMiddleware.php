<?php
/**
 * 보안 미들웨어
 * 
 * 보안 관련 HTTP 헤더를 설정합니다.
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
 * SecurityMiddleware 클래스
 */
final class SecurityMiddleware
{
    /**
     * 보안 헤더 미들웨어
     */
    public static function headers(): Closure
    {
        return function (Request $request, Closure $next): Response {
            $response = $next();
            
            if ($response instanceof Response) {
                // XSS 방지
                $response->setHeader('X-Content-Type-Options', 'nosniff');
                $response->setHeader('X-XSS-Protection', '1; mode=block');
                
                // Clickjacking 방지
                $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
                
                // Referrer Policy
                $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
                
                // Content Security Policy (API용)
                $response->setHeader(
                    'Content-Security-Policy',
                    "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
                );
                
                // 캐시 제어 (API 응답)
                if (str_contains($request->getPath(), '/api/')) {
                    $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
                    $response->setHeader('Pragma', 'no-cache');
                }
            }
            
            return $response;
        };
    }

    /**
     * HTTPS 강제 미들웨어
     */
    public static function forceHttps(): Closure
    {
        return function (Request $request, Closure $next): Response {
            if (!$request->isSecure() && !self::isLocalhost($request)) {
                $url = 'https://' . $request->server('HTTP_HOST') . $request->getUri();
                return Response::redirect($url, 301);
            }
            
            $response = $next();
            
            if ($response instanceof Response) {
                // HSTS 헤더
                $response->setHeader(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains'
                );
            }
            
            return $response;
        };
    }

    /**
     * 로컬호스트 확인
     */
    private static function isLocalhost(Request $request): bool
    {
        $ip = $request->getClientIp();
        $host = $request->server('HTTP_HOST');
        
        return in_array($ip, ['127.0.0.1', '::1']) ||
               str_starts_with($host, 'localhost') ||
               str_starts_with($host, '127.0.0.1');
    }

    /**
     * SQL Injection 방지 (입력값 검증)
     */
    public static function sanitizeInput(): Closure
    {
        return function (Request $request, Closure $next): Response {
            // 위험한 SQL 패턴 검사 (기본적인 검사)
            $patterns = [
                '/(\s|^)(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE)\s/i',
                '/(\s|^)(UNION|JOIN)\s+SELECT/i',
                '/--\s/',
                '/;\s*(SELECT|INSERT|UPDATE|DELETE|DROP)/i',
            ];
            
            $inputs = $request->input();
            
            foreach ($inputs as $key => $value) {
                if (is_string($value)) {
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            return Response::error('잘못된 입력값입니다.', 400);
                        }
                    }
                }
            }
            
            return $next();
        };
    }
}
