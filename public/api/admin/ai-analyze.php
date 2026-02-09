<?php
/**
 * AI 분석 API 엔드포인트
 * 
 * Admin 전용 - 기사 URL을 분석하여 요약, 번역, 분석 결과 반환
 * Agent Pipeline (ValidationAgent, AnalysisAgent, InterpretAgent, LearningAgent) 사용
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

// TTS 여러 청크 생성 시 60초 제한에 걸리지 않도록 실행 시간 연장 (보이스 전체 재생)
set_time_limit(300);

// .env 로드 (GOOGLE_TTS_API_KEY 등)
$projectRoot = __DIR__ . '/../../../';
$envFile = $projectRoot . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

// Agent System 로드
require_once __DIR__ . '/../../../src/agents/autoload.php';

use Agents\Pipeline\AgentPipeline;
use Agents\Agents\LearningAgent;
use Agents\Services\OpenAIService;

// 응답 헬퍼
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $status = 400) {
    sendResponse(['success' => false, 'error' => $message], $status);
}

// URL 분석 실행
function analyzeUrl(string $url, array $options = []): array {
    $startTime = microtime(true);

    $projectRoot = __DIR__ . '/../../../';
    $googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php')
        ? require $projectRoot . 'config/google_tts.php'
        : [];
    $ttsVoice = $googleTtsConfig['default_voice'] ?? 'ko-KR-Standard-A';
    // Admin에서 저장한 보이스 사용 (DB에서 조회)
    if (file_exists($projectRoot . 'src/backend/Core/Database.php')) {
        require_once $projectRoot . 'src/backend/Core/Database.php';
        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
            $stmt->execute(['tts_voice']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['value'] !== '') {
                $ttsVoice = $row['value'];
            }
        } catch (Throwable $e) {
            // DB 조회 실패 시 config 기본값 유지
        }
    }

    $pipelineConfig = [
        'project_root' => rtrim($projectRoot, '/\\'),
        'openai' => [],
        'enable_interpret' => $options['enable_interpret'] ?? true,
        'enable_learning' => $options['enable_learning'] ?? true,
        'google_tts' => $googleTtsConfig,
        'analysis' => [
            'enable_tts' => $options['enable_tts'] ?? false,
            'summary_length' => 3,
            'key_points_count' => 3,
            'tts_voice' => $ttsVoice,
        ],
        'stop_on_failure' => true
    ];

    $pipeline = new AgentPipeline($pipelineConfig);
    $pipeline->setupDefaultPipeline();
    
    // 실행
    $result = $pipeline->run($url);
    
    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
    
    // 결과 처리
    if ($result->isSuccess()) {
        $finalAnalysis = $result->getFinalAnalysis();
        $articleData = $result->context?->getArticleData();
        $article = $articleData ? $articleData->toArray() : null;

        return [
            'success' => true,
            'url' => $url,
            'mock_mode' => $pipeline->isMockMode(),
            'needs_clarification' => false,
            'clarification_data' => null,
            'article' => $article,
            'analysis' => [
                'news_title' => $finalAnalysis['news_title'] ?? null,
                'translation_summary' => $finalAnalysis['translation_summary'] ?? ($finalAnalysis['narration'] ? mb_substr($finalAnalysis['narration'], 0, 200) : ''),
                'key_points' => $finalAnalysis['key_points'] ?? [],
                'narration' => $finalAnalysis['narration'] ?? null,
                'critical_analysis' => $finalAnalysis['critical_analysis'] ?? [
                    'why_important' => ''
                ],
                'audio_url' => $finalAnalysis['audio_url'] ?? null
            ],
            'duration_ms' => $durationMs,
            'agents_executed' => array_keys($result->results),
            'error' => null
        ];
    }
    
    // 명확화 필요
    if ($result->needsClarification()) {
        return [
            'success' => false,
            'url' => $url,
            'mock_mode' => $pipeline->isMockMode(),
            'needs_clarification' => true,
            'clarification_data' => $result->clarificationData,
            'analysis' => null,
            'duration_ms' => $durationMs,
            'agents_executed' => array_keys($result->results),
            'error' => null
        ];
    }
    
    // 실패
    return [
        'success' => false,
        'url' => $url,
        'mock_mode' => $pipeline->isMockMode(),
        'needs_clarification' => false,
        'clarification_data' => null,
        'analysis' => null,
        'duration_ms' => $durationMs,
        'agents_executed' => array_keys($result->results),
        'error' => $result->getError()
    ];
}

// 스타일 학습
function learnStyle(array $texts): array {
    $openai = new OpenAIService(['mock_mode' => true]);
    $learningAgent = new LearningAgent($openai, [
        'storage_path' => __DIR__ . '/../../../storage/learning'
    ]);
    
    $learningAgent->initialize();
    
    // 샘플 텍스트 추가
    foreach ($texts as $text) {
        if (!empty(trim($text))) {
            $learningAgent->addSampleText($text, ['source' => 'admin']);
        }
    }
    
    // 학습 실행
    $patterns = $learningAgent->learn();
    
    return [
        'success' => true,
        'message' => '스타일 학습이 완료되었습니다.',
        'sample_count' => count($texts),
        'patterns' => $patterns
    ];
}

// 학습 상태 확인
function getStatus(): array {
    $openai = new OpenAIService([]);
    $learningAgent = new LearningAgent($openai, [
        'storage_path' => __DIR__ . '/../../../storage/learning'
    ]);
    
    $learningAgent->initialize();
    
    return [
        'success' => true,
        'mock_mode' => $openai->isMockMode(),
        'has_learned_patterns' => $learningAgent->hasLearnedPatterns(),
        'patterns' => $learningAgent->getLearnedPatterns()
    ];
}

// 요청 처리
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // 시스템 상태 확인
            $pipeline = new AgentPipeline([]);
            $pipeline->setupDefaultPipeline();
            
            sendResponse([
                'success' => true,
                'status' => 'ready',
                'mock_mode' => $pipeline->isMockMode(),
                'agents' => $pipeline->getAgentNames(),
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
                    
                    // 옵션
                    $options = [
                        'enable_tts' => $input['enable_tts'] ?? false,
                        'enable_interpret' => $input['enable_interpret'] ?? true,
                        'enable_learning' => $input['enable_learning'] ?? true
                    ];
                    
                    // Agent Pipeline 분석 실행
                    $result = analyzeUrl($url, $options);
                    sendResponse($result);
                    break;

                case 'learn':
                    $texts = $input['texts'] ?? [];
                    
                    if (empty($texts)) {
                        sendError('Learning texts are required');
                    }
                    
                    $result = learnStyle($texts);
                    sendResponse($result);
                    break;

                case 'status':
                    $result = getStatus();
                    sendResponse($result);
                    break;

                default:
                    sendError("Unknown action: {$action}");
            }
            break;

        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("AI Analyze Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendError('Server error: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("AI Analyze Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendError('Fatal error: ' . $e->getMessage(), 500);
}
