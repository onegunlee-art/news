<?php
/**
 * Pipeline Integration Test
 * 
 * 전체 파이프라인 통합 테스트
 * 
 * @package Agents\Tests
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Tests;

use PHPUnit\Framework\TestCase;
use Agents\Pipeline\AgentPipeline;
use Agents\Models\AgentContext;

class PipelineIntegrationTest extends TestCase
{
    private AgentPipeline $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = new AgentPipeline([
            'openai' => ['mock_mode' => true],
            'enable_interpret' => true,
            'enable_learning' => true,
            'analysis' => ['enable_tts' => false],
            'stop_on_failure' => true
        ]);
        $this->pipeline->setupDefaultPipeline();
    }

    /**
     * 파이프라인 설정 테스트
     */
    public function testSetupDefaultPipeline(): void
    {
        $agentNames = $this->pipeline->getAgentNames();
        
        $this->assertContains('ValidationAgent', $agentNames);
        $this->assertContains('AnalysisAgent', $agentNames);
        $this->assertContains('InterpretAgent', $agentNames);
        $this->assertContains('LearningAgent', $agentNames);
    }

    /**
     * Mock 모드 확인
     */
    public function testMockModeEnabled(): void
    {
        $this->assertTrue($this->pipeline->isMockMode());
    }

    /**
     * Agent 조회 테스트
     */
    public function testGetAgent(): void
    {
        $validation = $this->pipeline->getAgent('ValidationAgent');
        $this->assertNotNull($validation);
        $this->assertEquals('ValidationAgent', $validation->getName());
    }

    /**
     * 존재하지 않는 Agent 조회
     */
    public function testGetNonExistentAgent(): void
    {
        $agent = $this->pipeline->getAgent('NonExistentAgent');
        $this->assertNull($agent);
    }

    /**
     * 유효하지 않은 URL 테스트
     */
    public function testRunWithInvalidUrl(): void
    {
        $result = $this->pipeline->run('not-a-valid-url');
        
        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getError());
        $this->assertStringContainsString('URL', $result->getError());
    }

    /**
     * 빈 URL 테스트
     */
    public function testRunWithEmptyUrl(): void
    {
        $result = $this->pipeline->run('');
        
        $this->assertFalse($result->isSuccess());
    }

    /**
     * 파이프라인 결과 구조 테스트
     */
    public function testPipelineResultStructure(): void
    {
        $result = $this->pipeline->run('https://example.com/article');
        
        // toArray 확인
        $array = $result->toArray();
        
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('error', $array);
        $this->assertArrayHasKey('duration_ms', $array);
        $this->assertArrayHasKey('agents', $array);
        $this->assertArrayHasKey('results', $array);
    }

    /**
     * 개별 Agent 실행 테스트
     */
    public function testRunSingleAgent(): void
    {
        $context = new AgentContext('https://example.com');
        $result = $this->pipeline->runAgent('ValidationAgent', $context);
        
        // 결과는 성공 또는 실패 (네트워크 상태에 따라)
        $this->assertNotNull($result);
    }

    /**
     * 부분 파이프라인 설정 테스트 (Interpret, Learning 비활성화)
     */
    public function testPartialPipeline(): void
    {
        $partialPipeline = new AgentPipeline([
            'openai' => ['mock_mode' => true],
            'enable_interpret' => false,
            'enable_learning' => false,
            'analysis' => ['enable_tts' => false]
        ]);
        $partialPipeline->setupDefaultPipeline();
        
        $agentNames = $partialPipeline->getAgentNames();
        
        $this->assertContains('ValidationAgent', $agentNames);
        $this->assertContains('AnalysisAgent', $agentNames);
        $this->assertNotContains('InterpretAgent', $agentNames);
        $this->assertNotContains('LearningAgent', $agentNames);
    }

    /**
     * JSON 출력 테스트
     */
    public function testResultToJson(): void
    {
        $result = $this->pipeline->run('https://example.com');
        $json = $result->toJson(JSON_PRETTY_PRINT);
        
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
    }

    /**
     * 실행 시간 측정 테스트
     */
    public function testDurationTracking(): void
    {
        $result = $this->pipeline->run('https://example.com');
        $array = $result->toArray();
        
        $this->assertArrayHasKey('duration_ms', $array);
        $this->assertIsNumeric($array['duration_ms']);
    }
}

// 간단한 테스트 러너
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=== Pipeline Integration Tests ===\n\n";
    
    $pipeline = new AgentPipeline([
        'openai' => ['mock_mode' => true],
        'enable_interpret' => true,
        'enable_learning' => true,
        'analysis' => ['enable_tts' => false]
    ]);
    $pipeline->setupDefaultPipeline();
    
    // Test 1: Default pipeline setup
    $agents = $pipeline->getAgentNames();
    $test1 = in_array('ValidationAgent', $agents) && 
             in_array('AnalysisAgent', $agents) &&
             in_array('InterpretAgent', $agents) &&
             in_array('LearningAgent', $agents);
    echo "Test 1 (Default setup): " . ($test1 ? "PASS" : "FAIL") . "\n";
    
    // Test 2: Mock mode
    $test2 = $pipeline->isMockMode();
    echo "Test 2 (Mock mode): " . ($test2 ? "PASS" : "FAIL") . "\n";
    
    // Test 3: Get agent
    $validation = $pipeline->getAgent('ValidationAgent');
    $test3 = $validation !== null && $validation->getName() === 'ValidationAgent';
    echo "Test 3 (Get agent): " . ($test3 ? "PASS" : "FAIL") . "\n";
    
    // Test 4: Invalid URL
    $result = $pipeline->run('not-a-url');
    $test4 = !$result->isSuccess();
    echo "Test 4 (Invalid URL): " . ($test4 ? "PASS" : "FAIL") . "\n";
    
    // Test 5: Result structure
    $result = $pipeline->run('https://example.com');
    $array = $result->toArray();
    $test5 = isset($array['success']) && isset($array['duration_ms']) && isset($array['agents']);
    echo "Test 5 (Result structure): " . ($test5 ? "PASS" : "FAIL") . "\n";
    
    // Test 6: JSON output
    $json = $result->toJson();
    $decoded = json_decode($json, true);
    $test6 = $decoded !== null;
    echo "Test 6 (JSON output): " . ($test6 ? "PASS" : "FAIL") . "\n";
    
    echo "\n=== Tests Complete ===\n";
    
    // 상세 결과 출력
    echo "\n=== Pipeline Result Details ===\n";
    echo $result->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
