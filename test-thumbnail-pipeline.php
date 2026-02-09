<?php
/**
 * ThumbnailAgent 파이프라인 테스트 (CLI)
 * 사용: php test-thumbnail-pipeline.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = __DIR__;
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

echo "Running pipeline for: " . $url . "\n";
echo "Agents: " . implode(' -> ', $pipeline->getAgentNames()) . "\n\n";

$start = microtime(true);
$result = $pipeline->run($url);
$duration = round((microtime(true) - $start) * 1000);

echo "Success: " . ($result->isSuccess() ? 'YES' : 'NO') . "\n";
echo "Duration: {$duration} ms\n\n";

if ($result->isSuccess()) {
    $article = $result->context?->getArticleData();
    if ($article) {
        echo "Article title: " . mb_substr($article->getTitle(), 0, 80) . "...\n";
        echo "Thumbnail URL (illustration): " . ($article->getImageUrl() ?: '(null)') . "\n";
    }
    $thumbResult = $result->getAgentResult('ThumbnailAgent');
    if ($thumbResult && $thumbResult->isSuccess()) {
        $data = $thumbResult->getData();
        echo "ThumbnailAgent style: " . ($data['thumbnail']['style'] ?? '') . "\n";
    }
} else {
    echo "Error: " . $result->getError() . "\n";
}

echo "\nDone.\n";
