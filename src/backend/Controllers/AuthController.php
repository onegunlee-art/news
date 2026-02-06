<?php
/**
 * 인증 컨트롤러 클래스
 * 
 * 인증 관련 API 엔드포인트를 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use RuntimeException;

/**
 * AuthController 클래스
 */
final class AuthController
{
    private AuthService $authService;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * 카카오 로그인 URL로 리다이렉트
     * 
     * GET /api/auth/kakao
     */
    public function kakaoLogin(Request $request): Response
    {
        try {
            $loginUrl = $this->authService->getKakaoLoginUrl();
            
            return Response::redirect($loginUrl);
        } catch (RuntimeException $e) {
            return Response::error('카카오 로그인 URL 생성 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 카카오 콜백 처리
     * 
     * GET /api/auth/kakao/callback
     */
    public function kakaoCallback(Request $request): Response
    {
        // 에러 응답 처리
        $error = $request->query('error');
        if ($error) {
            $errorDescription = $request->query('error_description', '로그인이 취소되었습니다.');
            return $this->redirectToFrontendWithError($errorDescription);
        }
        
        // 인가 코드 확인
        $code = $request->query('code');
        if (!$code) {
            return $this->redirectToFrontendWithError('인가 코드가 없습니다.');
        }
        
        $state = $request->query('state');
        
        try {
            // 카카오 콜백 처리 (로그인/회원가입)
            $result = $this->authService->handleKakaoCallback($code, $state);
            
            // 프론트엔드로 토큰과 함께 리다이렉트
            return $this->redirectToFrontendWithToken($result);
        } catch (RuntimeException $e) {
            return $this->redirectToFrontendWithError($e->getMessage());
        }
    }

    /**
     * 토큰 갱신
     * 
     * POST /api/auth/refresh
     */
    public function refreshToken(Request $request): Response
    {
        $refreshToken = $request->json('refresh_token');
        
        if (!$refreshToken) {
            return Response::error('리프레시 토큰이 필요합니다.', 400);
        }
        
        try {
            $result = $this->authService->refreshAccessToken($refreshToken);
            
            return Response::success($result, '토큰이 갱신되었습니다.');
        } catch (RuntimeException $e) {
            return Response::unauthorized($e->getMessage());
        }
    }

    /**
     * 로그아웃
     * 
     * POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return Response::unauthorized('액세스 토큰이 필요합니다.');
        }
        
        $refreshToken = $request->json('refresh_token');
        
        $success = $this->authService->logout($accessToken, $refreshToken);
        
        if ($success) {
            return Response::success(null, '로그아웃되었습니다.');
        }
        
        return Response::error('로그아웃 처리 중 오류가 발생했습니다.', 500);
    }

    /**
     * 이메일/비밀번호 로그인
     * 
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        $email = $request->json('email');
        $password = $request->json('password');
        
        if (!$email || !$password) {
            return Response::error('이메일과 비밀번호를 입력해주세요.', 400);
        }
        
        try {
            $result = $this->authService->loginWithEmail($email, $password);
            return Response::success($result, '로그인되었습니다.');
        } catch (RuntimeException $e) {
            return Response::unauthorized($e->getMessage());
        }
    }

    /**
     * 이메일/비밀번호 회원가입
     * 
     * POST /api/auth/register
     */
    public function register(Request $request): Response
    {
        $email = $request->json('email');
        $password = $request->json('password');
        $nickname = $request->json('nickname') ?? trim($email ? explode('@', $email)[0] : '');
        
        if (!$email || !$password) {
            return Response::error('이메일과 비밀번호를 입력해주세요.', 400);
        }
        
        if (strlen($password) < 6) {
            return Response::error('비밀번호는 6자 이상이어야 합니다.', 400);
        }
        
        if (empty($nickname)) {
            $nickname = 'User';
        }
        
        try {
            $result = $this->authService->registerWithEmail($email, $password, $nickname);
            return Response::success($result, '회원가입이 완료되었습니다.');
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 현재 사용자 정보 조회
     * 
     * GET /api/auth/me
     */
    public function me(Request $request): Response
    {
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return Response::unauthorized('로그인이 필요합니다.');
        }
        
        $user = $this->authService->getUserFromToken($accessToken);
        
        if (!$user) {
            return Response::unauthorized('유효하지 않은 토큰입니다.');
        }
        
        return Response::success($user, '사용자 정보 조회 성공');
    }

    /**
     * 프론트엔드로 토큰과 함께 리다이렉트
     */
    private function redirectToFrontendWithToken(array $result): Response
    {
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        $frontendUrl = $config['url'] ?? 'https://www.thegist.com';
        
        // 토큰을 URL fragment로 전달 (보안상 query string보다 안전)
        $params = http_build_query([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
        ]);
        
        $redirectUrl = $frontendUrl . '/auth/callback#' . $params;
        
        // HTML 리다이렉트 (JavaScript로 토큰 저장 후 이동)
        return $this->htmlRedirect($redirectUrl, $result);
    }

    /**
     * 프론트엔드로 에러와 함께 리다이렉트
     */
    private function redirectToFrontendWithError(string $error): Response
    {
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        $frontendUrl = $config['url'] ?? 'https://www.thegist.com';
        
        $redirectUrl = $frontendUrl . '/auth/callback?error=' . urlencode($error);
        
        return Response::redirect($redirectUrl);
    }

    /**
     * HTML을 통한 리다이렉트 (토큰을 안전하게 전달)
     */
    private function htmlRedirect(string $url, array $tokenData): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>로그인 처리 중...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
        }
        .loading {
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #00d9ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>로그인 처리 중...</p>
    </div>
    <script>
        (function() {
            const tokenData = {$this->jsonEncode($tokenData)};
            
            // localStorage에 토큰 저장
            if (tokenData.access_token) {
                localStorage.setItem('access_token', tokenData.access_token);
                localStorage.setItem('refresh_token', tokenData.refresh_token);
                localStorage.setItem('token_expires_at', Date.now() + (tokenData.expires_in * 1000));
                
                if (tokenData.user) {
                    localStorage.setItem('user', JSON.stringify(tokenData.user));
                }
            }
            
            // 메인 페이지로 이동
            window.location.href = '/';
        })();
    </script>
</body>
</html>
HTML;

        $response = new Response();
        return $response
            ->setStatusCode(200)
            ->setContentType('text/html')
            ->setBody($html);
    }

    /**
     * JSON 인코딩 헬퍼
     */
    private function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
