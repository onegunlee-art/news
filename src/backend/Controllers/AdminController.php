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
        $this->db = Database::getInstance();
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
        // 관리자 권한 확인 (개발 중에는 생략 가능)
        // $adminId = $this->checkAdminAuth($request);
        // if (!$adminId) {
        //     return Response::unauthorized('관리자 권한이 필요합니다.');
        // }
        
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
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min((int) $request->query('per_page', 20), 100);
        $offset = ($page - 1) * $perPage;
        
        try {
            // 전체 수
            $stmt = $this->db->query("SELECT COUNT(*) FROM users");
            $total = (int) $stmt->fetchColumn();
            
            // 사용자 목록
            $stmt = $this->db->prepare("
                SELECT id, email, nickname, profile_image, role, status, 
                       last_login_at, created_at
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$perPage, $offset]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'items' => $users,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                ],
            ], '사용자 목록 조회 성공');
            
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
     * 최근 활동 조회
     * 
     * GET /api/admin/activities
     */
    public function activities(Request $request): Response
    {
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
            
            // 최근 분석
            $stmt = $this->db->prepare("
                SELECT 'analysis' as type, title as message, created_at as time 
                FROM analyses 
                ORDER BY created_at DESC 
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
