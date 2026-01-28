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
    
    // 뉴스 저장 (북마크)
    $router->post('/{id}/bookmark', [NewsController::class, 'bookmark']);
    
    // 북마크 삭제
    $router->delete('/{id}/bookmark', [NewsController::class, 'removeBookmark']);
});

// ==================== 분석 라우트 ====================
$router->group(['prefix' => '/analysis'], function (Router $router) {
    // 뉴스 분석 요청
    $router->post('/news/{id}', [AnalysisController::class, 'analyzeNews']);
    
    // 텍스트 분석 요청
    $router->post('/text', [AnalysisController::class, 'analyzeText']);
    
    // 분석 결과 조회
    $router->get('/{id}', [AnalysisController::class, 'show']);
    
    // 사용자의 분석 내역
    $router->get('/user/history', [AnalysisController::class, 'userHistory']);
});

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
});

// ==================== 404 처리 ====================
$router->any('/{any}', function (Request $request): Response {
    return Response::notFound('Endpoint not found');
});
