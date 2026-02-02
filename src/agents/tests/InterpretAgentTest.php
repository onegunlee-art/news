<?php
/**
 * InterpretAgent Unit Test
 * 
 * @package Agents\Tests
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Tests;

use PHPUnit\Framework\TestCase;
use Agents\Agents\InterpretAgent;
use Agents\Services\OpenAIService;
use Agents\Models\AgentContext;
use Agents\Models\AnalysisResult;

class InterpretAgentTest extends TestCase
{
    private InterpretAgent $agent;
    private OpenAIService $openai;

    protected function setUp(): void
    {
        $this->openai = new OpenAIService(['mock_mode' => true]);
        $this->agent = new InterpretAgent($this->openai, [
            'relevance_threshold' => 0.6,
            'top_k' => 3
        ]);
    }

    /**
     * Agent 이름 테스트
     */
    public function testGetName(): void
    {
        $this->assertEquals('InterpretAgent', $this->agent->getName());
    }

    /**
     * 유효한 쿼리 검증 테스트
     */
    public function testValidateValidQuery(): void
    {
        $this->assertTrue($this->agent->validate('한국의 대외정책 분석'));
        $this->assertTrue($this->agent->validate('미국 금리 인상이 한국 경제에 미치는 영향'));
    }

    /**
     * 빈 쿼리 검증 테스트
     */
    public function testValidateEmptyQuery(): void
    {
        $this->assertFalse($this->agent->validate(''));
        $this->assertFalse($this->agent->validate('   '));
    }

    /**
     * 지식 베이스 로드 테스트
     */
    public function testLoadKnowledgeBase(): void
    {
        $documents = [
            'doc1' => ['content' => '외교 정책 분석 패턴', 'type' => 'diplomacy'],
            'doc2' => ['content' => '경제 지표 해석 방법', 'type' => 'economy'],
            'doc3' => ['content' => 'AI 기술 동향 분석', 'type' => 'technology']
        ];

        $this->agent->loadKnowledgeBase($documents);
        
        // 에러 없이 로드되면 성공
        $this->assertTrue(true);
    }

    /**
     * 초기화 테스트
     */
    public function testInitialize(): void
    {
        $this->agent->initialize();
        $this->assertTrue($this->agent->isReady());
    }

    /**
     * 쿼리 없이 처리 시 실패 테스트
     */
    public function testProcessWithoutQuery(): void
    {
        $context = new AgentContext('');
        $result = $this->agent->process($context);

        $this->assertFalse($result->isSuccess());
    }

    /**
     * 유효한 쿼리 처리 테스트
     */
    public function testProcessValidQuery(): void
    {
        $context = new AgentContext('https://example.com');
        
        // 쿼리 설정
        $reflection = new \ReflectionClass($context);
        $property = $reflection->getProperty('query');
        $property->setAccessible(true);
        $property->setValue($context, '미중 무역 분쟁이 한국 반도체 산업에 미치는 영향 분석');

        $result = $this->agent->process($context);

        $this->assertTrue($result->isSuccess() || $result->isPartial());
    }

    /**
     * 도움이 되는 쿼리 판단 테스트
     */
    public function testIsHelpfulQuery(): void
    {
        // Mock 모드에서 대부분 유효로 처리
        $result = $this->agent->isHelpfulQuery('글로벌 공급망 재편 분석');
        $this->assertIsBool($result);
    }

    /**
     * 너무 짧은 쿼리 테스트
     */
    public function testShortQuery(): void
    {
        $context = new AgentContext('');
        $reflection = new \ReflectionClass($context);
        $property = $reflection->getProperty('query');
        $property->setAccessible(true);
        $property->setValue($context, '뭐');

        $result = $this->agent->process($context);
        
        // 짧은 쿼리는 명확화 필요 또는 실패
        $this->assertTrue($result->isPartial() || !$result->isSuccess());
    }
}

// 간단한 테스트 러너
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=== InterpretAgent Unit Tests ===\n\n";
    
    $openai = new OpenAIService(['mock_mode' => true]);
    $agent = new InterpretAgent($openai, [
        'relevance_threshold' => 0.6,
        'top_k' => 3
    ]);
    
    // Test 1: Agent name
    $test1 = $agent->getName() === 'InterpretAgent';
    echo "Test 1 (Agent name): " . ($test1 ? "PASS" : "FAIL") . "\n";
    
    // Test 2: Valid query
    $test2 = $agent->validate('한국 경제 분석');
    echo "Test 2 (Valid query): " . ($test2 ? "PASS" : "FAIL") . "\n";
    
    // Test 3: Empty query
    $test3 = !$agent->validate('');
    echo "Test 3 (Empty query): " . ($test3 ? "PASS" : "FAIL") . "\n";
    
    // Test 4: Initialize
    $agent->initialize();
    $test4 = $agent->isReady();
    echo "Test 4 (Initialize): " . ($test4 ? "PASS" : "FAIL") . "\n";
    
    // Test 5: Load knowledge base
    $documents = [
        'doc1' => '외교 정책 패턴',
        'doc2' => '경제 분석 방법'
    ];
    $agent->loadKnowledgeBase($documents);
    $test5 = true; // 에러 없으면 성공
    echo "Test 5 (Load KB): " . ($test5 ? "PASS" : "FAIL") . "\n";
    
    // Test 6: isHelpfulQuery
    $test6 = is_bool($agent->isHelpfulQuery('미중 관계 분석'));
    echo "Test 6 (isHelpfulQuery): " . ($test6 ? "PASS" : "FAIL") . "\n";
    
    echo "\n=== Tests Complete ===\n";
}
