<?php
/**
 * Agent System Test Runner
 * 
 * 모든 Agent Unit Test 및 Integration Test 실행
 * 
 * 사용법: php run_tests.php
 * 
 * @package Agents\Tests
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     The Gist - Intelligent News Agent System Test Runner     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Autoload
require_once __DIR__ . '/autoload.php';

use Agents\Services\OpenAIService;
use Agents\Services\WebScraperService;
use Agents\Agents\ValidationAgent;
use Agents\Agents\AnalysisAgent;
use Agents\Agents\InterpretAgent;
use Agents\Agents\LearningAgent;
use Agents\Pipeline\AgentPipeline;
use Agents\Models\AgentContext;
use Agents\Models\ArticleData;
use Agents\Models\AnalysisResult;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest(string $name, callable $test): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    try {
        $result = $test();
        if ($result) {
            $passedTests++;
            echo "  ✓ {$name}\n";
        } else {
            $failedTests++;
            echo "  ✗ {$name} - FAILED\n";
        }
    } catch (Throwable $e) {
        $failedTests++;
        echo "  ✗ {$name} - ERROR: {$e->getMessage()}\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// OpenAIService Tests
// ═══════════════════════════════════════════════════════════════
echo "─────────────────────────────────────────────────────────────\n";
echo "▶ OpenAIService Tests\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest('Mock mode enabled by default', function() {
    $openai = new OpenAIService();
    return $openai->isMockMode() === true;
});

runTest('Chat returns mock response', function() {
    $openai = new OpenAIService(['mock_mode' => true]);
    $response = $openai->chat('System prompt', 'User message');
    return !empty($response);
});

runTest('TTS returns mock URL', function() {
    $openai = new OpenAIService(['mock_mode' => true]);
    $url = $openai->textToSpeech('Test text');
    return !empty($url) && strpos($url, 'mock') !== false;
});

runTest('Embedding returns vector', function() {
    $openai = new OpenAIService(['mock_mode' => true]);
    $embedding = $openai->createEmbedding('Test text');
    return is_array($embedding) && count($embedding) > 0;
});

// ═══════════════════════════════════════════════════════════════
// ValidationAgent Tests
// ═══════════════════════════════════════════════════════════════
echo "\n─────────────────────────────────────────────────────────────\n";
echo "▶ ValidationAgent Tests\n";
echo "─────────────────────────────────────────────────────────────\n";

$openai = new OpenAIService(['mock_mode' => true]);
$validationAgent = new ValidationAgent($openai, null, [
    'blocked_domains' => ['localhost', '127.0.0.1']
]);

runTest('Agent name is ValidationAgent', function() use ($validationAgent) {
    return $validationAgent->getName() === 'ValidationAgent';
});

runTest('Valid URL passes validation', function() use ($validationAgent) {
    return $validationAgent->validate('https://example.com/article');
});

runTest('Invalid URL fails validation', function() use ($validationAgent) {
    return !$validationAgent->validate('not-a-url');
});

runTest('Empty URL fails validation', function() use ($validationAgent) {
    return !$validationAgent->validate('');
});

runTest('Localhost blocked', function() use ($validationAgent) {
    return !$validationAgent->validate('http://localhost/admin');
});

runTest('Initialize works', function() use ($validationAgent) {
    $validationAgent->initialize();
    return $validationAgent->isReady();
});

// ═══════════════════════════════════════════════════════════════
// AnalysisAgent Tests
// ═══════════════════════════════════════════════════════════════
echo "\n─────────────────────────────────────────────────────────────\n";
echo "▶ AnalysisAgent Tests\n";
echo "─────────────────────────────────────────────────────────────\n";

$analysisAgent = new AnalysisAgent($openai, ['enable_tts' => false]);

runTest('Agent name is AnalysisAgent', function() use ($analysisAgent) {
    return $analysisAgent->getName() === 'AnalysisAgent';
});

runTest('Valid ArticleData passes', function() use ($analysisAgent) {
    $article = new ArticleData(
        url: 'https://example.com',
        title: 'Test',
        content: 'Content here'
    );
    return $analysisAgent->validate($article);
});

runTest('Empty content fails', function() use ($analysisAgent) {
    $article = new ArticleData(
        url: 'https://example.com',
        title: 'Test',
        content: ''
    );
    return !$analysisAgent->validate($article);
});

runTest('Initialize works', function() use ($analysisAgent) {
    $analysisAgent->initialize();
    return $analysisAgent->isReady();
});

runTest('Translate returns string', function() use ($analysisAgent) {
    $result = $analysisAgent->translate('Hello world');
    return is_string($result) && !empty($result);
});

// ═══════════════════════════════════════════════════════════════
// InterpretAgent Tests
// ═══════════════════════════════════════════════════════════════
echo "\n─────────────────────────────────────────────────────────────\n";
echo "▶ InterpretAgent Tests\n";
echo "─────────────────────────────────────────────────────────────\n";

$interpretAgent = new InterpretAgent($openai, [
    'relevance_threshold' => 0.6,
    'top_k' => 3
]);

runTest('Agent name is InterpretAgent', function() use ($interpretAgent) {
    return $interpretAgent->getName() === 'InterpretAgent';
});

runTest('Valid query passes', function() use ($interpretAgent) {
    return $interpretAgent->validate('한국 경제 분석');
});

runTest('Empty query fails', function() use ($interpretAgent) {
    return !$interpretAgent->validate('');
});

runTest('Initialize works', function() use ($interpretAgent) {
    $interpretAgent->initialize();
    return $interpretAgent->isReady();
});

runTest('Load knowledge base works', function() use ($interpretAgent) {
    $docs = ['doc1' => '외교 정책 패턴', 'doc2' => '경제 분석'];
    $interpretAgent->loadKnowledgeBase($docs);
    return true; // No exception means success
});

// ═══════════════════════════════════════════════════════════════
// LearningAgent Tests
// ═══════════════════════════════════════════════════════════════
echo "\n─────────────────────────────────────────────────────────────\n";
echo "▶ LearningAgent Tests\n";
echo "─────────────────────────────────────────────────────────────\n";

$tempPath = sys_get_temp_dir() . '/learning_test_' . uniqid();
mkdir($tempPath, 0755, true);

$learningAgent = new LearningAgent($openai, ['storage_path' => $tempPath]);

runTest('Agent name is LearningAgent', function() use ($learningAgent) {
    return $learningAgent->getName() === 'LearningAgent';
});

runTest('Long text passes validation', function() use ($learningAgent) {
    $text = str_repeat('테스트 문장입니다. ', 10);
    return $learningAgent->validate($text);
});

runTest('Short text fails validation', function() use ($learningAgent) {
    return !$learningAgent->validate('짧음');
});

runTest('Initialize works', function() use ($learningAgent) {
    $learningAgent->initialize();
    return $learningAgent->isReady();
});

runTest('Add sample and learn', function() use ($learningAgent) {
    $learningAgent->addSampleText('샘플 텍스트입니다. 충분히 긴 내용이 필요합니다.');
    $patterns = $learningAgent->learn();
    return is_array($patterns) && isset($patterns['style']);
});

runTest('Has learned patterns', function() use ($learningAgent) {
    return $learningAgent->hasLearnedPatterns();
});

runTest('Reset patterns', function() use ($learningAgent) {
    $learningAgent->resetPatterns();
    return !$learningAgent->hasLearnedPatterns();
});

// Cleanup temp directory
array_map('unlink', glob("$tempPath/*"));
rmdir($tempPath);

// ═══════════════════════════════════════════════════════════════
// Pipeline Integration Tests
// ═══════════════════════════════════════════════════════════════
echo "\n─────────────────────────────────────────────────────────────\n";
echo "▶ Pipeline Integration Tests\n";
echo "─────────────────────────────────────────────────────────────\n";

$pipeline = new AgentPipeline([
    'openai' => ['mock_mode' => true],
    'enable_interpret' => true,
    'enable_learning' => true,
    'analysis' => ['enable_tts' => false]
]);
$pipeline->setupDefaultPipeline();

runTest('Default pipeline has all agents', function() use ($pipeline) {
    $agents = $pipeline->getAgentNames();
    return in_array('ValidationAgent', $agents) &&
           in_array('AnalysisAgent', $agents) &&
           in_array('InterpretAgent', $agents) &&
           in_array('LearningAgent', $agents);
});

runTest('Mock mode enabled', function() use ($pipeline) {
    return $pipeline->isMockMode();
});

runTest('Get agent by name', function() use ($pipeline) {
    $agent = $pipeline->getAgent('ValidationAgent');
    return $agent !== null && $agent->getName() === 'ValidationAgent';
});

runTest('Invalid URL fails pipeline', function() use ($pipeline) {
    $result = $pipeline->run('not-a-url');
    return !$result->isSuccess();
});

runTest('Result has proper structure', function() use ($pipeline) {
    $result = $pipeline->run('https://example.com');
    $array = $result->toArray();
    return isset($array['success']) && isset($array['duration_ms']) && isset($array['agents']);
});

runTest('JSON output is valid', function() use ($pipeline) {
    $result = $pipeline->run('https://example.com');
    $json = $result->toJson();
    $decoded = json_decode($json, true);
    return $decoded !== null;
});

// ═══════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════
echo "\n═══════════════════════════════════════════════════════════════\n";
echo "                        TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

echo "  Total Tests:  {$totalTests}\n";
echo "  Passed:       {$passedTests} ✓\n";
echo "  Failed:       {$failedTests} ✗\n";
echo "  Pass Rate:    {$percentage}%\n";
echo "\n";

if ($failedTests === 0) {
    echo "  ╔════════════════════════════════════════════════════════╗\n";
    echo "  ║                  ALL TESTS PASSED!                     ║\n";
    echo "  ╚════════════════════════════════════════════════════════╝\n";
} else {
    echo "  ╔════════════════════════════════════════════════════════╗\n";
    echo "  ║              SOME TESTS FAILED ({$failedTests} failures)           ║\n";
    echo "  ╚════════════════════════════════════════════════════════╝\n";
}

echo "\n";
exit($failedTests > 0 ? 1 : 0);
