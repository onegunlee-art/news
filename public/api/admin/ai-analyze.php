<?php
/**
 * AI 분석 API 엔드포인트
 * 
 * Admin 전용 - 기사 URL을 분석하여 요약, 번역, 분석 결과 반환
 * 
 * @package API
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

// 에러 출력 억제 (JSON 응답만 반환)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 전역 에러 핸들러
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
    exit();
});

// CORS 설정
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'Agents\\';
    $baseDir = dirname(__DIR__, 3) . '/src/agents/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // 디렉토리 매핑
    $mappings = [
        'Core' => 'core',
        'Models' => 'models',
        'Services' => 'services',
        'Agents' => 'agents',
        'Pipeline' => 'pipeline',
        'Tests' => 'tests'
    ];
    
    foreach ($mappings as $namespace => $dir) {
        if (strpos($relativeClass, $namespace . '\\') === 0) {
            $subClass = substr($relativeClass, strlen($namespace) + 1);
            $file = $baseDir . $dir . '/' . str_replace('\\', '/', $subClass) . '.php';
            break;
        }
    }
    
    if (file_exists($file)) {
        require $file;
    }
});

use Agents\Pipeline\AgentPipeline;
use Agents\Agents\LearningAgent;
use Agents\Services\OpenAIService;

// 응답 헬퍼
function sendResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError(string $message, int $status = 400): void {
    sendResponse(['success' => false, 'error' => $message], $status);
}

// 요청 처리
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 시스템 상태 확인
            $openai = new OpenAIService();
            sendResponse([
                'success' => true,
                'status' => 'ready',
                'mock_mode' => $openai->isMockMode(),
                'message' => $openai->isMockMode() 
                    ? 'Mock 모드 - API 키 없이 테스트 응답 사용' 
                    : 'API 연동 모드'
            ]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendError('Invalid JSON input');
            }

            $action = $input['action'] ?? 'analyze';

            switch ($action) {
                case 'analyze':
                    handleAnalyze($input);
                    break;

                case 'learn':
                    handleLearn($input);
                    break;

                case 'status':
                    handleStatus();
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
}

/**
 * URL 분석 처리
 */
function handleAnalyze(array $input): void {
    $url = $input['url'] ?? '';
    
    if (empty($url)) {
        sendError('URL is required');
    }

    // 파이프라인 설정
    $config = [
        'openai' => ['mock_mode' => true], // Mock 모드
        'enable_interpret' => $input['enable_interpret'] ?? true,
        'enable_learning' => $input['enable_learning'] ?? false,
        'analysis' => [
            'enable_tts' => $input['enable_tts'] ?? false,
            'summary_length' => $input['summary_length'] ?? 3
        ],
        'stop_on_failure' => true
    ];

    $pipeline = new AgentPipeline($config);
    $pipeline->setupDefaultPipeline();

    // 실행
    $result = $pipeline->run($url);

    // 응답
    sendResponse([
        'success' => $result->isSuccess(),
        'url' => $url,
        'mock_mode' => $pipeline->isMockMode(),
        'needs_clarification' => $result->needsClarification(),
        'clarification_data' => $result->clarificationData,
        'analysis' => $result->getFinalAnalysis(),
        'duration_ms' => round($result->duration * 1000, 2),
        'agents_executed' => array_keys($result->results),
        'error' => $result->getError()
    ]);
}

/**
 * 학습 처리
 */
function handleLearn(array $input): void {
    $texts = $input['texts'] ?? [];
    
    if (empty($texts)) {
        sendError('Learning texts are required');
    }

    $openai = new OpenAIService(['mock_mode' => true]);
    $learningAgent = new LearningAgent($openai, [
        'storage_path' => dirname(__DIR__, 3) . '/storage/learning'
    ]);
    $learningAgent->initialize();

    // 샘플 텍스트 추가
    foreach ($texts as $text) {
        if (is_string($text) && !empty(trim($text))) {
            $learningAgent->addSampleText($text);
        }
    }

    // 학습 실행
    $patterns = $learningAgent->learn();

    sendResponse([
        'success' => true,
        'message' => '학습이 완료되었습니다.',
        'sample_count' => count($texts),
        'patterns' => $patterns
    ]);
}

/**
 * 상태 확인
 */
function handleStatus(): void {
    $openai = new OpenAIService();
    $learningAgent = new LearningAgent($openai, [
        'storage_path' => dirname(__DIR__, 3) . '/storage/learning'
    ]);
    $learningAgent->initialize();

    sendResponse([
        'success' => true,
        'mock_mode' => $openai->isMockMode(),
        'has_learned_patterns' => $learningAgent->hasLearnedPatterns(),
        'patterns' => $learningAgent->getLearnedPatterns()
    ]);
}
