<?php
declare(strict_types=1);

/**
 * Smoke test for multi-agent discovery pipeline (no LLM/DB required).
 * Usage: php tools/discovery_multi_agent_smoke.php
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);

$errors = [];

// 1. Config loads
$agentConfig = discoveryAgentsConfig($root);
if (!isset($agentConfig['agents']['briefer'])) {
    $errors[] = 'discovery_agents.php missing briefer config';
}

// 2. Classes autoload
$classes = [
    Discovery\Agents\Contracts\DiscoveryAgentInterface::class,
    Discovery\Agents\Contracts\DiscoveryAgentResult::class,
    Discovery\Agents\LLMClient::class,
    Discovery\Agents\ExtractorAgent::class,
    Discovery\Agents\BrieferAgent::class,
    Discovery\Agents\Pipeline::class,
    Discovery\Agents\Extractors\GenericExtractor::class,
    Discovery\Agents\Extractors\BBCExtractor::class,
    Discovery\Agents\Extractors\GuardianExtractor::class,
];
foreach ($classes as $class) {
    if (!class_exists($class) && !interface_exists($class)) {
        $errors[] = "Class not found: {$class}";
    }
}

// 3. LLMClient JSON parse
try {
    $parsed = Discovery\Agents\LLMClient::parseJsonFromText('{"changes":[{"title":"test"}]}');
    if (($parsed['changes'][0]['title'] ?? '') !== 'test') {
        $errors[] = 'LLMClient JSON parse failed';
    }
} catch (Throwable $e) {
    $errors[] = 'LLMClient JSON parse exception: ' . $e->getMessage();
}

// 4. ExtractorAgent with empty input
$extractor = new Discovery\Agents\ExtractorAgent();
$result = $extractor->process(['articles' => []], $agentConfig);
if ($result->inputCount !== 0 || $result->outputCount !== 0) {
    $errors[] = 'ExtractorAgent empty input test failed';
}

// 5. DiscoveryAgentResult DTO
$dto = new Discovery\Agents\Contracts\DiscoveryAgentResult([], 0, 0);
if ($dto->outputCount !== 0) {
    $errors[] = 'DiscoveryAgentResult DTO failed';
}

echo "=== Discovery Multi-Agent Smoke Test ===\n";
if ($errors === []) {
    echo "PASS: all checks OK\n";
    echo 'agents=' . implode(', ', array_keys($agentConfig['agents'] ?? [])) . "\n";
    exit(0);
}

foreach ($errors as $err) {
    echo "FAIL: {$err}\n";
}
exit(1);
