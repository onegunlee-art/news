<?php
/**
 * SNS/메신저 크롤러용 최소 HTML (Open Graph)
 * Nginx: /news/{id} 요청 중 UA가 봇이면 이 스크립트로 rewrite (aws/nginx-snippet-og-crawler-news.conf 참고)
 *
 * GET /og-news-html.php?id=123
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><title>Bad request</title></head><body></body></html>';
    exit;
}

$projectRoot = dirname(__DIR__);
$appConfigPath = $projectRoot . '/config/app.php';
$appConfig = file_exists($appConfigPath) ? require $appConfigPath : [];
$baseUrl = rtrim((string) ($appConfig['url'] ?? getenv('APP_URL') ?: 'https://www.thegist.co.kr'), '/');

$dbConfigPath = $projectRoot . '/config/database.php';
$dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'ailand',
    'username' => getenv('DB_USERNAME') ?: 'ailand',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
$dbConfig['dbname'] = $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'ailand';

$title = 'the gist.';
$canonical = $baseUrl . '/news/' . $id;

try {
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['dbname'], $dbConfig['charset']),
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $hasStatus = false;
    try {
        $c = $db->query("SHOW COLUMNS FROM news LIKE 'status'");
        $hasStatus = $c && $c->fetch() !== false;
    } catch (Throwable $e) {
        $hasStatus = false;
    }
    $where = 'id = ?';
    $params = [$id];
    if ($hasStatus) {
        $where .= " AND (status = 'published' OR status IS NULL)";
    }
    $stmt = $db->prepare("SELECT title FROM news WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['title'])) {
        $title = (string) $row['title'] . ' | the gist.';
    } else {
        http_response_code(404);
    }
} catch (Throwable $e) {
    $title = 'the gist.';
}

$esc = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

$ogImage = $baseUrl . '/og-share-brand.svg';
$ogDesc = 'the gist.';

?><!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $esc($title) ?></title>
  <meta name="description" content="<?= $esc($ogDesc) ?>" />
  <link rel="canonical" href="<?= $esc($canonical) ?>" />
  <meta property="og:type" content="article" />
  <meta property="og:title" content="<?= $esc($title) ?>" />
  <meta property="og:description" content="<?= $esc($ogDesc) ?>" />
  <meta property="og:url" content="<?= $esc($canonical) ?>" />
  <meta property="og:image" content="<?= $esc($ogImage) ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="the gist." />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= $esc($title) ?>" />
  <meta name="twitter:description" content="<?= $esc($ogDesc) ?>" />
  <meta name="twitter:image" content="<?= $esc($ogImage) ?>" />
</head>
<body></body>
</html>
