<?php
/**
 * GET/POST /api/admin/promotion-codes
 * 구독 결제 프로모션 코드 CRUD (관리자 전용)
 *
 * GET — 목록
 * POST body:
 *   { "action": "create", "code", "description", "discount_percent", "plan_price_map", "max_uses"?, "starts_at"?, "expires_at"?, "is_active"? }
 *   { "action": "update", "id", ...필드 선택 }
 *   { "action": "set_active", "id", "is_active": 0|1 }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/steppay.php';
require_once __DIR__ . '/../lib/log.php';

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$jwt = decodeJwt($token);
if (!$jwt || empty($jwt['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 토큰입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDb();
$adminStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$adminStmt->execute([(int) $jwt['user_id']]);
$adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
if (!$adminRow || ($adminRow['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '관리자만 접근할 수 있습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function promotionValidateMap(array $map, array $allowedPlanIds): ?string {
    if (empty($map)) {
        return 'plan_price_map이 비어 있습니다.';
    }
    foreach ($map as $planId => $entry) {
        if (!in_array($planId, $allowedPlanIds, true)) {
            return "허용되지 않는 플랜 키: {$planId}";
        }
        if (!is_array($entry)) {
            return "플랜 {$planId} 항목 형식이 올바르지 않습니다.";
        }
        $pc = trim((string) ($entry['price_code'] ?? ''));
        $am = isset($entry['amount']) ? (int) $entry['amount'] : 0;
        if ($pc === '' || $am <= 0) {
            return "플랜 {$planId}: price_code와 amount(원)가 필요합니다.";
        }
    }
    return null;
}

$cfg = getSteppayConfig();
$allowedPlans = array_keys($cfg['plans'] ?? []);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query(
            'SELECT id, code, description, discount_percent, plan_price_map, max_uses, used_count, starts_at, expires_at, is_active, created_at, updated_at
             FROM promotion_codes ORDER BY id DESC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            if (isset($r['plan_price_map']) && is_string($r['plan_price_map'])) {
                $r['plan_price_map'] = json_decode($r['plan_price_map'], true) ?: [];
            }
        }
        unset($r);
        echo json_encode(['success' => true, 'data' => ['items' => $rows]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? 'create';

    if ($action === 'set_active') {
        $id = (int) ($input['id'] ?? 0);
        $active = (int) ($input['is_active'] ?? 0) ? 1 : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id가 필요합니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $pdo->prepare('UPDATE promotion_codes SET is_active = ? WHERE id = ?')->execute([$active, $id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id가 필요합니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare('SELECT * FROM promotion_codes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '코드를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $code = isset($input['code']) ? strtoupper(trim((string) $input['code'])) : $existing['code'];
        if ($code === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'code가 비어 있습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($code !== $existing['code']) {
            $chk = $pdo->prepare('SELECT id FROM promotion_codes WHERE UPPER(code) = ? AND id != ? LIMIT 1');
            $chk->execute([$code, $id]);
            if ($chk->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => '이미 같은 코드가 있습니다.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $description = $input['description'] ?? $existing['description'];
        $discountPercent = isset($input['discount_percent']) ? (int) $input['discount_percent'] : (int) $existing['discount_percent'];
        $mapInput = $input['plan_price_map'] ?? json_decode($existing['plan_price_map'], true);
        if (is_string($mapInput)) {
            $mapInput = json_decode($mapInput, true) ?: [];
        }
        if (!is_array($mapInput)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'plan_price_map 형식 오류'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $mapErr = promotionValidateMap($mapInput, $allowedPlans);
        if ($mapErr !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $mapErr], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $maxUses = array_key_exists('max_uses', $input)
            ? ($input['max_uses'] === null || $input['max_uses'] === '' ? null : (int) $input['max_uses'])
            : ($existing['max_uses'] !== null ? (int) $existing['max_uses'] : null);
        $startsAt = array_key_exists('starts_at', $input) ? ($input['starts_at'] ?: null) : $existing['starts_at'];
        $expiresAt = array_key_exists('expires_at', $input) ? ($input['expires_at'] ?: null) : $existing['expires_at'];
        $isActive = isset($input['is_active']) ? ((int) $input['is_active'] ? 1 : 0) : (int) $existing['is_active'];

        $mapJson = json_encode($mapInput, JSON_UNESCAPED_UNICODE);

        $pdo->prepare(
            'UPDATE promotion_codes SET code = ?, description = ?, discount_percent = ?, plan_price_map = ?, max_uses = ?, starts_at = ?, expires_at = ?, is_active = ? WHERE id = ?'
        )->execute([
            $code,
            $description,
            $discountPercent,
            $mapJson,
            $maxUses,
            $startsAt,
            $expiresAt,
            $isActive,
            $id,
        ]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action !== 'create') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '알 수 없는 action입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    if ($code === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'code가 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $chk = $pdo->prepare('SELECT id FROM promotion_codes WHERE UPPER(code) = ? LIMIT 1');
    $chk->execute([$code]);
    if ($chk->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '이미 같은 코드가 있습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mapInput = $input['plan_price_map'] ?? [];
    if (is_string($mapInput)) {
        $mapInput = json_decode($mapInput, true) ?: [];
    }
    if (!is_array($mapInput)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'plan_price_map이 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $mapErr = promotionValidateMap($mapInput, $allowedPlans);
    if ($mapErr !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $mapErr], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $description = $input['description'] ?? null;
    $discountPercent = (int) ($input['discount_percent'] ?? 0);
    $maxUses = isset($input['max_uses']) && $input['max_uses'] !== '' && $input['max_uses'] !== null
        ? (int) $input['max_uses'] : null;
    $startsAt = !empty($input['starts_at']) ? $input['starts_at'] : null;
    $expiresAt = !empty($input['expires_at']) ? $input['expires_at'] : null;
    $isActive = isset($input['is_active']) ? ((int) $input['is_active'] ? 1 : 0) : 1;

    $mapJson = json_encode($mapInput, JSON_UNESCAPED_UNICODE);

    $pdo->prepare(
        'INSERT INTO promotion_codes (code, description, discount_percent, plan_price_map, max_uses, starts_at, expires_at, is_active)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $code,
        $description,
        $discountPercent,
        $mapJson,
        $maxUses,
        $startsAt,
        $expiresAt,
        $isActive,
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int) $pdo->lastInsertId()]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    payment_log('admin/promotion-codes error', ['msg' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
}
