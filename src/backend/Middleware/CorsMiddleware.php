<?php
/**
 * CORS 미들웨어
 * 
 * Cross-Origin Resource Sharing 설정을 처리합니다.
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
 * CorsMiddleware 클래스
 */
final class CorsMiddleware
{
    /**
     * CORS 미들웨어 생성
     */
    public static function create(array $options = []): Closure
    {
        $defaults = [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
            'max_age' => 86400,
            'supports_credentials' => false,
        ];
        
        $config = array_merge($defaults, $options);
        
        return function (Request $request, Closure $next) use ($config): Response {
            $origin = $request->getHeader('Origin');
            
            // Preflight 요청 처리
            if ($request->isMethod('OPTIONS')) {
                return self::handlePreflight($request, $config);
            }
            
            // 일반 요청 처리
            $response = $next();
            
            if ($response instanceof Response) {
                self::addCorsHeaders($response, $origin, $config);
            }
            
            return $response;
        };
    }

    /**
     * Preflight 요청 처리
     */
    private static function handlePreflight(Request $request, array $config): Response
    {
        $origin = $request->getHeader('Origin');
        $response = Response::noContent();
        
        self::addCorsHeaders($response, $origin, $config);
        
        // Preflight 캐시
        $response->setHeader('Access-Control-Max-Age', (string) $config['max_age']);
        
        return $response;
    }

    /**
     * CORS 헤더 추가
     */
    private static function addCorsHeaders(Response $response, ?string $origin, array $config): void
    {
        // Origin 검증
        $allowedOrigin = '*';
        
        if (!in_array('*', $config['allowed_origins']) && $origin) {
            if (in_array($origin, $config['allowed_origins'])) {
                $allowedOrigin = $origin;
            } else {
                return; // 허용되지 않은 origin
            }
        } elseif ($config['supports_credentials'] && $origin) {
            $allowedOrigin = $origin;
        }
        
        $response->setHeader('Access-Control-Allow-Origin', $allowedOrigin);
        
        if ($config['supports_credentials']) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        if (!empty($config['allowed_methods'])) {
            $response->setHeader(
                'Access-Control-Allow-Methods',
                implode(', ', $config['allowed_methods'])
            );
        }
        
        if (!empty($config['allowed_headers'])) {
            $response->setHeader(
                'Access-Control-Allow-Headers',
                implode(', ', $config['allowed_headers'])
            );
        }
        
        if (!empty($config['exposed_headers'])) {
            $response->setHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $config['exposed_headers'])
            );
        }
    }
}
