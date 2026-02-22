<?php
/**
 * 닷홈 서버 인벤토리 - 디렉터리 구조 및 용량 확인
 *
 * 사용: GET /api/admin/server-inventory.php?key=YOUR_SECRET
 * 보안: .env에 SERVER_INVENTORY_KEY 설정 또는 기본값 사용 (배포 후 삭제 권장)
 *
 * @package Admin
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET only']);
    exit;
}

// .env 로드
// 서버: /html/api/admin → projectRoot = /html/
$projectRoot = dirname(__DIR__, 2) . '/';
foreach (['env.txt', '.env', '.env.production'] as $f) {
    $path = $projectRoot . $f;
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$n, $v] = explode('=', $line, 2);
                $n = trim($n); $v = trim($v, " \t\"'");
                if ($n !== '') { putenv("$n=$v"); $_ENV[$n] = $v; }
            }
        }
        break;
    }
}

$expectedKey = $_ENV['SERVER_INVENTORY_KEY'] ?? getenv('SERVER_INVENTORY_KEY') ?: 'inventory-' . date('Ymd');
$givenKey = $_GET['key'] ?? '';
if ($givenKey !== $expectedKey) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Invalid or missing key',
        'hint' => 'Use ?key=YOUR_SECRET (set SERVER_INVENTORY_KEY in .env, or default: inventory-YYYYMMDD)',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function scanDirSafe(string $path, int $maxDepth = 4, int $depth = 0): array {
    if ($depth >= $maxDepth) return ['_truncated' => 'max depth'];
    $result = [];
    if (!is_dir($path) || !is_readable($path)) return ['_error' => 'not readable'];
    $items = @scandir($path);
    if ($items === false) return ['_error' => 'scandir failed'];
    $totalSize = 0;
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = $path . DIRECTORY_SEPARATOR . $name;
        if (is_dir($full)) {
            $sub = scanDirSafe($full, $maxDepth, $depth + 1);
            $result[$name . '/'] = $sub;
        } else {
            $size = @filesize($full);
            $result[$name] = $size !== false ? formatBytes($size) : '?';
            $totalSize += $size !== false ? $size : 0;
        }
    }
    $result['_total'] = formatBytes($totalSize);
    return $result;
}

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
$docRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $docRoot), DIRECTORY_SEPARATOR);

$inventory = [
    'generated_at' => date('c'),
    'document_root' => $docRoot,
    'php_version' => PHP_VERSION,
    'structure' => scanDirSafe($docRoot, 3),
];

// storage 하위 용량
$storagePath = $docRoot . DIRECTORY_SEPARATOR . 'storage';
if (is_dir($storagePath)) {
    $storageDirs = [];
    foreach (['cache', 'logs', 'audio', 'thumbnails', 'learning', 'jobs'] as $d) {
        $p = $storagePath . DIRECTORY_SEPARATOR . $d;
        if (is_dir($p)) {
            $count = count(glob($p . DIRECTORY_SEPARATOR . '*'));
            $storageDirs[$d] = ['files' => $count];
        }
    }
    $inventory['storage'] = $storageDirs;
}

echo json_encode($inventory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
