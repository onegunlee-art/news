<?php
/**
 * ValidationAgent Unit Test
 * 
 * @package Agents\Tests
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Tests;

use PHPUnit\Framework\TestCase;
use Agents\Agents\ValidationAgent;
use Agents\Services\OpenAIService;
use Agents\Services\WebScraperService;
use Agents\Models\AgentContext;

class ValidationAgentTest extends TestCase
{
    private ValidationAgent $agent;
    private OpenAIService $openai;

    protected function setUp(): void
    {
        // Mock 모드로 OpenAI 서비스 생성
        $this->openai = new OpenAIService(['mock_mode' => true]);
        $this->agent = new ValidationAgent($this->openai, null, [
            'blocked_domains' => ['localhost', '127.0.0.1'],
            'min_content_length' => 50
        ]);
    }

    /**
     * 유효한 URL 형식 테스트
     */
    public function testValidateValidUrl(): void
    {
        $this->assertTrue($this->agent->validate('https://example.com/article'));
        $this->assertTrue($this->agent->validate('http://news.example.org/story/123'));
    }

    /**
     * 유효하지 않은 URL 형식 테스트
     */
    public function testValidateInvalidUrl(): void
    {
        $this->assertFalse($this->agent->validate(''));
        $this->assertFalse($this->agent->validate('not-a-url'));
        $this->assertFalse($this->agent->validate('ftp://example.com'));
        $this->assertFalse($this->agent->validate('http://localhost/admin'));
    }

    /**
     * Agent 이름 테스트
     */
    public function testGetName(): void
    {
        $this->assertEquals('ValidationAgent', $this->agent->getName());
    }

    /**
     * Agent 설정 테스트
     */
    public function testGetConfig(): void
    {
        $config = $this->agent->getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('max_retries', $config);
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
     * 차단된 도메인 테스트
     */
    public function testBlockedDomains(): void
    {
        $this->assertFalse($this->agent->validate('http://localhost/article'));
        $this->assertFalse($this->agent->validate('http://127.0.0.1/article'));
    }

    /**
     * 정상 처리 테스트 (실제 URL 접근 없이)
     */
    public function testProcessWithMockData(): void
    {
        // 이 테스트는 실제 네트워크 요청을 하므로
        // Mock Scraper가 필요한 경우 별도 구현
        $this->markTestSkipped('실제 URL 테스트는 통합 테스트에서 수행');
    }

    /**
     * 빈 URL 처리 테스트
     */
    public function testProcessEmptyUrl(): void
    {
        $context = new AgentContext('');
        $result = $this->agent->process($context);
        
        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getFirstError());
    }

    /**
     * 잘못된 URL 형식 처리 테스트
     */
    public function testProcessInvalidUrlFormat(): void
    {
        $context = new AgentContext('not-a-valid-url');
        $result = $this->agent->process($context);
        
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('유효하지 않은 URL', $result->getFirstError());
    }
}

// 간단한 테스트 러너 (PHPUnit 없이도 실행 가능)
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=== ValidationAgent Unit Tests ===\n\n";
    
    $openai = new OpenAIService(['mock_mode' => true]);
    $agent = new ValidationAgent($openai, null, [
        'blocked_domains' => ['localhost', '127.0.0.1'],
        'min_content_length' => 50
    ]);
    
    // Test 1: Valid URL
    $test1 = $agent->validate('https://example.com/article');
    echo "Test 1 (Valid URL): " . ($test1 ? "PASS" : "FAIL") . "\n";
    
    // Test 2: Invalid URL
    $test2 = !$agent->validate('not-a-url');
    echo "Test 2 (Invalid URL): " . ($test2 ? "PASS" : "FAIL") . "\n";
    
    // Test 3: Blocked domain
    $test3 = !$agent->validate('http://localhost/admin');
    echo "Test 3 (Blocked domain): " . ($test3 ? "PASS" : "FAIL") . "\n";
    
    // Test 4: Empty URL
    $test4 = !$agent->validate('');
    echo "Test 4 (Empty URL): " . ($test4 ? "PASS" : "FAIL") . "\n";
    
    // Test 5: Agent name
    $test5 = $agent->getName() === 'ValidationAgent';
    echo "Test 5 (Agent name): " . ($test5 ? "PASS" : "FAIL") . "\n";
    
    echo "\n=== Tests Complete ===\n";
}
