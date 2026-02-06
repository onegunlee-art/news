<?php
/**
 * 배포 서버 API 상태 확인
 * 브라우저에서 /api/status.php 로 열어 각 엔드포인트 200/404/500 여부 확인
 */
header('Content-Type: text/html; charset=utf-8');

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = rtrim($base, '/') . '/api';

// 뉴스 상세 테스트용 id: 목록에서 첫 번째 id 사용 (id=1이 없을 수 있음)
$detailId = 1;
$listUrl = $baseUrl . '/admin/news.php?page=1&per_page=1';
$chList = curl_init($listUrl);
curl_setopt_array($chList, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$listJson = curl_exec($chList);
curl_close($chList);
if ($listJson) {
    $list = @json_decode($listJson, true);
    if (!empty($list['data']['items'][0]['id'])) {
        $detailId = (int) $list['data']['items'][0]['id'];
    }
}

$endpoints = [
    ['GET', '뉴스 상세', $baseUrl . '/news/detail.php?id=' . $detailId],
    ['GET', '뉴스 목록(Admin)', $baseUrl . '/admin/news.php?category=diplomacy&page=1&per_page=5'],
    ['GET', '즐겨찾기 목록', $baseUrl . '/user/bookmarks.php?page=1&per_page=5'],
    ['OPTIONS', '즐겨찾기 추가/삭제', $baseUrl . '/news/bookmark.php'],
];

$results = [];
foreach ($endpoints as [$method, $name, $url]) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => ($method === 'HEAD'),
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $results[] = ['name' => $name, 'method' => $method, 'url' => $url, 'status' => $code];
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 상태</title>
    <style>
        body { font-family: sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #eee; }
        .ok { color: #0a0; }
        .err { color: #c00; }
        .warn { color: #c80; }
    </style>
</head>
<body>
    <h1>API 상태 확인</h1>
    <p>기준 URL: <code><?= htmlspecialchars($baseUrl) ?></code></p>
    <table>
        <thead>
            <tr><th>엔드포인트</th><th>메서드</th><th>상태</th></tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><code><?= htmlspecialchars($r['method']) ?></code></td>
                <td class="<?= $r['status'] >= 200 && $r['status'] < 300 ? 'ok' : ($r['status'] === 401 ? 'warn' : 'err') ?>">
                    <?= $r['status'] ?> <?= $r['status'] === 200 ? 'OK' : ($r['status'] === 401 ? '(로그인 필요)' : '') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
