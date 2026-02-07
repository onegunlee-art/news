<?php
/**
 * 기존 기사 썸네일 일괄 갱신 (한 번 실행용)
 *
 * 브라우저에서 아래 주소로 접속하면 전체 기사 사진이 새 규칙으로 바뀝니다.
 * https://www.thegist.com/run-thumbnail-update.php?run=1
 *
 * 실행 후 보안을 위해 이 파일을 삭제하세요.
 */
header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['run']) || $_GET['run'] !== '1') {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>썸네일 갱신</title></head><body style="font-family:sans-serif;padding:2rem;">';
    echo '<p>기존 기사 썸네일을 <strong>전체 갱신</strong>하려면 아래 링크를 클릭하세요.</p>';
    echo '<p><a href="?run=1" style="background:#7c3aed;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;">썸네일 전체 갱신 실행</a></p>';
    echo '<p style="color:#666;font-size:14px;">실행 후 이 파일(run-thumbnail-update.php)을 서버에서 삭제하는 것을 권장합니다.</p>';
    echo '</body></html>';
    exit;
}

$host = 'localhost';
$dbname = 'ailand';
$username = 'ailand';
$password = 'romi4120!';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><p>DB 연결 실패.</p></body></html>';
    exit;
}

require_once __DIR__ . '/api/lib/imageSearch.php';

$stmt = $pdo->query("SELECT id, title, category, image_url FROM news ORDER BY id DESC");
$newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($newsList);
$done = 0;

foreach ($newsList as $news) {
    $newUrl = smartImageUrl($news['title'], $news['category'] ?? '', $pdo);
    $up = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
    $up->execute([$newUrl, $news['id']]);
    $done++;
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>완료</title></head><body style="font-family:sans-serif;padding:2rem;">';
echo '<h1>썸네일 전체 갱신 완료</h1>';
echo '<p><strong>' . $done . '</strong>개 기사 썸네일이 새 규칙(인물/국가/API)으로 변경되었습니다.</p>';
echo '<p><a href="/">사이트로 이동</a></p>';
echo '<p style="color:#666;font-size:14px;">보안을 위해 이 파일(run-thumbnail-update.php)을 서버에서 삭제하세요.</p>';
echo '</body></html>';
