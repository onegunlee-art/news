<?php
/**
 * API 과금 대시보드 API
 * - 자체 api_usage_logs 집계 (실시간)
 * - OpenAI Usage API (옵션: OPENAI_ADMIN_KEY 또는 OPENAI_API_KEY)
 * - Google TTS, Kakao, Supabase 등 연결 정보
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$projectRoot = dirname(__DIR__, 3) . '/';

// .env 로드 (database.php 등에서 getenv 사용)
$envPath = $projectRoot . (file_exists($projectRoot . '.env') ? '.env' : 'env.txt');
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
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

// DB 연결
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => '',
    'charset' => 'utf8mb4'
];

if (file_exists($projectRoot . 'config/database.php')) {
    $cfg = require $projectRoot . 'config/database.php';
    $dbConfig['host'] = $cfg['host'] ?? $dbConfig['host'];
    $dbConfig['dbname'] = $cfg['database'] ?? $cfg['dbname'] ?? $dbConfig['dbname'];
    $dbConfig['username'] = $cfg['username'] ?? $dbConfig['username'];
    $dbConfig['password'] = $cfg['password'] ?? $dbConfig['password'];
    $dbConfig['charset'] = $cfg['charset'] ?? $dbConfig['charset'];
}

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']);
    exit;
}

$openaiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
$openaiAdminKey = $_ENV['OPENAI_ADMIN_KEY'] ?? getenv('OPENAI_ADMIN_KEY') ?: '';
$googleTtsKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY') ?: '';
$kakaoKey = $_ENV['KAKAO_API_KEY'] ?? getenv('KAKAO_API_KEY') ?: '';
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?: '';
$nytKey = $_ENV['NYT_API_KEY'] ?? getenv('NYT_API_KEY') ?: '';

// api_usage_logs 테이블 존재 여부
$hasUsageTable = false;
try {
    $db->query("SELECT 1 FROM api_usage_logs LIMIT 1");
    $hasUsageTable = true;
} catch (PDOException $e) {
    // 테이블 없음 - 마이그레이션 필요
}

// 자체 로그 집계 (일/월)
$selfToday = [];
$selfMonth = [];
$selfByProvider = [];

if ($hasUsageTable) {
    $todayStart = date('Y-m-d 00:00:00');
    $monthStart = date('Y-m-01 00:00:00');

    $stmt = $db->prepare("
        SELECT provider, endpoint,
               SUM(input_tokens) as input_tokens,
               SUM(output_tokens) as output_tokens,
               SUM(images) as images,
               SUM(characters) as characters,
               SUM(requests) as requests,
               SUM(COALESCE(estimated_cost_usd, 0)) as cost_usd
        FROM api_usage_logs
        WHERE created_at >= ?
        GROUP BY provider, endpoint
    ");
    $stmt->execute([$todayStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['provider'] . ':' . $row['endpoint'];
        $selfToday[$key] = $row;
    }

    $stmt->execute([$monthStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['provider'] . ':' . $row['endpoint'];
        $selfMonth[$key] = $row;
    }

    // provider별 합계
    $stmt = $db->prepare("
        SELECT provider,
               SUM(input_tokens) as input_tokens,
               SUM(output_tokens) as output_tokens,
               SUM(images) as images,
               SUM(characters) as characters,
               SUM(requests) as requests,
               SUM(COALESCE(estimated_cost_usd, 0)) as cost_usd
        FROM api_usage_logs
        WHERE created_at >= ?
        GROUP BY provider
    ");
    $stmt->execute([$monthStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $selfByProvider[$row['provider']] = $row;
    }
}

// OpenAI Usage API (옵션)
$openaiUsage = null;
$openaiError = null;
$usageKey = $openaiAdminKey ?: $openaiKey;
if ($usageKey !== '') {
    $base = 'https://api.openai.com/v1/organization/usage';
    $start = strtotime('-30 days');
    $end = time();

    $endpoints = ['completions', 'embeddings', 'images'];
    $openaiUsage = ['completions' => null, 'embeddings' => null, 'images' => null];

    foreach ($endpoints as $ep) {
        $url = $base . '/' . $ep . '?start_time=' . $start . '&end_time=' . $end . '&limit=31&bucket_width=1d';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $usageKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            $openaiUsage[$ep] = $data;
        } else {
            if ($openaiError === null) {
                $openaiError = 'OpenAI Usage API: HTTP ' . $code . ' (일반 API 키로는 사용량 조회 불가할 수 있음. Organization Admin 키 필요)';
            }
        }
    }

    // Costs API (과금 상세)
    $costsUrl = $base . '/costs?start_time=' . $start . '&end_time=' . $end . '&limit=31';
    $ch = curl_init($costsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $usageKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $costResp = curl_exec($ch);
    $costCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $openaiUsage['costs'] = ($costCode === 200 && $costResp) ? json_decode($costResp, true) : null;
}

// 응답
$response = [
    'success' => true,
    'providers' => [
        'openai' => [
            'configured' => $openaiKey !== '',
            'admin_key' => $openaiAdminKey !== '',
            'usage' => $openaiUsage,
            'usage_error' => $openaiError,
            'dashboard_url' => 'https://platform.openai.com/settings/organization/usage',
        ],
        'google_tts' => [
            'configured' => $googleTtsKey !== '',
            'dashboard_url' => 'https://console.cloud.google.com/apis/dashboard',
            'billing_url' => 'https://console.cloud.google.com/billing',
        ],
        'kakao' => [
            'configured' => $kakaoKey !== '',
            'dashboard_url' => 'https://developers.kakao.com/console/app',
        ],
        'supabase' => [
            'configured' => $supabaseUrl !== '',
            'dashboard_url' => 'https://supabase.com/dashboard/project/_/settings/general',
        ],
        'nyt' => [
            'configured' => $nytKey !== '',
            'dashboard_url' => 'https://developer.nytimes.com/',
        ],
    ],
    'self_tracked' => [
        'has_table' => $hasUsageTable,
        'today' => array_values($selfToday),
        'month' => array_values($selfMonth),
        'by_provider' => $selfByProvider,
    ],
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
