<?php
/**
 * AnalysisAgent Unit Test
 * 
 * @package Agents\Tests
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Tests;

use PHPUnit\Framework\TestCase;
use Agents\Agents\AnalysisAgent;
use Agents\Services\OpenAIService;
use Agents\Models\AgentContext;
use Agents\Models\ArticleData;

class AnalysisAgentTest extends TestCase
{
    private AnalysisAgent $agent;
    private OpenAIService $openai;

    protected function setUp(): void
    {
        $this->openai = new OpenAIService(['mock_mode' => true]);
        $this->agent = new AnalysisAgent($this->openai, [
            'summary_length' => 3,
            'key_points_count' => 3,
            'enable_tts' => false // 테스트에서는 TTS 비활성화
        ]);
    }

    /**
     * Agent 이름 테스트
     */
    public function testGetName(): void
    {
        $this->assertEquals('AnalysisAgent', $this->agent->getName());
    }

    /**
     * ArticleData 유효성 검증 테스트
     */
    public function testValidateWithArticleData(): void
    {
        $validArticle = new ArticleData(
            url: 'https://example.com/article',
            title: 'Test Article',
            content: 'This is the article content that is long enough for analysis.'
        );
        
        $this->assertTrue($this->agent->validate($validArticle));
    }

    /**
     * 빈 콘텐츠 검증 테스트
     */
    public function testValidateEmptyContent(): void
    {
        $emptyArticle = new ArticleData(
            url: 'https://example.com/article',
            title: 'Test Article',
            content: ''
        );
        
        $this->assertFalse($this->agent->validate($emptyArticle));
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
     * 정상 처리 테스트 (Mock 모드)
     */
    public function testProcessWithMockData(): void
    {
        $article = new ArticleData(
            url: 'https://example.com/news/12345',
            title: 'Breaking: Major Tech Company Announces New AI Product',
            content: 'A major technology company has announced a groundbreaking new artificial intelligence product that promises to revolutionize the industry. The product, which has been in development for over three years, combines advanced machine learning algorithms with natural language processing capabilities. Industry experts predict this will have significant implications for businesses worldwide.',
            language: 'en'
        );

        $context = new AgentContext('https://example.com/news/12345');
        // 수동으로 articleData 설정 (ValidationAgent 없이 테스트)
        $reflection = new \ReflectionClass($context);
        $property = $reflection->getProperty('articleData');
        $property->setAccessible(true);
        $property->setValue($context, $article);

        $result = $this->agent->process($context);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('translation_summary', $result->getData());
        $this->assertArrayHasKey('key_points', $result->getData());
        $this->assertArrayHasKey('critical_analysis', $result->getData());
    }

    /**
     * ArticleData 없이 처리 시 실패 테스트
     */
    public function testProcessWithoutArticleData(): void
    {
        $context = new AgentContext('https://example.com');
        $result = $this->agent->process($context);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('기사 데이터가 없습니다', $result->getFirstError());
    }

    /**
     * 개별 번역 기능 테스트
     */
    public function testTranslate(): void
    {
        $text = "This is a test sentence for translation.";
        $result = $this->agent->translate($text);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * 개별 요약 기능 테스트
     */
    public function testSummarize(): void
    {
        $text = "This is a long text that needs to be summarized. It contains multiple sentences and paragraphs with various information about different topics.";
        $result = $this->agent->summarize($text, 2);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * 설정 테스트
     */
    public function testGetConfig(): void
    {
        $config = $this->agent->getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('temperature', $config);
    }
}

// 간단한 테스트 러너
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=== AnalysisAgent Unit Tests ===\n\n";
    
    $openai = new OpenAIService(['mock_mode' => true]);
    $agent = new AnalysisAgent($openai, [
        'enable_tts' => false
    ]);
    
    // Test 1: Agent name
    $test1 = $agent->getName() === 'AnalysisAgent';
    echo "Test 1 (Agent name): " . ($test1 ? "PASS" : "FAIL") . "\n";
    
    // Test 2: Valid ArticleData
    $article = new ArticleData(
        url: 'https://example.com',
        title: 'Test',
        content: 'Content here'
    );
    $test2 = $agent->validate($article);
    echo "Test 2 (Valid ArticleData): " . ($test2 ? "PASS" : "FAIL") . "\n";
    
    // Test 3: Empty content
    $emptyArticle = new ArticleData(
        url: 'https://example.com',
        title: 'Test',
        content: ''
    );
    $test3 = !$agent->validate($emptyArticle);
    echo "Test 3 (Empty content): " . ($test3 ? "PASS" : "FAIL") . "\n";
    
    // Test 4: Initialize
    $agent->initialize();
    $test4 = $agent->isReady();
    echo "Test 4 (Initialize): " . ($test4 ? "PASS" : "FAIL") . "\n";
    
    // Test 5: Translate (Mock)
    $translation = $agent->translate("Hello world");
    $test5 = !empty($translation);
    echo "Test 5 (Translate mock): " . ($test5 ? "PASS" : "FAIL") . "\n";
    
    echo "\n=== Tests Complete ===\n";
}
