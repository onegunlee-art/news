<?php
/**
 * 썸네일 파이프라인 미리보기 (브라우저에서 확인)
 * Foreign Affairs 기사로 ThumbnailAgent 결과를 화면에 표시
 *
 * 사용: 브라우저에서 /thumbnail-preview.php 또는 /public/thumbnail-preview.php 로 접속
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
$url = 'https://www.foreignaffairs.com/united-states/real-risks-saudi-uae-feud';

// .env 로드
$envFile = $projectRoot . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

require_once $projectRoot . '/src/agents/autoload.php';

use Agents\Pipeline\AgentPipeline;

$config = [
    'project_root' => $projectRoot,
    'openai' => ['mock_mode' => true],
    'enable_interpret' => false,
    'enable_learning' => false,
    'google_tts' => [],
    'analysis' => ['enable_tts' => false],
    'stop_on_failure' => true,
];

$pipeline = new AgentPipeline($config);
$pipeline->setupDefaultPipeline();

$start = microtime(true);
$result = $pipeline->run($url);
$duration = round((microtime(true) - $start) * 1000);

$title = '';
$imageUrl = '';
$success = $result->isSuccess();
$error = $result->getError();

if ($success) {
    $article = $result->context?->getArticleData();
    if ($article) {
        $title = $article->getTitle();
        $imageUrl = $article->getImageUrl() ?: '';
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>썸네일 미리보기 · ThumbnailAgent</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
        }
        .container { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #a8d8ea; }
        .meta { font-size: 0.875rem; color: #888; margin-bottom: 1.5rem; }
        .card {
            background: #16213e;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        .card img {
            width: 100%;
            height: auto;
            display: block;
            min-height: 280px;
            object-fit: cover;
        }
        .card-body { padding: 1.25rem 1.5rem; }
        .card-title { font-size: 1.125rem; line-height: 1.4; margin: 0 0 0.75rem; }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge.ok { background: #0f3460; color: #a8d8ea; }
        .badge.fail { background: #6b2d2d; color: #f4a4a4; }
        .url { font-size: 0.8rem; color: #6a8ea8; word-break: break-all; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>썸네일 미리보기 (일러스트)</h1>
        <p class="meta">Pipeline: ValidationAgent → ThumbnailAgent → … · <?php echo (int)$duration; ?> ms</p>

        <?php if ($success && $imageUrl): ?>
            <div class="card">
                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($title); ?>">
                <div class="card-body">
                    <span class="badge ok">illustration</span>
                    <p class="card-title"><?php echo htmlspecialchars($title); ?></p>
                    <p class="url"><?php echo htmlspecialchars($url); ?></p>
                </div>
            </div>
        <?php elseif ($success): ?>
            <div class="card">
                <div class="card-body">
                    <span class="badge ok">Success</span>
                    <p class="card-title"><?php echo htmlspecialchars($title ?: '(제목 없음)'); ?></p>
                    <p class="meta">썸네일 URL이 비어 있습니다. (fallback 적용 여부 확인)</p>
                    <p class="url"><?php echo htmlspecialchars($url); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <span class="badge fail">실패</span>
                    <p class="card-title"><?php echo htmlspecialchars($error ?: 'Unknown error'); ?></p>
                    <p class="url"><?php echo htmlspecialchars($url); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <p class="meta" style="margin-top: 1.5rem;">저작권 회피용 일러스트 썸네일 · ThumbnailAgent</p>
    </div>
</body>
</html>
