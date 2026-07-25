<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/discovery/bootstrap.php';

echo "=== Discovery Smoke Test ===\n";

// 1) Feature flag off
putenv('ENABLE_DISCOVERY=false');
$_ENV['ENABLE_DISCOVERY'] = 'false';
$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);
$config = discoveryConfig($root);
echo '[isolation] enabled=' . ($config['enabled'] ? 'true' : 'false') . "\n";
if ($config['enabled']) {
    fwrite(STDERR, "FAIL: expected enabled=false\n");
    exit(1);
}

try {
    discoveryEnsureEnabled($root);
    fwrite(STDERR, "FAIL: discoveryEnsureEnabled should throw when disabled\n");
    exit(1);
} catch (Throwable $e) {
    echo '[isolation] ensureEnabled blocked: ' . $e->getMessage() . "\n";
}

// 2) Source verifier (invalid URL path — fast local check)
$verifier = new Discovery\SourceVerifier(5);
$sources = $verifier->verify([
    ['name' => 'Bad', 'url' => 'not-a-url', 'article_title' => ''],
], 'test title');
$verifiedCount = count(array_filter($sources, static fn($s) => !empty($s['verified'])));
echo "[verify] invalid url rejected, verified={$verifiedCount}\n";
if ($verifiedCount !== 0) {
    fwrite(STDERR, "FAIL: invalid url should not verify\n");
    exit(1);
}

// 3) Mock LLM (no API key)
$llm = new Discovery\DiscoveryLLMClient(['model' => 'gpt-4o', 'api_key' => '']);
$result = $llm->generateDailyChanges(date('Y-m-d'), discoveryConfig($root));
echo '[mock] changes=' . count($result['changes']) . "\n";

if (count($result['changes']) < 1) {
    fwrite(STDERR, "FAIL: mock should return at least 1 change\n");
    exit(1);
}

echo "OK\n";
