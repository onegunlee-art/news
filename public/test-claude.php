<?php
/**
 * Claude API 연동 테스트 엔드포인트
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/src/agents/autoload.php';

// .env 로드
$envPath = dirname(__DIR__) . '/.env';
$envTxtPath = dirname(__DIR__) . '/env.txt';

// env.txt 우선 (서버 배포용)
if (file_exists($envTxtPath)) {
    $envPath = $envTxtPath;
}
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
            putenv($line);
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

use Agents\Services\ClaudeService;

try {
    $claude = new ClaudeService();
    $result = $claude->testConnection();
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
