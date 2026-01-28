<?php
/**
 * 뉴스 컨트롤러 클래스
 * 
 * 뉴스 관련 API 엔드포인트를 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\NewsService;
use App\Services\NYTNewsService;
use App\Services\AuthService;
use RuntimeException;

/**
 * NewsController 클래스
 */
final class NewsController
{
    private NewsService $newsService;
    private NYTNewsService $nytService;
    private AuthService $authService;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->newsService = new NewsService();
        $this->nytService = new NYTNewsService();
        $this->authService = new AuthService();
    }

    /**
     * 뉴스 목록 조회
     * 
     * GET /api/news
     */
    public function index(Request $request): Response
    {
        $page = (int) ($request->query('page', 1));
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $category = $request->query('category');
        
        try {
            if ($category) {
                $items = $this->newsService->getNewsByCategory($category, $perPage);
                return Response::success([
                    'items' => $items,
                    'category' => $category,
                ], '카테고리별 뉴스 조회 성공');
            }
            
            $items = $this->newsService->getLatestNews($perPage);
            
            return Response::success([
                'items' => $items,
                'page' => $page,
            ], '뉴스 목록 조회 성공');
        } catch (RuntimeException $e) {
            return Response::error('뉴스 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 뉴스 검색
     * 
     * GET /api/news/search
     */
    public function search(Request $request): Response
    {
        $query = $request->query('q', '');
        
        if (empty($query)) {
            return Response::error('검색어를 입력해주세요.', 400);
        }
        
        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        
        try {
            $result = $this->newsService->search($query, $page, $perPage);
            
            return Response::success($result, '검색 완료');
        } catch (RuntimeException $e) {
            return Response::error('검색 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 뉴스 상세 조회
     * 
     * GET /api/news/{id}
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        
        if ($id <= 0) {
            return Response::error('유효하지 않은 뉴스 ID입니다.', 400);
        }
        
        $news = $this->newsService->getNewsById($id);
        
        if (!$news) {
            return Response::notFound('뉴스를 찾을 수 없습니다.');
        }
        
        // 북마크 여부 확인 (로그인한 경우)
        $accessToken = $request->bearerToken();
        if ($accessToken) {
            $userId = $this->authService->getAuthenticatedUserId($accessToken);
            if ($userId) {
                $news['is_bookmarked'] = $this->newsService->isBookmarked($userId, $id);
            }
        }
        
        return Response::success($news, '뉴스 조회 성공');
    }

    /**
     * 북마크 추가
     * 
     * POST /api/news/{id}/bookmark
     */
    public function bookmark(Request $request): Response
    {
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return Response::unauthorized('로그인이 필요합니다.');
        }
        
        $userId = $this->authService->getAuthenticatedUserId($accessToken);
        
        if (!$userId) {
            return Response::unauthorized('유효하지 않은 토큰입니다.');
        }
        
        $newsId = (int) $request->param('id');
        
        if ($newsId <= 0) {
            return Response::error('유효하지 않은 뉴스 ID입니다.', 400);
        }
        
        $memo = $request->json('memo');
        
        try {
            $this->newsService->addBookmark($userId, $newsId, $memo);
            
            return Response::created(null, '북마크에 추가되었습니다.');
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 북마크 삭제
     * 
     * DELETE /api/news/{id}/bookmark
     */
    public function removeBookmark(Request $request): Response
    {
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return Response::unauthorized('로그인이 필요합니다.');
        }
        
        $userId = $this->authService->getAuthenticatedUserId($accessToken);
        
        if (!$userId) {
            return Response::unauthorized('유효하지 않은 토큰입니다.');
        }
        
        $newsId = (int) $request->param('id');
        
        if ($newsId <= 0) {
            return Response::error('유효하지 않은 뉴스 ID입니다.', 400);
        }
        
        $success = $this->newsService->removeBookmark($userId, $newsId);
        
        if ($success) {
            return Response::success(null, '북마크가 삭제되었습니다.');
        }
        
        return Response::notFound('북마크를 찾을 수 없습니다.');
    }

    /**
     * 사용자 북마크 목록
     * 
     * GET /api/user/bookmarks
     */
    public function userBookmarks(Request $request): Response
    {
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return Response::unauthorized('로그인이 필요합니다.');
        }
        
        $userId = $this->authService->getAuthenticatedUserId($accessToken);
        
        if (!$userId) {
            return Response::unauthorized('유효하지 않은 토큰입니다.');
        }
        
        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = min((int) ($request->query('per_page', 20)), 100);
        
        $result = $this->newsService->getUserBookmarks($userId, $page, $perPage);
        
        return Response::success($result, '북마크 목록 조회 성공');
    }

    /**
     * 카테고리 목록 조회
     * 
     * GET /api/news/categories
     */
    public function categories(Request $request): Response
    {
        $categories = $this->newsService->getCategories();
        
        return Response::success($categories, '카테고리 목록 조회 성공');
    }

    /**
     * 출처 목록 조회
     * 
     * GET /api/news/sources
     */
    public function sources(Request $request): Response
    {
        $sources = $this->newsService->getSources();
        
        return Response::success($sources, '출처 목록 조회 성공');
    }

    // ==========================================
    // NYT (New York Times) API 엔드포인트
    // ==========================================

    /**
     * NYT Top Stories 조회
     * 
     * GET /api/news/nyt/top
     */
    public function nytTopStories(Request $request): Response
    {
        $section = $request->query('section', 'home');
        
        try {
            $result = $this->nytService->getTopStories($section);
            
            if (!$result['success']) {
                return Response::error($result['error'] ?? 'NYT API 요청 실패', 500);
            }
            
            $articles = $this->nytService->normalizeNews($result['data'], 'top_stories');
            
            return Response::success([
                'section' => $section,
                'count' => count($articles),
                'items' => $articles,
            ], 'NYT Top Stories 조회 성공');
        } catch (RuntimeException $e) {
            return Response::error('NYT API 오류: ' . $e->getMessage(), 500);
        }
    }

    /**
     * NYT 기사 검색
     * 
     * GET /api/news/nyt/search
     */
    public function nytSearch(Request $request): Response
    {
        $query = $request->query('q', '');
        
        if (empty($query)) {
            return Response::error('검색어를 입력해주세요.', 400);
        }
        
        $options = [
            'page' => max(0, (int) ($request->query('page', 0))),
            'sort' => $request->query('sort', 'newest'),
        ];
        
        // 날짜 필터
        if ($request->query('begin_date')) {
            $options['begin_date'] = $request->query('begin_date');
        }
        if ($request->query('end_date')) {
            $options['end_date'] = $request->query('end_date');
        }
        
        try {
            $result = $this->nytService->searchArticles($query, $options);
            
            if (!$result['success']) {
                return Response::error($result['error'] ?? 'NYT 검색 실패', 500);
            }
            
            $articles = $this->nytService->normalizeNews($result['data'], 'search');
            $meta = $result['data']['response']['meta'] ?? [];
            
            return Response::success([
                'query' => $query,
                'page' => $options['page'],
                'total' => $meta['hits'] ?? count($articles),
                'items' => $articles,
            ], 'NYT 검색 완료');
        } catch (RuntimeException $e) {
            return Response::error('NYT 검색 오류: ' . $e->getMessage(), 500);
        }
    }

    /**
     * NYT 인기 기사 조회
     * 
     * GET /api/news/nyt/popular
     */
    public function nytPopular(Request $request): Response
    {
        $type = $request->query('type', 'viewed'); // viewed, shared, emailed
        $period = (int) $request->query('period', 1); // 1, 7, 30
        
        try {
            $result = $this->nytService->getMostPopular($type, $period);
            
            if (!$result['success']) {
                return Response::error($result['error'] ?? 'NYT Popular API 실패', 500);
            }
            
            $articles = $this->nytService->normalizeNews($result['data'], 'popular');
            
            return Response::success([
                'type' => $type,
                'period' => $period,
                'count' => count($articles),
                'items' => $articles,
            ], 'NYT 인기 기사 조회 성공');
        } catch (RuntimeException $e) {
            return Response::error('NYT API 오류: ' . $e->getMessage(), 500);
        }
    }

    /**
     * NYT 섹션 목록
     * 
     * GET /api/news/nyt/sections
     */
    public function nytSections(Request $request): Response
    {
        $config = require __DIR__ . '/../../../config/nyt.php';
        
        return Response::success([
            'sections' => $config['sections'],
        ], 'NYT 섹션 목록');
    }
}
