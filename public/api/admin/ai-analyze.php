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
function findProjectRoot(): string {
    $rawCandidates = [
        __DIR__ . '/../../../',  // 로컬 (public/api/admin)
        __DIR__ . '/../../',     // 서버 (api/admin)
        __DIR__ . '/../',
    ];
    foreach ($rawCandidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $dir = dirname($dir);
        if (file_exists($dir . '/src/agents/autoload.php')) {
            return rtrim($dir, '/\\') . '/';
        }
    }
    throw new \RuntimeException('Project root not found. __DIR__=' . __DIR__);
}

// .env / env.txt 로드 (서버는 env.txt 사용 권장 - FTP dotfile 미업로드 대비)
function loadEnvFile(string $path): bool {
    if (!is_file($path) || !is_readable($path)) return false;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
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
    return true;
}

$projectRoot = null;
$envLoaded = false;
$envTried = [];

try {
    $projectRoot = findProjectRoot();
} catch (\Throwable $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Project root: ' . $e->getMessage(),
        'debug' => ['__dir__' => __DIR__, 'tried' => $envTried],
        'analysis' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$envTried = [];
$envFiles = [
    $projectRoot . 'env.txt',       // 배포 시 생성 (dotfile 업로드 실패 대비)
    $projectRoot . '.env',
    $projectRoot . '.env.production',
    dirname($projectRoot) . '/.env',
];
foreach ($envFiles as $f) {
    $envTried[] = $f;
    if (loadEnvFile($f)) {
        $envLoaded = true;
        break;
    }
}

// Agent System 로드
require_once $projectRoot . 'src/agents/autoload.php';

use Agents\Pipeline\AgentPipeline;
use Agents\Agents\LearningAgent;
use Agents\Services\OpenAIService;
use Agents\Services\GoogleTTSService;

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
    global $projectRoot, $envLoaded, $envTried;
    $startTime = microtime(true);

    // 디버그 정보 수집 ($_ENV 우선 - putenv 미반영 서버 대응)
    $openaiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
    $openaiKey = is_string($openaiKey) ? $openaiKey : '';
    $debug = [
        'project_root' => $projectRoot,
        'env_loaded' => $envLoaded,
        'env_tried' => $envTried ?? [],
        'openai_key_set' => $openaiKey !== '',
        'openai_key_prefix' => $openaiKey !== '' ? substr($openaiKey, 0, 10) . '...' : '(empty)',
        'google_tts_key_set' => !empty($_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY')),
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

    // 실행 (예외 시에도 JSON 반환)
    try {
        $result = $pipeline->run($url);
    } catch (\Throwable $e) {
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        return [
            'success' => false,
            'url' => $url,
            'mock_mode' => $isMock,
            'needs_clarification' => false,
            'clarification_data' => null,
            'article' => null,
            'analysis' => null,
            'duration_ms' => $durationMs,
            'agents_executed' => array_keys($pipeline->getResults()),
            'phase' => 'pipeline_run',
            'failed_step' => 'exception',
            'debug' => $debug,
            'error' => 'Pipeline 예외: ' . $e->getMessage() . ' (파일: ' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        ];
    }

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
    $agentsExecuted = array_keys($result->results);
    $failedStep = $result->isSuccess() ? null : (end($agentsExecuted) ?: null);

    // 결과 처리
    if ($result->isSuccess()) {
        $finalAnalysis = $result->getFinalAnalysis();
        $articleData = $result->context?->getArticleData();
        $article = $articleData ? $articleData->toArray() : null;

        // narration fallback: key_points만 사용 (Critique 미사용)
        $narration = $finalAnalysis['narration'] ?? null;
        if (empty($narration) && !empty($finalAnalysis['key_points'])) {
            $narration = implode(' ', $finalAnalysis['key_points']);
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
                'content_summary' => $finalAnalysis['content_summary'] ?? null,
                'narration' => $narration,
                'critical_analysis' => $finalAnalysis['critical_analysis'] ?? [],
                'audio_url' => $finalAnalysis['audio_url'] ?? null
            ],
            'duration_ms' => $durationMs,
            'agents_executed' => $agentsExecuted,
            'phase' => 'success',
            'failed_step' => null,
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
            'article' => null,
            'analysis' => null,
            'duration_ms' => $durationMs,
            'agents_executed' => $agentsExecuted,
            'phase' => 'clarification',
            'failed_step' => null,
            'debug' => $debug,
            'error' => null
        ];
    }

    // 실패 (ValidationAgent/ThumbnailAgent/AnalysisAgent 등에서 실패)
    return [
        'success' => false,
        'url' => $url,
        'mock_mode' => $isMock,
        'needs_clarification' => false,
        'clarification_data' => null,
        'article' => null,
        'analysis' => null,
        'duration_ms' => $durationMs,
        'agents_executed' => $agentsExecuted,
        'phase' => 'agent_failure',
        'failed_step' => $failedStep,
        'debug' => $debug,
        'error' => $result->getError()
    ];
}

/**
 * TTS 생성 (2단계: 분석 완료 후 호출)
 * 순서: 제목 → 0.5초 휴식 → 출처·작성자 → 0.5초 휴식 → 내레이션
 * 입력: narration (필수), tts_voice (선택), news_title, source, author (선택, 있으면 구조화 재생)
 * 출력: { success, audio_url } 또는 { success: false, error }
 */
function generateTtsFromNarration(string $narration, ?string $ttsVoice = null, ?string $newsTitle = null, ?string $source = null, ?string $author = null): array {
    global $projectRoot;

    $narration = trim($narration);
    if ($narration === '') {
        return ['success' => false, 'error' => 'narration is required'];
    }

    $googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php')
        ? require $projectRoot . 'config/google_tts.php'
        : [];
    $voice = $googleTtsConfig['default_voice'] ?? 'ko-KR-Standard-A';
    if ($ttsVoice !== null && $ttsVoice !== '') {
        $voice = $ttsVoice;
    } elseif (file_exists($projectRoot . 'src/backend/Core/Database.php')) {
        require_once $projectRoot . 'src/backend/Core/Database.php';
        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
            $stmt->execute(['tts_voice']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['value'] !== '') {
                $voice = $row['value'];
            }
        } catch (\Throwable $e) {
            // DB 실패 시 config 기본값 유지
        }
    }

    $title = $newsTitle !== null && trim($newsTitle) !== '' ? trim($newsTitle) : '제목 없음';
    $metaParts = [];
    if ($source !== null && trim($source) !== '') {
        $metaParts[] = '출처 ' . trim($source);
    }
    if ($author !== null && trim($author) !== '') {
        $metaParts[] = '작성자 ' . trim($author);
    }
    $meta = implode('. ', $metaParts);
    if ($meta === '') {
        $meta = '출처 없음';
    }

    $audioUrl = null;
    $lastError = '';
    $googleTtsKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
    if (!empty($googleTtsConfig) && is_string($googleTtsKey) && $googleTtsKey !== '') {
        $googleTts = new GoogleTTSService($googleTtsConfig);
        $audioUrl = $googleTts->textToSpeechStructured($title, $meta, $narration, ['voice' => $voice]);
        if ($audioUrl === null || $audioUrl === '') {
            $lastError = $googleTts->getLastError();
            $audioUrl = $googleTts->textToSpeech($narration, ['voice' => $voice]);
        }
        if (($audioUrl === null || $audioUrl === '') && $lastError === '') {
            $lastError = $googleTts->getLastError();
        }
    }
    if ($audioUrl === null || $audioUrl === '') {
        try {
            $openai = new OpenAIService([]);
            $audioUrl = $openai->textToSpeech($narration);
        } catch (\Throwable $e) {
            $lastError = $lastError ?: ('OpenAI TTS: ' . $e->getMessage());
        }
    }

    if ($audioUrl !== null && $audioUrl !== '') {
        return ['success' => true, 'audio_url' => $audioUrl];
    }
    $errMsg = $lastError !== '' ? ('TTS 생성 실패: ' . $lastError) : 'TTS 생성 실패. Google TTS 또는 OpenAI TTS 설정을 확인하세요.';
    return ['success' => false, 'error' => $errMsg];
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

/**
 * GPT API 호출 단독 테스트 (스크래핑/파이프라인 없이 OpenAI만 호출)
 * POST action=test_openai → 실제 API 호출 후 성공/실패 반환
 */
function testOpenAICall(): array {
    global $envLoaded, $envTried;
    $openai = new OpenAIService([]);
    $debug = [
        'env_loaded' => $envLoaded ?? false,
        'env_tried' => $envTried ?? [],
        'openai_configured' => $openai->isConfigured(),
        'mock_mode' => $openai->isMockMode(),
    ];
    if ($openai->isMockMode()) {
        return [
            'success' => false,
            'message' => 'Mock 모드입니다. API 키가 없어 실제 OpenAI 호출을 하지 않습니다.',
            'debug' => $debug,
            'response' => null,
            'error' => 'OPENAI_API_KEY not set or mock_mode'
        ];
    }
    $start = microtime(true);
    try {
        $response = $openai->chat(
            'You are a test assistant. Reply briefly.',
            'Say exactly: OK',
            ['max_tokens' => 10, 'timeout' => 30]
        );
        $ms = round((microtime(true) - $start) * 1000, 2);
        return [
            'success' => true,
            'message' => 'GPT API 호출 성공',
            'debug' => $debug,
            'response' => $response,
            'duration_ms' => $ms,
            'error' => null
        ];
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $start) * 1000, 2);
        return [
            'success' => false,
            'message' => 'GPT API 호출 실패: ' . $e->getMessage(),
            'debug' => $debug,
            'response' => null,
            'duration_ms' => $ms,
            'error' => $e->getMessage()
        ];
    }
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
                'openai_key_set' => !empty($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')),
                'env_loaded' => $envLoaded ?? false,
                'env_tried' => $envTried ?? [],
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

                case 'generate_tts':
                    $narration = $input['narration'] ?? '';
                    $ttsVoice = isset($input['tts_voice']) && is_string($input['tts_voice']) ? $input['tts_voice'] : null;
                    $newsTitle = isset($input['news_title']) && is_string($input['news_title']) ? $input['news_title'] : null;
                    $source = isset($input['source']) && is_string($input['source']) ? $input['source'] : null;
                    $author = isset($input['author']) && is_string($input['author']) ? $input['author'] : null;
                    $result = generateTtsFromNarration($narration, $ttsVoice, $newsTitle, $source, $author);
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

                case 'test_openai':
                    $result = testOpenAICall();
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
