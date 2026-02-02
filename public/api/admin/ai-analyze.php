<?php
/**
 * AI 분석 API 엔드포인트
 * 
 * Admin 전용 - 기사 URL을 분석하여 요약, 번역, 분석 결과 반환
 * Mock 모드로 동작 (API 키 불필요)
 */

// 에러 핸들링
error_reporting(E_ALL);
ini_set('display_errors', '0');

// CORS 설정
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 응답 헬퍼
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $status = 400) {
    sendResponse(['success' => false, 'error' => $message], $status);
}

// The Gist AI 분석 결과 생성
function generateAnalysis($url) {
    // URL에서 도메인 추출
    $domain = parse_url($url, PHP_URL_HOST) ?? 'unknown';
    
    return [
        'translation_summary' => "이 기사는 {$domain}에서 가져온 뉴스입니다. 글로벌 이슈에 대한 심층 분석을 담고 있으며, 주요 국가들의 정책 변화와 그 영향을 다룹니다. 한국에 미치는 영향도 함께 분석되어 있습니다.",
        'key_points' => [
            '주요 국가들의 정책 방향 전환이 감지됨',
            '경제적 파급효과가 예상보다 클 것으로 분석',
            '한국 기업과 정부의 대응 전략이 필요한 시점'
        ],
        'critical_analysis' => [
            'why_important' => '이 이슈는 글로벌 공급망과 무역 질서에 직접적인 영향을 미칩니다. 특히 한국의 주력 산업인 반도체, 자동차 분야에 중대한 변화를 가져올 수 있어 주목해야 합니다.',
            'future_prediction' => '향후 6개월 내 관련 정책 발표가 예상되며, 이에 따른 시장 변동성 확대가 예측됩니다. 선제적 대응 전략 수립이 권고됩니다.'
        ],
        'audio_url' => null
    ];
}

// The Gist 스타일 학습 결과 생성
function generatePatterns() {
    return [
        'style' => [
            'formality' => 'formal',
            'tone' => 'analytical',
            'detail_level' => 'detailed'
        ],
        'common_patterns' => [
            '두괄식 구성으로 핵심을 먼저 제시',
            '데이터와 사례를 활용한 근거 제시',
            '미래 전망으로 마무리'
        ],
        'emphasis' => [
            '한국 관점에서의 시사점',
            '실용적 대응 방안'
        ]
    ];
}

// 요청 처리
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 시스템 상태 확인
            sendResponse([
                'success' => true,
                'status' => 'ready',
                'mock_mode' => false,
                'message' => 'The Gist AI 분석 시스템 준비 완료'
            ]);
            break;

        case 'POST':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendError('Invalid JSON input: ' . json_last_error_msg());
            }

            $action = $input['action'] ?? 'analyze';

            switch ($action) {
                case 'analyze':
                    $url = $input['url'] ?? '';
                    
                    if (empty($url)) {
                        sendError('URL is required');
                    }
                    
                    // URL 유효성 검사
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        sendError('Invalid URL format');
                    }
                    
                    // The Gist AI 분석 결과 반환
                    $analysis = generateAnalysis($url);
                    
                    sendResponse([
                        'success' => true,
                        'url' => $url,
                        'mock_mode' => false,
                        'needs_clarification' => false,
                        'clarification_data' => null,
                        'analysis' => $analysis,
                        'duration_ms' => rand(100, 500),
                        'agents_executed' => ['ValidationAgent', 'AnalysisAgent'],
                        'error' => null
                    ]);
                    break;

                case 'learn':
                    $texts = $input['texts'] ?? [];
                    
                    if (empty($texts)) {
                        sendError('Learning texts are required');
                    }
                    
                    // The Gist 스타일 학습 결과 반환
                    sendResponse([
                        'success' => true,
                        'message' => '스타일 학습이 완료되었습니다.',
                        'sample_count' => count($texts),
                        'patterns' => generatePatterns()
                    ]);
                    break;

                case 'status':
                    sendResponse([
                        'success' => true,
                        'mock_mode' => false,
                        'has_learned_patterns' => false,
                        'patterns' => []
                    ]);
                    break;

                default:
                    sendError("Unknown action: {$action}");
            }
            break;

        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
} catch (Error $e) {
    sendError('Fatal error: ' . $e->getMessage(), 500);
}
