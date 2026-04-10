<?php
/**
 * 관리자 썸네일 이미지 업로드 API
 * POST multipart/form-data — file: 이미지 파일
 * 성공 시: { success: true, image_url: "/storage/thumbnails/xxx.webp" }
 */

require_once __DIR__ . '/../lib/cors.php';
require_once __DIR__ . '/../lib/auth.php';

header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();
handleOptionsRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST만 허용됩니다.']);
    exit;
}

$pdo = getDb();
$userId = getAuthUserId($pdo);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$allowedExt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$maxSize     = 10 * 1024 * 1024; // 10 MB

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "파일 업로드 실패 (에러코드: $code)"]);
    exit;
}

$file = $_FILES['file'];

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '파일 크기가 10MB를 초과합니다.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMime, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 파일 형식입니다. (jpg, png, webp, gif만 가능)']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext = $mimeToExt[$mime] ?? 'jpg';
}

$projectRoot = realpath(__DIR__ . '/../../../') ?: dirname(__DIR__, 3);
$storageDir  = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'thumbnails';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

$filename = 'upload-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $storageDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '파일 저장에 실패했습니다.']);
    exit;
}

chmod($destPath, 0644);

$webUrl = '/storage/thumbnails/' . $filename;

echo json_encode([
    'success'   => true,
    'image_url' => $webUrl,
    'filename'  => $filename,
    'size'      => $file['size'],
    'mime'      => $mime,
]);
