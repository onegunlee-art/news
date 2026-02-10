<?php
/**
 * AI 분석 API 엔드포인트
 * 
 * Admin 전용 - 기사 URL을 분석하여 요약, 번역, 분석 결과 반환
 * Agent Pipeline (ValidationAgent, AnalysisAgent, InterpretAgent, LearningAgent) 사용
 */

// 에러 핸들링 - JSON만 출력
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 출력 버퍼링 시작 - PHP 경고가 JSON을 오염시키지 않도록
ob_start();

// CORS 설정
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Fatal Error 핸들러 - 어떤 경우에도 JSON 응답 보장
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'],
            'analysis' => null
        ], JSON_UNESCAPED_UNICODE);
    }
});

// TTS 여러 청크 생성 시 60초 제한에 걸리지 않도록 실행 시간 연장
set_time_limit(300);

// ── 프로젝트 루트 자동 탐지 ──
// 로컬: public/api/admin → 3단계 상위 = project root
// 서버(dothome): api/admin → 2단계 상위 = html root
function findProjectRoot(): string {
    $candidates = [
        realpath(__DIR__ . '/../../../'),  // 로컬 개발 (public/api/admin → project/)
        realpath(__DIR__ . '/../../'),      // 서버 배포 (api/admin → html/)
        realpath(__DIR__ . '/../'),         // 안전 fallback
    ];
    foreach ($candidates as $path) {
        if ($path && file_exists($path . '/src/agents/autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    // 최후의 수단: 현재 위치 기준 탐색
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $dir = dirname($dir);
        if (file_exists($dir . '/src/agents/autoload.php')) {
            return rtrim($dir, '/\\') . '/';
        }
    }
    throw new \RuntimeException(
        'Cannot find project root. Searched from: ' . __DIR__ . 
        '. Expected src/agents/autoload.php at project root.'
    );
}

$projectRoot = findProjectRoot();

// .env 로드 (OPENAI_API_KEY, GOOGLE_TTS_API_KEY 등)
$envLoaded = false;
$envFiles = [
    $projectRoot . '.env',
    $projectRoot . '.env.production',
    dirname($projectRoot) . '/.env',  // 한 단계 위도 확인
];
foreach ($envFiles as $envFile) {
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
        $envLoaded = true;
        break;
    }
}

// Agent System 로드
require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Pipeline\AgentPipeline;
use Agents\Agents\LearningAgent;
use Agents\Services\OpenAIService;

// 응답 헬퍼
function sendResponse($data, $status = 200) {
    ob_clean(); // 버퍼된 경고/에러 모두 제거
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $status = 400, $extra = []) {
    sendResponse(array_merge(['success' => false, 'error' => $message, 'analysis' => null], $extra), $status);
}

// URL 분석 실행
function analyzeUrl(string $url, array $options = []): array {
    global $projectRoot, $envLoaded;
    $startTime = microtime(true);

    // 디버그 정보 수집
    $debug = [
        'project_root' => $projectRoot,
        'env_loaded' => $envLoaded,
        'openai_key_set' => !empty(getenv('OPENAI_API_KEY')),
        'openai_key_prefix' => substr(getenv('OPENAI_API_KEY') ?: '', 0, 10) . '...',
        'google_tts_key_set' => !empty(getenv('GOOGLE_TTS_API_KEY')),
    ];

    // Google TTS 설정
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
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['value'] !== '') {
                $ttsVoice = $row['value'];
            }
        } catch (\Throwable $e) {
            // DB 조회 실패 시 config 기본값 유지
            $debug['db_error'] = $e->getMessage();
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
            'key_points_count' => 4,
            'tts_voice' => $ttsVoice,
        ],
        'stop_on_failure' => true
    ];

    $pipeline = new AgentPipeline($pipelineConfig);
    $pipeline->setupDefaultPipeline();

    $isMock = $pipeline->isMockMode();
    $debug['mock_mode'] = $isMock;

    // 실행
    $result = $pipeline->run($url);

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

    // 결과 처리
    if ($result->isSuccess()) {
        $finalAnalysis = $result->getFinalAnalysis();
        $articleData = $result->context?->getArticleData();
        $article = $articleData ? $articleData->toArray() : null;

        // narration fallback: key_points + why_important 조합
        $narration = $finalAnalysis['narration'] ?? null;
        if (empty($narration) && !empty($finalAnalysis['key_points'])) {
            $narration = implode(' ', $finalAnalysis['key_points']);
            if (!empty($finalAnalysis['critical_analysis']['why_important'])) {
                $narration .= ' ' . $finalAnalysis['critical_analysis']['why_important'];
            }
        }

        return [
            'success' => true,
            'url' => $url,
            'mock_mode' => $isMock,
            'needs_clarification' => false,
            'clarification_data' => null,
            'article' => $article,
            'analysis' => [
                'news_title' => $finalAnalysis['news_title'] ?? null,
                'translation_summary' => $finalAnalysis['translation_summary'] ?? ($narration ? mb_substr($narration, 0, 200) : ''),
                'key_points' => $finalAnalysis['key_points'] ?? [],
                'narration' => $narration,
                'critical_analysis' => $finalAnalysis['critical_analysis'] ?? [
                    'why_important' => ''
                ],
                'audio_url' => $finalAnalysis['audio_url'] ?? null
            ],
            'duration_ms' => $durationMs,
            'agents_executed' => array_keys($result->results),
            'debug' => $debug,
            'error' => null
        ];
    }

    // 명확화 필요
    if ($result->needsClarification()) {
        return [
            'success' => false,
            'url' => $url,
            'mock_mode' => $isMock,
            'needs_clarification' => true,
            'clarification_data' => $result->clarificationData,
            'analysis' => null,
            'duration_ms' => $durationMs,
            'agents_executed' => array_keys($result->results),
            'debug' => $debug,
            'error' => null
        ];
    }

    // 실패
    return [
        'success' => false,
        'url' => $url,
        'mock_mode' => $isMock,
        'needs_clarification' => false,
        'clarification_data' => null,
        'analysis' => null,
        'duration_ms' => $durationMs,
        'agents_executed' => array_keys($result->results),
        'debug' => $debug,
        'error' => $result->getError()
    ];
}

// 스타일 학습
function learnStyle(array $texts): array {
    global $projectRoot;
    $openai = new OpenAIService(['mock_mode' => true]);
    $learningAgent = new LearningAgent($openai, [
        'storage_path' => $projectRoot . 'storage/learning'
    ]);
    
    $learningAgent->initialize();
    
    foreach ($texts as $text) {
        if (!empty(trim($text))) {
            $learningAgent->addSampleText($text, ['source' => 'admin']);
        }
    }
    
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
    global $projectRoot;
    $openai = new OpenAIService([]);
    $learningAgent = new LearningAgent($openai, [
        'storage_path' => $projectRoot . 'storage/learning'
    ]);
    
    $learningAgent->initialize();
    
    return [
        'success' => true,
        'mock_mode' => $openai->isMockMode(),
        'openai_configured' => $openai->isConfigured(),
        'has_learned_patterns' => $learningAgent->hasLearnedPatterns(),
        'patterns' => $learningAgent->getLearnedPatterns()
    ];
}

// 요청 처리
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $pipeline = new AgentPipeline([]);
            $pipeline->setupDefaultPipeline();
            
            sendResponse([
                'success' => true,
                'status' => 'ready',
                'mock_mode' => $pipeline->isMockMode(),
                'openai_key_set' => !empty(getenv('OPENAI_API_KEY')),
                'env_loaded' => $envLoaded ?? false,
                'project_root' => $projectRoot ?? 'not set',
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
                    
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        sendError('Invalid URL format');
                    }
                    
                    $options = [
                        'enable_tts' => $input['enable_tts'] ?? false,
                        'enable_interpret' => $input['enable_interpret'] ?? true,
                        'enable_learning' => $input['enable_learning'] ?? true
                    ];
                    
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
} catch (\Exception $e) {
    error_log("AI Analyze Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendError('Server error: ' . $e->getMessage(), 500);
} catch (\Error $e) {
    error_log("AI Analyze Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendError('Fatal error: ' . $e->getMessage(), 500);
}
