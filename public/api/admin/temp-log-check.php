<?php
header('Content-Type: text/plain; charset=utf-8');
$secret = $_GET['key'] ?? '';
if ($secret !== 'ws_nzwk99oqjj121exh') {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$logPath = $_SERVER['DOCUMENT_ROOT'] . '/../storage/logs/payment.log';
if (!file_exists($logPath)) {
    $logPath = __DIR__ . '/../../../storage/logs/payment.log';
}
if (!file_exists($logPath)) {
    echo "payment.log not found\nTried: " . $logPath;
    exit;
}

$search = $_GET['q'] ?? '';
if ($search) {
    $lines = file($logPath, FILE_IGNORE_NEW_LINES);
    $matches = array_filter($lines, fn($l) => stripos($l, $search) !== false);
    echo implode("\n", array_values($matches));
} else {
    $content = file_get_contents($logPath);
    $size = strlen($content);
    echo "Log size: {$size} bytes\n";
    echo substr($content, max(0, $size - 5000));
}
