<?php
/**
 * 검색엔진 / SNS 크롤러용 완성 HTML (Open Graph + JSON-LD)
 *
 * Nginx: /news/{id} 요청 중 봇 UA이면 이 스크립트로 rewrite
 * (aws/thegist-nginx.conf, aws/nginx-snippet-og-crawler-news.conf 참고)
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

$canonical = $baseUrl . '/news/' . $id;
$esc = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$brandImage = $baseUrl . '/og-share-brand.svg';

$title = null;
$description = null;
$imageUrl = null;
$publishedAt = null;
$category = null;
$narration = null;

try {
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['dbname'], $dbConfig['charset']),
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $cols = array_map(
        static fn($c) => $c['Field'],
        $db->query("SHOW COLUMNS FROM news")->fetchAll(PDO::FETCH_ASSOC)
    );
    $select = ['title'];
    foreach (['description', 'image_url', 'published_at', 'category', 'narration'] as $col) {
        if (in_array($col, $cols, true)) {
            $select[] = $col;
        }
    }
    $hasStatus = in_array('status', $cols, true);

    $where = 'id = ?';
    $params = [$id];
    if ($hasStatus) {
        $where .= " AND (status = 'published' OR status IS NULL)";
    }
    $selectStr = implode(', ', $select);
    $stmt = $db->prepare("SELECT {$selectStr} FROM news WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['title'])) {
        $title = (string) $row['title'];
        $description = !empty($row['description']) ? (string) $row['description'] : null;
        $imageUrl = !empty($row['image_url']) ? (string) $row['image_url'] : null;
        $publishedAt = !empty($row['published_at']) ? (string) $row['published_at'] : null;
        $category = !empty($row['category']) ? (string) $row['category'] : null;
        $narration = !empty($row['narration']) ? (string) $row['narration'] : null;
    }
} catch (Throwable $e) {
    // DB 오류 시 title=null 유지 → fallback
}

// 기사를 찾지 못하면 SPA index.html을 반환 (빈 화면 방지)
if ($title === null) {
    $indexPath = __DIR__ . '/index.html';
    if (file_exists($indexPath)) {
        readfile($indexPath);
    } else {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>Not found</title></head><body></body></html>';
    }
    exit;
}

$pageTitle = $title . ' | the gist.';
$ogDesc = $description !== null ? mb_substr(strip_tags($description), 0, 200, 'UTF-8') : 'the gist. — 글로벌 이슈를 한눈에';
$rawImage = $imageUrl ?: $brandImage;
$ogImage = (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://'))
    ? $rawImage
    : $baseUrl . '/' . ltrim($rawImage, '/');
$bodyText = $narration ?: $description ?: '';
$bodyText = strip_tags($bodyText);

// JSON-LD NewsArticle
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $title,
    'description' => $ogDesc,
    'url' => $canonical,
    'image' => $ogImage,
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'the gist.',
        'url' => $baseUrl,
    ],
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $canonical,
    ],
];
if ($publishedAt !== null) {
    $jsonLd['datePublished'] = date('c', strtotime($publishedAt));
}
if ($category !== null) {
    $jsonLd['articleSection'] = $category;
}
$jsonLdJson = json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

?><!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $esc($pageTitle) ?></title>
  <meta name="description" content="<?= $esc($ogDesc) ?>" />
  <link rel="canonical" href="<?= $esc($canonical) ?>" />
  <meta property="og:type" content="article" />
  <meta property="og:title" content="<?= $esc($pageTitle) ?>" />
  <meta property="og:description" content="<?= $esc($ogDesc) ?>" />
  <meta property="og:url" content="<?= $esc($canonical) ?>" />
  <meta property="og:image" content="<?= $esc($ogImage) ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name" content="the gist." />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= $esc($pageTitle) ?>" />
  <meta name="twitter:description" content="<?= $esc($ogDesc) ?>" />
  <meta name="twitter:image" content="<?= $esc($ogImage) ?>" />
  <script type="application/ld+json"><?= $jsonLdJson ?></script>
</head>
<body>
  <h1><?= $esc($title) ?></h1>
<?php if ($ogDesc !== ''): ?>
  <p><?= $esc($ogDesc) ?></p>
<?php endif; ?>
<?php if ($bodyText !== ''): ?>
  <article><?= nl2br($esc($bodyText)) ?></article>
<?php endif; ?>
</body>
</html>
