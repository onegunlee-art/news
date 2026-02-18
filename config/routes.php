<?php
/**
 * API 라우트 설정 파일
 * 
 * 모든 API 엔드포인트를 정의합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

use App\Core\Router;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\NewsController;
use App\Controllers\AnalysisController;
use App\Controllers\AdminController;
use App\Controllers\TTSController;

/** @var Router $router */

// ==================== 헬스 체크 ====================
$router->get('/health', function (Request $request): Response {
    return Response::success([
        'status' => 'healthy',
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'server_time' => date('c'),
    ], 'Server is running');
});

// ==================== 인증 라우트 ====================
$router->group(['prefix' => '/auth'], function (Router $router) {
    // 테스트 계정 생성 (1회 실행용, 완료 후 라우트 제거 권장)
    $router->get('/seed-test-user', function (Request $request): Response {
        $messages = [];
        try {
            $db = \App\Core\Database::getInstance();
        } catch (Throwable $e) {
            return Response::error('DB 연결 실패: ' . $e->getMessage(), 500);
        }
        try {
            $db->executeQuery("ALTER TABLE `users` ADD COLUMN `password_hash` VARCHAR(255) NULL COMMENT '비밀번호 해시' AFTER `profile_image`");
            $messages[] = 'password_hash 컬럼 추가됨';
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                $messages[] = 'ALTER: ' . $e->getMessage();
            }
        }
        $email = 'test@test.com';
        $password = 'Test1234!';
        $nickname = '테스트유저';
        $existing = $db->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $email]);
        if ($existing) {
            $messages[] = '이미 test@test.com 계정이 존재합니다.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->insert('users', [
                'email' => $email,
                'password_hash' => $hash,
                'nickname' => $nickname,
                'role' => 'user',
                'status' => 'active',
            ]);
            $messages[] = '테스트 계정이 생성되었습니다.';
        }
        return Response::success([
            'messages' => $messages,
            'email' => $email,
            'password' => $password,
            'login_url' => '/login',
        ], '테스트 계정 준비 완료');
    });

    // 이메일/비밀번호 로그인
    $router->post('/login', [AuthController::class, 'login']);
    
    // 이메일/비밀번호 회원가입
    $router->post('/register', [AuthController::class, 'register']);
    
    // 카카오 로그인 URL 리다이렉트
    $router->get('/kakao', [AuthController::class, 'kakaoLogin']);
    
    // 카카오 콜백 처리
    $router->get('/kakao/callback', [AuthController::class, 'kakaoCallback']);
    
    // 토큰 갱신
    $router->post('/refresh', [AuthController::class, 'refreshToken']);
    
    // 로그아웃
    $router->post('/logout', [AuthController::class, 'logout']);
    
    // 현재 사용자 정보
    $router->get('/me', [AuthController::class, 'me']);
});

// ==================== 뉴스 라우트 ====================
$router->group(['prefix' => '/news'], function (Router $router) {
    // 뉴스 검색
    $router->get('/search', [NewsController::class, 'search']);
    
    // NYT (New York Times) API
    $router->get('/nyt/top', [NewsController::class, 'nytTopStories']);
    $router->get('/nyt/search', [NewsController::class, 'nytSearch']);
    $router->get('/nyt/popular', [NewsController::class, 'nytPopular']);
    $router->get('/nyt/sections', [NewsController::class, 'nytSections']);
    
    // 최신 뉴스 목록
    $router->get('/', [NewsController::class, 'index']);
    
    // 뉴스 상세 조회
    $router->get('/{id}', [NewsController::class, 'show']);
    
    // 뉴스 저장 (북마크) - body 기반 (id in body, 라우팅 이슈 방지)
    $router->post('/bookmark', [NewsController::class, 'bookmarkByBody']);
    $router->post('/{id}/bookmark', [NewsController::class, 'bookmark']);
    
    // 북마크 삭제
    $router->delete('/bookmark', [NewsController::class, 'removeBookmarkByBody']);
    $router->delete('/{id}/bookmark', [NewsController::class, 'removeBookmark']);
});

// ==================== 분석 라우트 ====================
$router->group(['prefix' => '/analysis'], function (Router $router) {
    // URL 기반 AI 분석 (Agent Pipeline)
    $router->post('/url', [AnalysisController::class, 'analyzeUrl']);
    
    // Agent Pipeline 상태 확인
    $router->get('/pipeline/status', [AnalysisController::class, 'pipelineStatus']);
    
    // 뉴스 분석 요청
    $router->post('/news/{id}', [AnalysisController::class, 'analyzeNews']);
    
    // 텍스트 분석 요청
    $router->post('/text', [AnalysisController::class, 'analyzeText']);
    
    // 분석 통계 조회
    $router->get('/stats', [AnalysisController::class, 'stats']);
    
    // 분석 결과 조회
    $router->get('/{id}', [AnalysisController::class, 'show']);
    
    // 사용자의 분석 내역
    $router->get('/user/history', [AnalysisController::class, 'userHistory']);
});

// ==================== TTS (기사 Listen용) ====================
$router->post('/tts/generate', [TTSController::class, 'generate']);

// ==================== 사용자 라우트 ====================
$router->group(['prefix' => '/user'], function (Router $router) {
    // 프로필 조회
    $router->get('/profile', function (Request $request): Response {
        // 인증 확인 후 프로필 반환
        return Response::success(['message' => 'Profile endpoint']);
    });
    
    // 북마크 목록
    $router->get('/bookmarks', [NewsController::class, 'userBookmarks']);
    
    // 분석 내역
    $router->get('/analyses', [AnalysisController::class, 'userHistory']);
});

// ==================== 관리자 라우트 ====================
$router->group(['prefix' => '/admin'], function (Router $router) {
    // 대시보드 통계
    $router->get('/stats', [AdminController::class, 'stats']);
    
    // 사용자 관리
    $router->get('/users', [AdminController::class, 'users']);
    $router->put('/users/{id}/status', [AdminController::class, 'updateUserStatus']);
    $router->put('/users/{id}/role', [AdminController::class, 'updateUserRole']);
    
    // 뉴스 관리
    $router->get('/news', [AdminController::class, 'getNews']);
    $router->post('/news', [AdminController::class, 'createNews']);
    $router->delete('/news/{id}', [AdminController::class, 'deleteNews']);
    
    // 최근 활동
    $router->get('/activities', [AdminController::class, 'activities']);
    
    // 시스템 설정
    $router->get('/settings', [AdminController::class, 'getSettings']);
    $router->put('/settings', [AdminController::class, 'updateSettings']);
    
    // 캐시 초기화
    $router->post('/cache/clear', [AdminController::class, 'clearCache']);

    // TTS 보이스 변경 시 전체 기사 TTS 일괄 재생성 (Supabase media_cache 갱신)
    $router->post('/tts/regenerate-all', [AdminController::class, 'regenerateAllTts']);

    // original_title 백필 (원문 HTML에서 <title> 추출)
    $router->get('/backfill-original-title', function (Request $request): Response {
        $projectRoot = dirname(__DIR__);
        $scriptPath = $projectRoot . '/api/admin/backfill-original-title-from-html.php';
        if (!is_file($scriptPath)) {
            $scriptPath = $projectRoot . '/public/api/admin/backfill-original-title-from-html.php';
        }
        if (!is_file($scriptPath)) {
            return Response::error('백필 스크립트를 찾을 수 없습니다.', 404);
        }
        $_GET = array_merge($_GET, $request->getQueryParams());
        ob_start();
        include $scriptPath;
        $output = ob_get_clean();
        $data = json_decode($output, true);
        if ($data && isset($data['success'])) {
            return $data['success']
                ? Response::success($data, $data['message'] ?? '완료')
                : Response::error($data['message'] ?? '실패', 500);
        }
        return Response::error('백필 실행 중 오류가 발생했습니다.', 500);
    });
});

// ==================== 404 처리 ====================
$router->any('/{any}', function (Request $request): Response {
    return Response::notFound('Endpoint not found');
});
