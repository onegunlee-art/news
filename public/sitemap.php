<?php
/**
 * 동적 sitemap.xml 생성
 *
 * Nginx: /sitemap.xml → /sitemap.php (rewrite)
 * Apache: .htaccess RewriteRule
 *
 * DB에서 published 기사 목록을 가져와 sitemap XML로 출력합니다.
 */

declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$cacheFile = sys_get_temp_dir() . '/thegist_sitemap.xml';
$cacheTtl = 3600;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    readfile($cacheFile);
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

$urls = [];
$urls[] = ['loc' => $baseUrl . '/', 'lastmod' => date('Y-m-d'), 'changefreq' => 'daily', 'priority' => '1.0'];

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
    $hasStatus = in_array('status', $cols, true);
    $hasPublishedAt = in_array('published_at', $cols, true);

    $where = '1=1';
    if ($hasStatus) {
        $where = "(status = 'published' OR status IS NULL)";
    }

    $orderCol = $hasPublishedAt ? 'published_at' : 'created_at';
    $selectCols = 'id';
    if ($hasPublishedAt) {
        $selectCols .= ', published_at';
    }

    $stmt = $db->query("SELECT {$selectCols} FROM news WHERE {$where} ORDER BY {$orderCol} DESC LIMIT 5000");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url = [
            'loc' => $baseUrl . '/news/' . $row['id'],
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];
        if (!empty($row['published_at'])) {
            $url['lastmod'] = date('Y-m-d', strtotime($row['published_at']));
        }
        $urls[] = $url;
    }
} catch (Throwable $e) {
    // DB 오류 시 홈페이지 URL만 출력
}

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') ?></loc>
<?php if (!empty($u['lastmod'])): ?>
    <lastmod><?= $u['lastmod'] ?></lastmod>
<?php endif; ?>
<?php if (!empty($u['changefreq'])): ?>
    <changefreq><?= $u['changefreq'] ?></changefreq>
<?php endif; ?>
<?php if (!empty($u['priority'])): ?>
    <priority><?= $u['priority'] ?></priority>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
<?php
$xml = ob_get_flush();
@file_put_contents($cacheFile, $xml, LOCK_EX);
