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
use Agents\Services\SupabaseService;
use Agents\Services\RAGService;

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

/**
 * 504 게이트웨이 타임아웃 회피: 백그라운드 작업 + 폴링
 * analyze 요청 시 즉시 job_id 반환 후, 파이프라인을 백그라운드에서 실행
 */
function getJobsDir(): string {
    global $projectRoot;
    $dir = rtrim($projectRoot, '/\\') . '/storage/jobs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/';
}

function getJobFilePath(string $jobId): string {
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);
    if ($safe !== $jobId || strlen($jobId) > 64) {
        throw new \InvalidArgumentException('Invalid job_id');
    }
    return getJobsDir() . $jobId . '.json';
}

function writeJobStatus(string $jobId, array $data): void {
    $path = getJobFilePath($jobId);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function readJobStatus(string $jobId): ?array {
    $path = getJobFilePath($jobId);
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * 즉시 응답 전송 후 스크립트 계속 실행 (504 타임아웃 회피)
 */
function sendResponseAndContinue(array $data, int $status = 200): void {
    ob_clean();
    http_response_code($status);
    header('Content-Length: ' . strlen(json_encode($data, JSON_UNESCAPED_UNICODE)));
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
    }
    ignore_user_abort(true);
}

/**
 * TTS 미디어 캐시 키 (동일 입력이면 동일 해시 → 캐시 히트)
 */
function buildTtsCacheKey(string $narration, ?string $ttsVoice, ?string $newsTitle, ?string $source, ?string $author, ?string $publishedAt = null): string {
    $payload = [
        trim($narration),
        $ttsVoice ?? '',
        $newsTitle ?? '',
        $source ?? '',
        $author ?? '',
        $publishedAt ?? '',
    ];
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
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

    $ragService = null;
    $supabase = new SupabaseService([]);
    if ($supabase->isConfigured()) {
        $ragService = new RAGService(new OpenAIService([]), $supabase);
    }

    $pipelineConfig = [
        'project_root' => rtrim($projectRoot, '/\\'),
        'openai' => [],
        'scraper' => ['timeout' => 60],
        'enable_interpret' => $options['enable_interpret'] ?? true,
        'enable_learning' => $options['enable_learning'] ?? true,
        'google_tts' => $googleTtsConfig,
        'rag_service' => $ragService,
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

        // RAG: 분석 결과 임베딩 저장 (AI 학습용)
        if ($ragService !== null && $ragService->isConfigured()) {
            $toStore = ($finalAnalysis['content_summary'] ?? '') . "\n\n" . ($narration ?? '');
            if (!empty($finalAnalysis['key_points'])) {
                $toStore .= "\n\n" . implode("\n", $finalAnalysis['key_points']);
            }
            if (trim($toStore) !== '') {
                $ragService->storeAnalysisEmbedding(
                    null,
                    $url,
                    $toStore,
                    'analysis',
                    ['news_title' => $finalAnalysis['news_title'] ?? '']
                );
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
                'original_title' => $finalAnalysis['original_title'] ?? null,
                'author' => $finalAnalysis['author'] ?? null,
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
 * 순서: 제목 → 1초 휴식 → 편집 문구(날짜·출처) → 1초 휴식 → 내레이션(발췌)
 * 입력: narration (필수), tts_voice (선택), news_title, source, published_at (선택, 있으면 구조화 재생)
 * 출력: { success, audio_url } 또는 { success: false, error }
 * 긴 기사(예: Foreign Affairs)는 내레이션이 매우 길어 TTS 시 메모리/타임아웃/API 제한에 걸릴 수 있으므로 바이트 상한 적용.
 */
function generateTtsFromNarration(string $narration, ?string $ttsVoice = null, ?string $newsTitle = null, ?string $source = null, ?string $author = null, ?string $publishedAt = null): array {
    global $projectRoot;

    set_time_limit(1200); // TTS 40청크 + 재시도 고려 20분

    $narration = trim($narration);
    if ($narration === '') {
        return ['success' => false, 'error' => 'narration is required'];
    }

    // 긴 내레이션 시 메모리·타임아웃·Google 청크 수 제한 회피 (UTF-8 경계로 자르기)
    $maxNarrationBytes = 192000; // 40청크(4800바이트) 분량 → 약 10분 분량
    if (strlen($narration) > $maxNarrationBytes) {
        $narration = mb_strcut($narration, 0, $maxNarrationBytes, 'UTF-8');
    }

    // 저장 경로 사전 생성 (권한 문제 미리 방지)
    $storageDir = rtrim($projectRoot, '/') . '/storage/audio';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }
    $googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php')
        ? require $projectRoot . 'config/google_tts.php'
        : [];
    $googleTtsKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
    if (is_string($googleTtsKey) && $googleTtsKey !== '') {
        $googleTtsConfig['api_key'] = $googleTtsKey;
    }
    $googleTtsConfig['audio_storage_path'] = $storageDir;
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
    $sourceName = ($source !== null && trim($source) !== '') ? trim($source) : 'The Gist';
    // "Foreign Affairs Magazine" → "Foreign Affairs" (Magazine 제거)
    if (preg_match('/^(.+?)\s+Magazine$/i', $sourceName, $m)) {
        $sourceName = $m[1];
    }
    $dateStr = '';
    if ($publishedAt !== null && trim($publishedAt) !== '') {
        try {
            $dt = new \DateTime(trim($publishedAt));
            $dateStr = $dt->format('Y년 n월 j일');
        } catch (\Throwable $e) {
            $dateStr = '';
        }
    }
    if ($dateStr === '') {
        $dateStr = (new \DateTime())->format('Y년 n월 j일');
    }
    $meta = $dateStr . '자 ' . $sourceName . ' 저널의 "' . $title . '"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.';

    $audioUrl = null;
    $lastError = '';
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
        if ($audioUrl === null || $audioUrl === '') {
            $lastError = $lastError ?: 'Google TTS 실패 (저장 경로 또는 API 확인)';
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
    if (($audioUrl === null || $audioUrl === '') && strlen($narration) > 4800) {
        $shortNarration = mb_strcut($narration, 0, 4000, 'UTF-8');
        if (!empty($googleTtsConfig) && is_string($googleTtsKey) && $googleTtsKey !== '') {
            $googleTts = new GoogleTTSService(array_merge($googleTtsConfig, ['audio_storage_path' => $storageDir]));
            $audioUrl = $googleTts->textToSpeech($shortNarration, ['voice' => $voice]);
            if ($audioUrl === null && $lastError === '') {
                $lastError = $googleTts->getLastError();
            }
        }
        if (($audioUrl === null || $audioUrl === '') && $shortNarration !== '') {
            try {
                $openai = new OpenAIService([]);
                $audioUrl = $openai->textToSpeech($shortNarration);
            } catch (\Throwable $e) {
                $lastError = $lastError ?: ('OpenAI TTS: ' . $e->getMessage());
            }
        }
    }

    if ($audioUrl !== null && $audioUrl !== '') {
        return ['success' => true, 'audio_url' => $audioUrl];
    }
    $errMsg = $lastError !== '' ? ('TTS 생성 실패: ' . $lastError) : 'TTS 생성 실패. Google TTS 또는 OpenAI TTS 설정을 확인하세요.';
    return ['success' => false, 'error' => $errMsg];
}

/**
 * Listen과 동일한 구조로 TTS 생성 (제목 pause 매체설명 pause 내레이션 pause Critique)
 * cache_hash로 tts_{hash}.wav 저장 → Listen 캐시와 공유
 */
function generateTtsFromNarrationStructured(string $title, string $meta, string $narration, string $critiquePart, string $voice, string $cacheHash, string $projectRoot): array {
    $storageDir = rtrim($projectRoot, '/') . '/storage/audio';
    if (!is_dir($storageDir)) @mkdir($storageDir, 0755, true);

    $googleTtsConfig = file_exists($projectRoot . 'config/google_tts.php') ? require $projectRoot . 'config/google_tts.php' : [];
    $googleTtsKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
    if (is_string($googleTtsKey) && $googleTtsKey !== '') $googleTtsConfig['api_key'] = $googleTtsKey;
    $googleTtsConfig['audio_storage_path'] = $storageDir;

    $maxNarrationBytes = 192000;
    if (strlen($narration) > $maxNarrationBytes) $narration = mb_strcut($narration, 0, $maxNarrationBytes, 'UTF-8');

    $googleTts = new GoogleTTSService($googleTtsConfig);
    $audioUrl = $googleTts->textToSpeechStructured($title, $meta, $narration, ['voice' => $voice, 'cache_hash' => $cacheHash], $critiquePart);

    if ($audioUrl === null || $audioUrl === '') {
        return ['success' => false, 'error' => $googleTts->getLastError() ?: 'TTS 생성 실패'];
    }
    return ['success' => true, 'audio_url' => $audioUrl];
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
 * DALL-E로 썸네일 수정 (Admin에서 직접 프롬프트 입력)
 * POST action=regenerate_thumbnail_dalle → { prompt (필수), news_title (선택) }
 */
function regenerateThumbnailDalle(array $input): array {
    $prompt = isset($input['prompt']) && is_string($input['prompt']) ? trim($input['prompt']) : '';
    $newsTitle = isset($input['news_title']) && is_string($input['news_title']) ? trim($input['news_title']) : '';
    $title = $prompt !== '' ? $prompt : $newsTitle;
    if ($title === '') {
        return ['success' => false, 'error' => 'prompt 또는 news_title이 필요합니다.', 'image_url' => null];
    }
    $openai = new OpenAIService([]);
    if ($openai->isMockMode()) {
        return ['success' => false, 'error' => 'OPENAI_API_KEY not set. DALL-E를 사용할 수 없습니다.', 'image_url' => null];
    }
    $titleSnippet = mb_substr($title, 0, 200);
    $effectivePrompt = "Start by using the original headline of the article from the provided URL as the default basis for the thumbnail concept. "
        . "Based on the article title (without extracting or quoting the full text), create a custom thumbnail concept art in a witty metaphorical cartoon style that visually represents the key idea implied by the title: \"" . $titleSnippet . "\". "
        . "Style: Playful metaphor cartoon (no literal portraits), with a medium level of satire. "
        . "Main characters: Include 1–2 protagonist characters representing the key country or countries, expressed through national characteristics or flags in a stylized, symbolic way. "
        . "Composition: Vertical (portrait) orientation with a wide cinematic feel optimized for a tall thumbnail. "
        . "Background: Keep the background clean and not overly complex so the main symbols and characters stand out clearly. "
        . "Visual elements: The image must include symbolic objects, at least one clear national symbol, and visible flags integrated naturally into the scene. "
        . "No text in the image. "
        . "Imagery should convey the concept of the article title without any text. "
        . "Clever symbolic elements and humor are encouraged. "
        . "Do NOT include any written titles or captions in the thumbnail itself.";
    try {
        $url = $openai->createImage($effectivePrompt, ['timeout' => 90]);
        $lastErr = $openai->getLastError();
        if ($url !== null) {
            return ['success' => true, 'image_url' => $url, 'error' => null];
        }
        return ['success' => false, 'error' => $lastErr ?? 'DALL-E 이미지 생성 실패', 'image_url' => null];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => 'DALL-E 오류: ' . $e->getMessage(), 'image_url' => null];
    }
}

/**
 * DALL-E 3 연동 테스트 (썸네일 생성용)
 * POST action=test_dalle → 단순 프롬프트로 이미지 생성 시도
 */
function testDalleCall(): array {
    global $envLoaded, $envTried;
    $openai = new OpenAIService([]);
    $root = findProjectRoot();
    $cfgFile = $root . 'config/openai.php';
    $cfg = is_file($cfgFile) ? (require $cfgFile) : [];
    $cfg = is_array($cfg) ? $cfg : [];
    $debug = [
        'env_loaded' => $envLoaded ?? false,
        'openai_configured' => $openai->isConfigured(),
        'mock_mode' => $openai->isMockMode(),
        'images_endpoint' => $cfg['endpoints']['images'] ?? 'https://api.openai.com/v1/images/generations',
    ];
    if ($openai->isMockMode()) {
        return [
            'success' => false,
            'message' => 'Mock 모드. OPENAI_API_KEY가 설정되지 않았습니다.',
            'debug' => $debug,
            'error' => 'OPENAI_API_KEY not set',
        ];
    }
    $start = microtime(true);
    try {
        $url = $openai->createImage('A simple blue circle on white background, minimal editorial style.', ['timeout' => 60]);
        $ms = round((microtime(true) - $start) * 1000, 2);
        $lastErr = $openai->getLastError();
        return [
            'success' => $url !== null,
            'message' => $url ? 'DALL-E 3 연동 성공' : ($lastErr ?? 'DALL-E 3 이미지 생성 실패'),
            'debug' => array_merge($debug, ['duration_ms' => $ms]),
            'image_url' => $url,
            'error' => $url ? null : ($lastErr ?? 'createImage returned null'),
        ];
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $start) * 1000, 2);
        return [
            'success' => false,
            'message' => 'DALL-E 3 호출 실패: ' . $e->getMessage(),
            'debug' => array_merge($debug, ['duration_ms' => $ms]),
            'error' => $e->getMessage(),
        ];
    }
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
            // 폴링: job_status?job_id=xxx
            $action = $_GET['action'] ?? '';
            $jobId = $_GET['job_id'] ?? '';
            if ($action === 'job_status' && $jobId !== '') {
                try {
                    $job = readJobStatus($jobId);
                } catch (\Throwable $e) {
                    sendResponse(['success' => false, 'status' => 'unknown', 'error' => 'Invalid job_id']);
                }
                if ($job === null) {
                    sendResponse(['success' => false, 'status' => 'unknown', 'error' => 'Job not found']);
                }
                if (($job['status'] ?? '') === 'processing') {
                    sendResponse([
                        'success' => true,
                        'status' => 'processing',
                        'job_id' => $jobId,
                        'message' => '분석 중... 잠시만 기다려주세요.',
                    ]);
                }
                if (($job['status'] ?? '') === 'done' || ($job['status'] ?? '') === 'failed') {
                    unset($job['status']);
                    sendResponse($job);
                }
                sendResponse($job);
            }
            
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
                    
                    // 504 회피: job 생성 후 즉시 반환, 파이프라인은 백그라운드 실행
                    $jobId = 'job_' . bin2hex(random_bytes(12));
                    writeJobStatus($jobId, [
                        'status' => 'processing',
                        'url' => $url,
                        'created_at' => date('c'),
                    ]);
                    
                    sendResponseAndContinue([
                        'success' => true,
                        'job_id' => $jobId,
                        'status' => 'processing',
                        'message' => '분석을 시작했습니다. 잠시만 기다려주세요.',
                        'analysis' => null,
                    ]);
                    
                    // 백그라운드: 파이프라인 실행
                    try {
                        $result = analyzeUrl($url, $options);
                        $result['job_id'] = $jobId;
                        $result['status'] = $result['success'] ? 'done' : 'failed';
                        writeJobStatus($jobId, $result);
                    } catch (\Throwable $e) {
                        writeJobStatus($jobId, [
                            'status' => 'failed',
                            'job_id' => $jobId,
                            'success' => false,
                            'error' => 'Pipeline 예외: ' . $e->getMessage(),
                            'analysis' => null,
                        ]);
                    }
                    exit;

                case 'generate_tts':
                    // Listen과 동일한 해시/구조 사용 → 기사 생성 시 TTS가 Listen 캐시에 선반입됨
                    $newsIdForCache = isset($input['news_id']) && (is_int($input['news_id']) || ctype_digit((string) $input['news_id'])) ? (int) $input['news_id'] : null;
                    $ttsVoice = isset($input['tts_voice']) && is_string($input['tts_voice']) ? trim($input['tts_voice']) : null;
                    $voiceForParams = $ttsVoice ?: ((function() use ($projectRoot) {
                        $cfg = file_exists($projectRoot . 'config/google_tts.php') ? require $projectRoot . 'config/google_tts.php' : [];
                        return $cfg['default_voice'] ?? 'ko-KR-Standard-A';
                    })());

                    // 신규 형식: title, meta, narration, critique_part (Listen과 동일)
                    $title = isset($input['title']) ? trim((string) $input['title']) : '';
                    $meta = isset($input['meta']) ? trim((string) $input['meta']) : '';
                    $narration = isset($input['narration']) ? trim((string) $input['narration']) : '';
                    $critiquePart = isset($input['critique_part']) ? trim((string) $input['critique_part']) : '';

                    // 구형 형식: narration, news_title, source, author, published_at → title, meta, critique_part로 변환
                    if ($title === '' && $meta === '' && $narration !== '') {
                        $newsTitle = isset($input['news_title']) && is_string($input['news_title']) ? trim($input['news_title']) : '제목 없음';
                        $source = isset($input['source']) && is_string($input['source']) ? trim($input['source']) : 'The Gist';
                        $author = isset($input['author']) && is_string($input['author']) ? trim($input['author']) : '';
                        $publishedAt = isset($input['published_at']) && is_string($input['published_at']) ? trim($input['published_at']) : null;
                        if (preg_match('/^(.+?)\s+Magazine$/i', $source, $m)) $source = $m[1];
                        $dateStr = '';
                        if ($publishedAt) {
                            try { $dt = new \DateTime($publishedAt); $dateStr = $dt->format('Y년 n월 j일'); } catch (\Throwable $e) {}
                        }
                        if ($dateStr === '') $dateStr = (new \DateTime())->format('Y년 n월 j일');
                        $title = $newsTitle;
                        $meta = $dateStr . '자 ' . $source . ' 저널의 "' . $title . '"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.';
                    }

                    if ($narration === '' && $critiquePart === '') {
                        sendResponse(['success' => false, 'error' => 'narration or critique_part is required']);
                        break;
                    }

                    $fullPayload = $title . '|' . $meta . '|' . $narration . '|' . $critiquePart . '|' . $voiceForParams;
                    $ttsCacheKey = hash('sha256', $fullPayload);

                    $supabaseForMedia = new SupabaseService([]);
                    $storageDir = rtrim($projectRoot, '/') . '/storage/audio';
                    $safeHash = preg_replace('/[^a-f0-9]/', '', $ttsCacheKey);

                    // 파일 캐시
                    if (is_file($storageDir . '/tts_' . $safeHash . '.wav')) {
                        sendResponse(['success' => true, 'audio_url' => '/storage/audio/tts_' . $safeHash . '.wav', 'from_cache' => true]);
                        break;
                    }
                    // Supabase 캐시
                    if ($supabaseForMedia->isConfigured()) {
                        $cacheQuery = 'media_type=eq.tts&generation_params->>hash=eq.' . rawurlencode($ttsCacheKey);
                        $cached = $supabaseForMedia->select('media_cache', $cacheQuery, 1);
                        if (!empty($cached) && is_array($cached) && !empty($cached[0]['file_url'])) {
                            sendResponse(['success' => true, 'audio_url' => $cached[0]['file_url'], 'from_cache' => true]);
                            break;
                        }
                    }

                    set_time_limit(1200);
                    $result = generateTtsFromNarrationStructured($title ?: '제목 없음', $meta ?: ' ', $narration, $critiquePart, $voiceForParams, $ttsCacheKey, $projectRoot);

                    if ($result['success'] && !empty($result['audio_url']) && $supabaseForMedia->isConfigured()) {
                        $supabaseForMedia->insert('media_cache', [
                            'news_id' => $newsIdForCache,
                            'media_type' => 'tts',
                            'file_url' => $result['audio_url'],
                            'generation_params' => ['hash' => $ttsCacheKey, 'voice' => $voiceForParams],
                        ]);
                    }

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

                case 'test_dalle':
                    $result = testDalleCall();
                    sendResponse($result);
                    break;

                case 'regenerate_thumbnail_dalle':
                    $result = regenerateThumbnailDalle($input);
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
