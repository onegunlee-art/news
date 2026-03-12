<?php
/**
 * GPT 응답 로그 조회 API (디버깅용)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$logDir = dirname(__DIR__, 3) . '/storage/logs';

if (!is_dir($logDir)) {
    echo json_encode(['error' => 'Log directory not found', 'path' => $logDir], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $files = glob($logDir . '/gpt_response_*.json');
    $files = array_map(function($f) {
        return [
            'name' => basename($f),
            'size' => filesize($f),
            'modified' => date('Y-m-d H:i:s', filemtime($f)),
        ];
    }, $files);
    usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));
    echo json_encode(['files' => array_slice($files, 0, 20)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'read') {
    $filename = $_GET['file'] ?? '';
    if (!$filename || !preg_match('/^gpt_response_[a-z]+_[\d\-_]+\.json$/', $filename)) {
        echo json_encode(['error' => 'Invalid filename'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $filepath = $logDir . '/' . $filename;
    if (!file_exists($filepath)) {
        echo json_encode(['error' => 'File not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $content = file_get_contents($filepath);
    $data = json_decode($content, true);
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
