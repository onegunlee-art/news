<?php
/**
 * GET /api/subscription/plans
 *
 * 구독 플랜 목록을 공개로 반환한다 (인증 불필요).
 * 단일 진실의 원천: config/app.php 의 steppay.plans.
 *
 * 응답 형식 (frontend SubscriptionPage 의 PLANS 상수와 동일 스키마):
 * {
 *   success: true,
 *   data: {
 *     plans: [
 *       { id, label, monthlyPrice, totalPrice?, discount?, billing, renewal, bestValue, amount, months }
 *     ],
 *     onetime_products: [...],
 *     currency: 'KRW'
 *   }
 * }
 *
 * 응답에 5분 브라우저 캐시 + stale-while-revalidate 600 적용.
 * (가격이 자주 바뀌지 않으므로 캐싱으로 결제 페이지 진입 속도 보호)
 */

require_once __DIR__ . '/../lib/cors.php';
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../lib/steppay.php';

try {
    $cfg = getSteppayConfig();
    $plans = $cfg['plans'] ?? [];
    $onetime = $cfg['onetime_products'] ?? [];

    if (!$plans) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => '플랜 설정을 불러올 수 없습니다.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1m amount 를 기준 월간 가격으로 잡고, 다른 플랜의 월 환산가 대비 할인율 계산
    $baseMonthlyAmount = isset($plans['1m']['amount']) ? (int) $plans['1m']['amount'] : 11000;

    // 표시 순서: 12m → 6m → 3m → 1m (frontend 기존 순서 유지)
    $displayOrder = ['12m', '6m', '3m', '1m'];

    $bestValueId = '12m';

    $planList = [];
    foreach ($displayOrder as $id) {
        if (!isset($plans[$id])) continue;
        $p = $plans[$id];
        $months = max(1, (int) ($p['months'] ?? 1));
        $amount = (int) ($p['amount'] ?? 0);
        $monthly = (int) round($amount / $months);

        // 할인율: (기준 월간가 - 환산 월가) / 기준 월간가
        $discountText = null;
        if ($id !== '1m' && $baseMonthlyAmount > 0) {
            $discountPct = (int) round(($baseMonthlyAmount - $monthly) * 100 / $baseMonthlyAmount);
            if ($discountPct > 0) {
                $discountText = sprintf('월간 구독 대비 %d%% 할인', $discountPct);
            }
        }

        $label = ($id === '12m') ? '연간 구독'
            : (($id === '1m') ? '1개월 구독' : sprintf('%d개월 구독', $months));

        $planList[] = [
            'id' => $id,
            'label' => $label,
            'monthlyPrice' => number_format($monthly),
            'totalPrice' => ($id === '1m') ? null : number_format($amount),
            'discount' => $discountText,
            'billing' => '',
            'renewal' => '',
            'bestValue' => ($id === $bestValueId),
            // 정수 원본 (참고용, frontend 표시 비교/계산용)
            'amount' => $amount,
            'months' => $months,
        ];
    }

    // 단건 상품 (newsletter 등) — 현재 표시는 안 하지만 추후 활용
    $onetimeList = [];
    foreach ($onetime as $oid => $o) {
        $onetimeList[] = [
            'id' => $oid,
            'label' => $o['label'] ?? $oid,
            'description' => $o['description'] ?? '',
            'amount' => (int) ($o['amount'] ?? 0),
            'amountFormatted' => number_format((int) ($o['amount'] ?? 0)),
        ];
    }

    // 5분 브라우저 캐시 + 10분 stale-while-revalidate
    header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

    echo json_encode([
        'success' => true,
        'data' => [
            'plans' => $planList,
            'onetime_products' => $onetimeList,
            'currency' => 'KRW',
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '플랜을 불러오지 못했습니다.',
    ], JSON_UNESCAPED_UNICODE);
}
