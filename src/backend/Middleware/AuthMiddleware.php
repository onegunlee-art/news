<?php
/**
 * 인증 미들웨어
 * 
 * JWT 토큰 검증을 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Utils\JWT;
use Closure;

/**
 * AuthMiddleware 클래스
 */
final class AuthMiddleware
{
    /**
     * 인증 필수 미들웨어
     */
    public static function required(): Closure
    {
        return function (Request $request, Closure $next): Response {
            $token = $request->bearerToken();
            
            if (!$token) {
                return Response::unauthorized('인증 토큰이 필요합니다.');
            }
            
            try {
                $jwt = new JWT();
                $payload = $jwt->decode($token);
                
                if (($payload['type'] ?? '') !== 'access') {
                    return Response::unauthorized('유효하지 않은 토큰 타입입니다.');
                }
                
                // 사용자 ID를 Request에 추가
                $request->setRouteParams(array_merge(
                    $request->param() ?? [],
                    ['auth_user_id' => $payload['user_id']]
                ));
                
                return $next();
            } catch (\RuntimeException $e) {
                return Response::unauthorized($e->getMessage());
            }
        };
    }

    /**
     * 인증 선택적 미들웨어
     */
    public static function optional(): Closure
    {
        return function (Request $request, Closure $next): Response {
            $token = $request->bearerToken();
            
            if ($token) {
                try {
                    $jwt = new JWT();
                    $payload = $jwt->decode($token);
                    
                    if (($payload['type'] ?? '') === 'access') {
                        $request->setRouteParams(array_merge(
                            $request->param() ?? [],
                            ['auth_user_id' => $payload['user_id']]
                        ));
                    }
                } catch (\RuntimeException) {
                    // 토큰이 유효하지 않아도 계속 진행
                }
            }
            
            return $next();
        };
    }

    /**
     * 관리자 전용 미들웨어
     */
    public static function admin(): Closure
    {
        return function (Request $request, Closure $next): Response {
            $token = $request->bearerToken();
            
            if (!$token) {
                return Response::unauthorized('인증 토큰이 필요합니다.');
            }
            
            try {
                $jwt = new JWT();
                $payload = $jwt->decode($token);
                
                if (($payload['role'] ?? '') !== 'admin') {
                    return Response::forbidden('관리자 권한이 필요합니다.');
                }
                
                return $next();
            } catch (\RuntimeException $e) {
                return Response::unauthorized($e->getMessage());
            }
        };
    }
}
