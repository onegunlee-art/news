<?php
/**
 * 관리자 컨트롤러 클래스
 * 
 * 관리자 대시보드 및 관리 기능 API를 처리합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\AuthService;
use RuntimeException;
use PDO;

/**
 * AdminController 클래스
 */
final class AdminController
{
    private AuthService $authService;
    private PDO $db;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 관리자 권한 확인
     */
    private function checkAdminAuth(Request $request): ?int
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        $userId = $this->authService->getAuthenticatedUserId($token);
        
        if (!$userId) {
            return null;
        }
        
        // 관리자 권한 확인
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['role'] !== 'admin') {
            return null;
        }
        
        return $userId;
    }

    /**
     * 대시보드 통계 조회
     * 
     * GET /api/admin/stats
     */
    public function stats(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        
        try {
            // 전체 사용자 수
            $stmt = $this->db->query("SELECT COUNT(*) FROM users");
            $totalUsers = (int) $stmt->fetchColumn();
            
            // 전체 뉴스 수
            $stmt = $this->db->query("SELECT COUNT(*) FROM news");
            $totalNews = (int) $stmt->fetchColumn();
            
            // 전체 분석 수
            $stmt = $this->db->query("SELECT COUNT(*) FROM analyses");
            $totalAnalyses = (int) $stmt->fetchColumn();
            
            // 오늘 가입한 사용자
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
            $todayUsers = (int) $stmt->fetchColumn();
            
            // 오늘 분석 수
            $stmt = $this->db->query("SELECT COUNT(*) FROM analyses WHERE DATE(created_at) = CURDATE()");
            $todayAnalyses = (int) $stmt->fetchColumn();
            
            return Response::success([
                'totalUsers' => $totalUsers,
                'totalNews' => $totalNews,
                'totalAnalyses' => $totalAnalyses,
                'todayUsers' => $todayUsers,
                'todayAnalyses' => $todayAnalyses,
                'apiStatus' => [
                    'nyt' => $this->checkNytApi(),
                    'kakao' => $this->checkKakaoApi(),
                    'database' => true,
                ],
            ], '통계 조회 성공');
            
        } catch (RuntimeException $e) {
            return Response::error('통계 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 사용자 목록 조회
     * 
     * GET /api/admin/users
     */
    public function users(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min((int) $request->query('per_page', 20), 100);
        $offset = ($page - 1) * $perPage;
        $filter = $request->query('filter', 'all');
        if (!in_array($filter, ['all', 'subscribed', 'unsubscribed', 'expiring'], true)) {
            $filter = 'all';
        }

        try {
            $where = '1=1';
            $params = [];
            if ($filter === 'subscribed') {
                $where = 'is_subscribed = 1';
            } elseif ($filter === 'unsubscribed') {
                $where = 'is_subscribed = 0';
            } elseif ($filter === 'expiring') {
                $where = 'is_subscribed = 1 AND subscription_expires_at IS NOT NULL AND subscription_expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)';
            }

            // 전체 수 (필터 적용)
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE $where");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            // 통계 (필터 없음)
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) AS total,
                    SUM(is_subscribed = 1) AS subscribed,
                    SUM(is_subscribed = 0) AS unsubscribed,
                    SUM(created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS new_this_month
                FROM users
            ");
            $statsRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats = [
                'total' => (int) ($statsRow['total'] ?? 0),
                'subscribed' => (int) ($statsRow['subscribed'] ?? 0),
                'unsubscribed' => (int) ($statsRow['unsubscribed'] ?? 0),
                'new_this_month' => (int) ($statsRow['new_this_month'] ?? 0),
            ];

            // 사용자 목록
            $stmt = $this->db->prepare("
                SELECT id, email, nickname, profile_image, role, status,
                       kakao_id, is_subscribed, subscription_expires_at,
                       last_login_at, created_at
                FROM users
                WHERE $where
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Response::success([
                'items' => $users,
                'stats' => $stats,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
                ],
            ], '사용자 목록 조회 성공');

        } catch (RuntimeException $e) {
            return Response::error('사용자 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 사용자 상세 조회 (사용 통계 포함)
     *
     * GET /api/admin/users/{id}
     */
    public function userDetail(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $userId = (int) $request->param('id');
        if ($userId <= 0) {
            return Response::error('유효하지 않은 사용자 ID입니다.', 400);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, email, nickname, profile_image, role, status,
                       kakao_id, is_subscribed, subscription_expires_at,
                       subscription_plan, subscription_start_date,
                       steppay_customer_id, steppay_subscription_id,
                       last_login_at, created_at, updated_at
                FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return Response::notFound('사용자를 찾을 수 없습니다.');
            }

            $analysesCount = 0;
            $bookmarksCount = 0;
            $searchCount = 0;

            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM analyses WHERE user_id = ?");
                $stmt->execute([$userId]);
                $analysesCount = (int) $stmt->fetchColumn();
            } catch (\Throwable $e) { /* table may not exist */ }

            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookmarks WHERE user_id = ?");
                $stmt->execute([$userId]);
                $bookmarksCount = (int) $stmt->fetchColumn();
            } catch (\Throwable $e) { /* table may not exist */ }

            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM search_history WHERE user_id = ?");
                $stmt->execute([$userId]);
                $searchCount = (int) $stmt->fetchColumn();
            } catch (\Throwable $e) { /* table may not exist */ }

            $user['usage'] = [
                'analyses_count' => $analysesCount,
                'bookmarks_count' => $bookmarksCount,
                'search_count' => $searchCount,
            ];

            return Response::success($user, '사용자 상세 조회 성공');
        } catch (RuntimeException $e) {
            return Response::error('사용자 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 사용자 상태 변경
     * 
     * PUT /api/admin/users/{id}/status
     */
    public function updateUserStatus(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $userId = (int) $request->param('id');
        $status = $request->json('status');
        
        if (!in_array($status, ['active', 'inactive', 'banned'])) {
            return Response::error('유효하지 않은 상태입니다.', 400);
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return Response::notFound('사용자를 찾을 수 없습니다.');
            }
            
            return Response::success(null, '사용자 상태가 변경되었습니다.');
            
        } catch (RuntimeException $e) {
            return Response::error('상태 변경 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 사용자 역할 변경
     * 
     * PUT /api/admin/users/{id}/role
     */
    public function updateUserRole(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $userId = (int) $request->param('id');
        $role = $request->json('role');
        
        if (!in_array($role, ['user', 'admin'])) {
            return Response::error('유효하지 않은 역할입니다.', 400);
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return Response::notFound('사용자를 찾을 수 없습니다.');
            }
            
            return Response::success(null, '사용자 역할이 변경되었습니다.');
            
        } catch (RuntimeException $e) {
            return Response::error('역할 변경 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 사용자 구독 상태 수동 수정
     *
     * PUT /api/admin/users/{id}/subscription
     */
    public function updateUserSubscription(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $userId = (int) $request->param('id');
        $isSubscribed = $request->json('is_subscribed');
        $expiresAt = $request->json('subscription_expires_at');

        if ($isSubscribed === null) {
            return Response::error('is_subscribed 값이 필요합니다.', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET is_subscribed = ?, subscription_expires_at = ? WHERE id = ?"
            );
            $stmt->execute([(int) $isSubscribed, $expiresAt ?: null, $userId]);

            if ($stmt->rowCount() === 0) {
                return Response::notFound('사용자를 찾을 수 없습니다.');
            }

            return Response::success(null, '구독 상태가 변경되었습니다.');
        } catch (RuntimeException $e) {
            return Response::error('구독 상태 변경 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 최근 활동 조회
     * 
     * GET /api/admin/activities
     */
    public function activities(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $limit = min((int) $request->query('limit', 10), 50);
        
        try {
            $activities = [];
            
            // 최근 가입한 사용자
            $stmt = $this->db->prepare("
                SELECT 'user' as type, nickname as message, created_at as time 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $userActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($userActivities as $activity) {
                $activities[] = [
                    'type' => 'user',
                    'message' => $activity['message'] . '님이 가입했습니다',
                    'time' => $this->timeAgo($activity['time']),
                ];
            }
            
            // 최근 분석 (analyses에 title 없음 → news JOIN 또는 summary 사용)
            $stmt = $this->db->prepare("
                SELECT 'analysis' as type,
                    COALESCE(n.title, LEFT(a.summary, 80), LEFT(a.input_text, 80), '제목 없음') as message,
                    a.created_at as time
                FROM analyses a
                LEFT JOIN news n ON a.news_id = n.id
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $analysisActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($analysisActivities as $activity) {
                $activities[] = [
                    'type' => 'analysis',
                    'message' => '분석 완료: ' . mb_substr($activity['message'] ?? '제목 없음', 0, 30),
                    'time' => $this->timeAgo($activity['time']),
                ];
            }
            
            // 시간순 정렬
            usort($activities, function($a, $b) {
                return strcmp($b['time'], $a['time']);
            });
            
            return Response::success(array_slice($activities, 0, $limit), '활동 조회 성공');
            
        } catch (RuntimeException $e) {
            return Response::error('활동 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 시스템 설정 조회
     * 
     * GET /api/admin/settings
     */
    public function getSettings(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        try {
            $stmt = $this->db->query("SELECT `key`, `value` FROM settings");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['key']] = $row['value'];
            }
            
            return Response::success($settings, '설정 조회 성공');
            
        } catch (RuntimeException $e) {
            return Response::error('설정 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 시스템 설정 업데이트
     * 
     * PUT /api/admin/settings
     */
    public function updateSettings(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $settings = $request->json();
        
        if (!is_array($settings)) {
            return Response::error('유효하지 않은 설정입니다.', 400);
        }
        
        try {
            foreach ($settings as $key => $value) {
                $valueStr = is_scalar($value) ? (string) $value : json_encode($value);
                $stmt = $this->db->prepare("
                    INSERT INTO settings (`key`, `value`, `type`) 
                    VALUES (?, ?, 'string') 
                    ON DUPLICATE KEY UPDATE `value` = ?
                ");
                $stmt->execute([$key, $valueStr, $valueStr]);
            }
            
            return Response::success(null, '설정이 저장되었습니다.');
            
        } catch (RuntimeException $e) {
            return Response::error('설정 저장 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 뉴스 작성
     * 
     * POST /api/admin/news
     */
    public function createNews(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $category = $request->json('category');
        $title = $request->json('title');
        $content = $request->json('content');
        
        if (empty($title) || empty($content)) {
            return Response::error('제목과 내용을 입력해주세요.', 400);
        }
        
        if (!in_array($category, ['diplomacy', 'economy', 'technology', 'entertainment'])) {
            return Response::error('유효하지 않은 카테고리입니다.', 400);
        }
        
        try {
            // 고유한 URL 생성 (Admin 작성 뉴스용)
            $uniqueUrl = 'admin://news/' . uniqid() . '-' . time();
            
            // description은 content의 앞부분 사용 (최대 300자)
            $description = mb_substr(strip_tags($content), 0, 300);
            
            $stmt = $this->db->prepare("
                INSERT INTO news (category, title, description, content, source, url, created_at)
                VALUES (?, ?, ?, ?, 'Admin', ?, NOW())
            ");
            $stmt->execute([$category, $title, $description, $content, $uniqueUrl]);
            
            $newsId = $this->db->lastInsertId();
            
            return Response::created([
                'id' => $newsId,
                'category' => $category,
                'title' => $title,
            ], '뉴스가 저장되었습니다.');
            
        } catch (RuntimeException $e) {
            return Response::error('뉴스 저장 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 뉴스 목록 조회 (관리자용)
     * 
     * GET /api/admin/news
     */
    public function getNews(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $category = $request->query('category');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min((int) $request->query('per_page', 20), 100);
        $offset = ($page - 1) * $perPage;
        
        try {
            $where = '';
            $params = [];
            
            if ($category) {
                $where = 'WHERE category = ?';
                $params[] = $category;
            }
            
            // 전체 수
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM news $where");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
            
            // 뉴스 목록
            $params[] = $perPage;
            $params[] = $offset;
            $stmt = $this->db->prepare("
                SELECT id, category, title, content, source, url, created_at
                FROM news 
                $where
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'items' => $news,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                ],
            ], '뉴스 목록 조회 성공');
            
        } catch (RuntimeException $e) {
            return Response::error('뉴스 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 뉴스 삭제
     * 
     * DELETE /api/admin/news/{id}
     */
    public function deleteNews(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        $newsId = (int) $request->param('id');
        
        if ($newsId <= 0) {
            return Response::error('유효하지 않은 뉴스 ID입니다.', 400);
        }
        
        try {
            $stmt = $this->db->prepare("DELETE FROM news WHERE id = ?");
            $stmt->execute([$newsId]);
            
            if ($stmt->rowCount() === 0) {
                return Response::notFound('뉴스를 찾을 수 없습니다.');
            }
            
            return Response::success(null, '뉴스가 삭제되었습니다.');
            
        } catch (RuntimeException $e) {
            return Response::error('뉴스 삭제 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 캐시 초기화
     * 
     * POST /api/admin/cache/clear
     */
    public function clearCache(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        try {
            $cacheDir = __DIR__ . '/../../../storage/cache';
            
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                    }
                }
            }
            
            return Response::success(null, '캐시가 초기화되었습니다.');
            
        } catch (RuntimeException $e) {
            return Response::error('캐시 초기화 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * TTS 일괄 재생성 (보이스/매체설명 변경 시 전체 기사 Listen용 TTS 재생성)
     * POST /api/admin/tts/regenerate-all
     * Body: { "force"?: bool, "offset"?: int, "limit"?: int }
     *   force: 기존 캐시 무시, offset/limit: 배치 처리 (504 타임아웃 방지)
     * Returns: { generated, skipped, total, offset, has_more, message }
     */
    public function regenerateAllTts(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }
        set_time_limit(360); // 배치당 최대 6분 (3배 연장)
        $body = $request->json() ?? [];
        $force = isset($body['force']) && $body['force'] === true;
        $offset = isset($body['offset']) && is_numeric($body['offset']) ? max(0, (int) $body['offset']) : 0;
        $limit = isset($body['limit']) && is_numeric($body['limit']) ? min(50, max(1, (int) $body['limit'])) : 1;

        $projectRoot = dirname(__DIR__, 3);
        $ttsVoice = $this->getSetting('tts_voice');
        $config = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];
        $apiKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
        if (is_string($apiKey) && $apiKey !== '') {
            $config['api_key'] = $apiKey;
        }
        $config['default_voice'] = $ttsVoice ?: ($config['default_voice'] ?? 'ko-KR-Standard-A');
        $ttsVoice = $config['default_voice'];

        if (!file_exists($projectRoot . '/src/agents/services/GoogleTTSService.php')) {
            return Response::error('TTS 서비스 파일을 찾을 수 없습니다.', 503);
        }
        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';
        $service = new \Agents\Services\GoogleTTSService($config);
        if (!$service->isConfigured()) {
            return Response::error('Google TTS API 키가 설정되지 않았습니다.', 503);
        }

        $supabase = $this->getSupabaseService($projectRoot);

        if ($force && $offset === 0) {
            $audioDir = $projectRoot . '/storage/audio';
            if (is_dir($audioDir)) {
                foreach (glob($audioDir . '/tts_*.wav') ?: [] as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            if ($supabase !== null && $supabase->isConfigured()) {
                try {
                    $supabase->delete('media_cache', 'media_type=eq.tts');
                } catch (\Throwable $e) {
                    error_log('[regenerateAllTts] Supabase media_cache 삭제 실패: ' . $e->getMessage());
                }
            }
        }

        $columns = 'id, title, content, description, source, url';
        $optCols = ['why_important', 'narration', 'published_at', 'updated_at', 'created_at', 'original_source', 'original_title', 'source_url'];
        foreach ($optCols as $col) {
            try {
                $chk = $this->db->query("SHOW COLUMNS FROM news LIKE '{$col}'");
                if ($chk && $chk->rowCount() > 0) {
                    $columns .= ', ' . $col;
                }
            } catch (\Throwable $e) {
                // 컬럼 없음
            }
        }

        $stmt = $this->db->query("SELECT {$columns} FROM news ORDER BY id ASC");
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_slice($allRows, $offset, $limit);
        $total = count($allRows);
        $hasMore = ($offset + count($rows)) < $total;

        $generated = 0;
        $skipped = 0;

        $storageDir = $projectRoot . '/storage/audio';

        foreach ($rows as $row) {
            $parts = $this->buildListenStructured($row);
            if ($parts === null) {
                $skipped++;
                continue;
            }
            [$title, $meta, $narration, $critiquePart] = $parts;
            // Listen과 동일한 캐시 키 (재생 순서: The Gist → narration)
            $fullPayload = $title . '|' . $meta . '|' . $critiquePart . '|' . $narration . '|' . $ttsVoice;
            $cacheHash = hash('sha256', $fullPayload);

            if (!$force) {
                // 파일 캐시
                $safeHash = preg_replace('/[^a-f0-9]/', '', $cacheHash);
                if (is_file($storageDir . '/tts_' . $safeHash . '.wav')) {
                    $skipped++;
                    continue;
                }

                if ($supabase !== null && $supabase->isConfigured()) {
                    $cacheQuery = 'media_type=eq.tts&generation_params->>hash=eq.' . rawurlencode($cacheHash);
                    $cached = $supabase->select('media_cache', $cacheQuery, 1);
                    if (!empty($cached) && is_array($cached) && !empty($cached[0]['file_url'])) {
                        $skipped++;
                        continue;
                    }
                }
            }

            $url = $service->textToSpeechStructured(
                $title ?: '제목 없음',
                $meta ?: ' ',
                $narration,
                ['voice' => $ttsVoice, 'cache_hash' => $cacheHash],
                $critiquePart
            );
            if ($url === null || $url === '') {
                $skipped++;
                continue;
            }

            if ($supabase !== null && $supabase->isConfigured()) {
                $supabase->insert('media_cache', [
                    'news_id' => (int) $row['id'],
                    'media_type' => 'tts',
                    'file_url' => $url,
                    'generation_params' => ['hash' => $cacheHash, 'voice' => $ttsVoice],
                ]);
            }
            $generated++;
        }

        return Response::success([
            'generated' => $generated,
            'skipped' => $skipped,
            'total' => $total,
            'offset' => $offset,
            'has_more' => $hasMore,
        ], $hasMore
            ? "배치 완료: {$generated}건 생성, {$skipped}건 스킵 (다음 배치 진행 중)"
            : "TTS 일괄 재생성 완료: 총 {$generated}건 생성, {$skipped}건 스킵.");
    }

    private function getSetting(string $key): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string) $row['value'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getSupabaseService(string $projectRoot): ?\Agents\Services\SupabaseService
    {
        $path = $projectRoot . '/src/agents/services/SupabaseService.php';
        if (!file_exists($path)) {
            return null;
        }
        require_once $path;
        $cfg = file_exists($projectRoot . '/config/supabase.php') ? require $projectRoot . '/config/supabase.php' : [];
        return new \Agents\Services\SupabaseService($cfg);
    }

    /** URL 슬러그에서 영어 제목 추출 */
    private function extractTitleFromUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://' . $trimmed;
        }
        $parsed = parse_url($trimmed);
        if ($parsed === false || !isset($parsed['path'])) {
            return null;
        }
        $segments = array_filter(explode('/', trim($parsed['path'], '/')));
        if (empty($segments)) {
            return null;
        }
        $slug = preg_replace('/\.(html?|php|aspx?)$/i', '', end($segments));
        if ($slug === '') {
            return null;
        }
        $words = array_filter(explode('-', $slug));
        if (empty($words)) {
            return null;
        }
        $result = [];
        foreach ($words as $w) {
            $result[] = ucfirst(strtolower($w));
        }
        return implode(' ', $result) ?: null;
    }

    /**
     * playArticle / _buildListenParts(generateTtsForNews.php) 와 동일 구조
     * (title, meta, narration, critiquePart) — Listen 캐시 해시 호환 필수
     */
    private function buildListenStructured(array $row): ?array
    {
        $speakTitle = trim($row['title'] ?? '');
        if ($speakTitle === '') {
            $speakTitle = '제목 없음';
        }

        $sourceUrl = trim($row['source_url'] ?? $row['url'] ?? '');
        $originalTitleMeta = trim($row['original_title'] ?? '');
        if ($originalTitleMeta === '' && $sourceUrl !== '') {
            $originalTitleMeta = $this->extractTitleFromUrl($sourceUrl) ?? '';
        }
        if ($originalTitleMeta === '') {
            $originalTitleMeta = '원문';
        }

        $rawSource = trim($row['original_source'] ?? '');
        if ($rawSource === '') {
            $rawSource = (($row['source'] ?? '') === 'Admin')
                ? 'the gist.'
                : trim((string) ($row['source'] ?? ''));
            if ($rawSource === '') {
                $rawSource = 'the gist.';
            }
        }
        $sourceDisplay = $this->formatSourceDisplayName($rawSource);
        if ($sourceDisplay === '') {
            $sourceDisplay = 'the gist.';
        }

        $meta = sprintf(
            '이 글은 %s에 게재된 %s 글의 시각을 참고하였습니다.',
            $sourceDisplay,
            $originalTitleMeta
        );

        $narrationRaw = trim($row['narration'] ?? '') ?: trim($row['content'] ?? '') ?: trim($row['description'] ?? '');
        $narration = $this->stripHtmlForTts($narrationRaw);
        $critiquePart = $this->stripHtmlForTts(trim($row['why_important'] ?? ''));

        if ($narration === '' && $critiquePart === '') {
            return null;
        }
        return [$speakTitle, $meta, $narration, $critiquePart];
    }

    /** formatSourceDisplayName 과 동일 (맨 뒤 " Magazine" 제거) */
    private function formatSourceDisplayName(string $source): string
    {
        $trimmed = trim($source);
        if ($trimmed === '') {
            return '';
        }
        $len = mb_strlen($trimmed, 'UTF-8');
        $suffix = ' magazine';
        $suffixLen = mb_strlen($suffix, 'UTF-8');
        if ($len >= $suffixLen) {
            $tail = mb_strtolower(mb_substr($trimmed, $len - $suffixLen, null, 'UTF-8'), 'UTF-8');
            if ($tail === $suffix) {
                return trim(mb_substr($trimmed, 0, $len - $suffixLen, 'UTF-8'));
            }
        }
        return $trimmed;
    }

    /** HTML 태그·엔티티 제거 → TTS용 평문 (_stripHtmlForTts 과 동일) */
    private function stripHtmlForTts(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $s = (string) $text;
        $s = html_entity_decode($s, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
        $smartDouble = ["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2033}", "\u{2036}", "\u{00AB}", "\u{00BB}"];
        $smartSingle = ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{2032}", "\u{2035}"];
        $s = str_replace($smartDouble, '"', $s);
        $s = str_replace($smartSingle, "'", $s);
        $s = preg_replace('/<br\s*\/?>/iu', ' ', $s);
        $s = preg_replace('/<\/(p|div|li|h[1-6])>/iu', ' ', $s);
        $s = strip_tags($s);
        $s = str_ireplace('&nbsp;', ' ', $s);
        $s = preg_replace('/\s{2,}/u', ' ', $s);
        return trim($s);
    }

    /**
     * 구독 취소 요청 목록
     *
     * GET /api/admin/cancel-requests
     */
    public function cancelRequests(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min((int) $request->query('per_page', 50), 100);
        $offset = ($page - 1) * $perPage;
        $filter = $request->query('status', 'all');

        try {
            $this->ensureCancelRequestsTable();

            $where = '1=1';
            if ($filter === 'pending') {
                $where = "cr.status = 'pending'";
            } elseif ($filter === 'done') {
                $where = "cr.status = 'done'";
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM cancel_requests cr WHERE $where");
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();

            $pendingStmt = $this->db->query("SELECT COUNT(*) FROM cancel_requests WHERE status = 'pending'");
            $pendingCount = (int) $pendingStmt->fetchColumn();

            $stmt = $this->db->prepare("
                SELECT cr.id, cr.user_id, cr.contact, cr.message, cr.prepared_at, cr.status, cr.created_at, cr.processed_at,
                       u.nickname, u.email, u.is_subscribed, u.subscription_expires_at
                FROM cancel_requests cr
                LEFT JOIN users u ON cr.user_id = u.id
                WHERE $where
                ORDER BY FIELD(cr.status, 'pending', 'done'), cr.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$perPage, $offset]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Response::success([
                'items' => $items,
                'pending_count' => $pendingCount,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
                ],
            ], '취소 요청 목록 조회 성공');
        } catch (RuntimeException $e) {
            return Response::error('취소 요청 조회 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 구독 취소 요청 — 환불 준비(취소 처리) 확정
     *
     * PUT /api/admin/cancel-requests/{id}/prepare
     */
    public function cancelRequestPrepare(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }

        $reqId = (int) $request->param('id');
        if ($reqId <= 0) {
            return Response::error('유효하지 않은 요청 ID입니다.', 400);
        }

        try {
            $this->ensureCancelRequestsTable();

            $stmt = $this->db->prepare(
                "UPDATE cancel_requests SET prepared_at = NOW() WHERE id = ? AND status = 'pending' AND prepared_at IS NULL"
            );
            $stmt->execute([$reqId]);

            if ($stmt->rowCount() === 0) {
                return Response::error('대기 중인 요청만 처리할 수 있거나 이미 취소 처리된 건입니다.', 400);
            }

            return Response::success(null, '환불 준비(취소 처리)로 표시했습니다.');
        } catch (RuntimeException $e) {
            return Response::error('처리 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 구독 취소 요청 — 환불 준비(취소 처리) 취소
     *
     * PUT /api/admin/cancel-requests/{id}/unprepare
     */
    public function cancelRequestUnprepare(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }

        $reqId = (int) $request->param('id');
        if ($reqId <= 0) {
            return Response::error('유효하지 않은 요청 ID입니다.', 400);
        }

        try {
            $this->ensureCancelRequestsTable();

            $stmt = $this->db->prepare(
                "UPDATE cancel_requests SET prepared_at = NULL WHERE id = ? AND status = 'pending' AND prepared_at IS NOT NULL"
            );
            $stmt->execute([$reqId]);

            if ($stmt->rowCount() === 0) {
                return Response::error('취소 처리를 되돌릴 수 있는 상태가 아닙니다.', 400);
            }

            return Response::success(null, '취소 처리를 취소했습니다.');
        } catch (RuntimeException $e) {
            return Response::error('처리 실패: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 구독 취소 요청 처리 완료 마킹 (환불 준비 후에만 가능)
     *
     * PUT /api/admin/cancel-requests/{id}/done
     */
    public function cancelRequestDone(Request $request): Response
    {
        $adminId = $this->checkAdminAuth($request);
        if (!$adminId) {
            return Response::unauthorized('관리자 권한이 필요합니다.');
        }

        $reqId = (int) $request->param('id');
        if ($reqId <= 0) {
            return Response::error('유효하지 않은 요청 ID입니다.', 400);
        }

        try {
            $this->ensureCancelRequestsTable();

            $stmt = $this->db->prepare(
                "UPDATE cancel_requests SET status = 'done', processed_at = NOW() WHERE id = ? AND status = 'pending' AND prepared_at IS NOT NULL"
            );
            $stmt->execute([$reqId]);

            if ($stmt->rowCount() === 0) {
                return Response::error('먼저 「취소 처리(환불 준비)」를 진행한 뒤 완결할 수 있습니다.', 400);
            }

            return Response::success(null, '처리 완료되었습니다.');
        } catch (RuntimeException $e) {
            return Response::error('처리 실패: ' . $e->getMessage(), 500);
        }
    }

    private function ensureCancelRequestsTable(): void
    {
        try {
            $this->db->query("SELECT 1 FROM cancel_requests LIMIT 1");
        } catch (\Throwable $e) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `cancel_requests` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT UNSIGNED NULL,
                    `contact` VARCHAR(255) NOT NULL,
                    `message` TEXT NULL,
                    `prepared_at` TIMESTAMP NULL DEFAULT NULL,
                    `status` ENUM('pending','done') NOT NULL DEFAULT 'pending',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `processed_at` TIMESTAMP NULL,
                    INDEX `idx_cancel_status` (`status`),
                    INDEX `idx_cancel_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        $this->ensureCancelRequestsPreparedAtColumn();
    }

    /**
     * 기존 DB에 prepared_at 컬럼 보강
     */
    private function ensureCancelRequestsPreparedAtColumn(): void
    {
        try {
            $check = $this->db->query("SHOW COLUMNS FROM cancel_requests LIKE 'prepared_at'");
            if ($check !== false && $check->rowCount() > 0) {
                return;
            }
            $this->db->exec(
                "ALTER TABLE cancel_requests ADD COLUMN prepared_at TIMESTAMP NULL DEFAULT NULL COMMENT '환불 준비(취소 처리) 확정 시각' AFTER message"
            );
        } catch (\Throwable) {
            // 테이블 없음 등은 상위 ensure에서 처리
        }
    }

    /**
     * NYT API 상태 확인
     */
    private function checkNytApi(): bool
    {
        $config = require __DIR__ . '/../../../config/nyt.php';
        return $config['api_key'] !== 'YOUR_NYT_API_KEY_HERE';
    }

    /**
     * Kakao API 상태 확인
     */
    private function checkKakaoApi(): bool
    {
        $config = require __DIR__ . '/../../../config/kakao.php';
        return !empty($config['client_id']) && $config['client_id'] !== 'YOUR_KAKAO_REST_API_KEY';
    }

    /**
     * 시간 차이를 한글로 표시
     */
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return '방금 전';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '분 전';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '시간 전';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . '일 전';
        } else {
            return date('Y-m-d', $time);
        }
    }
}
