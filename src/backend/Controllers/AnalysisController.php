<?php
/**
 * 분석 컨트롤러 클래스
 * 
 * 분석 관련 API 엔드포인트를 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AnalysisService;
use App\Services\AuthService;
use RuntimeException;

/**
 * AnalysisController 클래스
 */
final class AnalysisController
{
    private AnalysisService $analysisService;
    private AuthService $authService;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->analysisService = new AnalysisService();
        $this->authService = new AuthService();
    }

    /**
     * 뉴스 분석 요청
     * 
     * POST /api/analysis/news/{id}
     */
    public function analyzeNews(Request $request): Response
    {
        $newsId = (int) $request->param('id');
        
        if ($newsId <= 0) {
            return Response::error('유효하지 않은 뉴스 ID입니다.', 400);
        }
        
        // 사용자 ID 추출 (선택적)
        $userId = null;
        $accessToken = $request->bearerToken();
        
        if ($accessToken) {
            $userId = $this->authService->getAuthenticatedUserId($accessToken);
            
            // 일일 분석 제한 확인
            if ($userId && !$this->analysisService->checkDailyLimit($userId)) {
                return Response::error('일일 분석 제한을 초과했습니다.', 429);
            }
        }
        
        try {
            $result = $this->analysisService->analyzeNews($newsId, $userId);
            
            return Response::success($result, '뉴스 분석 완료');
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 텍스트 분석 요청
     * 
     * POST /api/analysis/text
     */
    public function analyzeText(Request $request): Response
    {
        $text = $request->json('text', '');
        
        if (empty(trim($text))) {
            return Response::error('분석할 텍스트를 입력해주세요.', 400);
        }
        
        // 최대 텍스트 길이 제한
        if (mb_strlen($text) > 10000) {
            return Response::error('텍스트가 너무 깁니다. 최대 10,000자까지 가능합니다.', 400);
        }
        
        // 사용자 ID 추출 (선택적)
        $userId = null;
        $accessToken = $request->bearerToken();
        
        if ($accessToken) {
            $userId = $this->authService->getAuthenticatedUserId($accessToken);
            
            // 일일 분석 제한 확인
            if ($userId && !$this->analysisService->checkDailyLimit($userId)) {
                return Response::error('일일 분석 제한을 초과했습니다.', 429);
            }
        }
        
        try {
            $result = $this->analysisService->analyzeText($text, $userId);
            
            return Response::success($result, '텍스트 분석 완료');
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 분석 결과 조회
     * 
     * GET /api/analysis/{id}
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        
        if ($id <= 0) {
            return Response::error('유효하지 않은 분석 ID입니다.', 400);
        }
        
        $analysis = $this->analysisService->getAnalysisById($id);
        
        if (!$analysis) {
            return Response::notFound('분석 결과를 찾을 수 없습니다.');
        }
        
        return Response::success($analysis, '분석 결과 조회 성공');
    }

    /**
     * 사용자 분석 내역 조회
     * 
     * GET /api/analysis/user/history
     */
    public function userHistory(Request $request): Response
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
        
        $result = $this->analysisService->getUserAnalyses($userId, $page, $perPage);
        
        return Response::success($result, '분석 내역 조회 성공');
    }

    /**
     * 분석 통계 조회
     * 
     * GET /api/analysis/stats
     */
    public function stats(Request $request): Response
    {
        $accessToken = $request->bearerToken();
        
        if (!$accessToken) {
            return Response::unauthorized('로그인이 필요합니다.');
        }
        
        $userId = $this->authService->getAuthenticatedUserId($accessToken);
        
        if (!$userId) {
            return Response::unauthorized('유효하지 않은 토큰입니다.');
        }
        
        // 사용자 통계 계산
        $result = $this->analysisService->getUserAnalyses($userId, 1, 1000);
        $analyses = $result['items'];
        
        $stats = [
            'total_analyses' => $result['total'],
            'sentiment_distribution' => [
                'positive' => 0,
                'negative' => 0,
                'neutral' => 0,
            ],
            'recent_analyses' => array_slice($analyses, 0, 5),
        ];
        
        foreach ($analyses as $analysis) {
            $sentiment = $analysis['sentiment']['type'] ?? 'neutral';
            $stats['sentiment_distribution'][$sentiment]++;
        }
        
        return Response::success($stats, '통계 조회 성공');
    }

    /**
     * URL 기반 AI 분석 요청 (Agent Pipeline)
     * 
     * POST /api/analysis/url
     */
    public function analyzeUrl(Request $request): Response
    {
        $url = $request->json('url', '');
        
        if (empty(trim($url))) {
            return Response::error('분석할 URL을 입력해주세요.', 400);
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return Response::error('유효하지 않은 URL 형식입니다.', 400);
        }
        
        // 사용자 ID 추출 (선택적)
        $userId = null;
        $accessToken = $request->bearerToken();
        
        if ($accessToken) {
            $userId = $this->authService->getAuthenticatedUserId($accessToken);
            
            // 일일 분석 제한 확인
            if ($userId && !$this->analysisService->checkDailyLimit($userId)) {
                return Response::error('일일 분석 제한을 초과했습니다.', 429);
            }
        }
        
        // 분석 옵션
        $options = [
            'enable_tts' => $request->json('enable_tts', false),
            'enable_interpret' => $request->json('enable_interpret', true),
            'enable_learning' => $request->json('enable_learning', true)
        ];
        
        try {
            $result = $this->analysisService->analyzeUrl($url, $userId, $options);
            
            // 명확화 필요한 경우
            if (isset($result['needs_clarification']) && $result['needs_clarification']) {
                return Response::json([
                    'success' => false,
                    'needs_clarification' => true,
                    'clarification_data' => $result['clarification_data'],
                    'message' => '추가 정보가 필요합니다.'
                ], 202);
            }
            
            return Response::success($result, 'URL 분석 완료');
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Agent Pipeline 상태 확인
     * 
     * GET /api/analysis/pipeline/status
     */
    public function pipelineStatus(Request $request): Response
    {
        try {
            $status = $this->analysisService->getPipelineStatus();
            return Response::success($status, 'Pipeline 상태 조회 성공');
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 500);
        }
    }
}
