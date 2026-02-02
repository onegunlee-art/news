<?php
/**
 * LearningAgent Unit Test
 * 
 * @package Agents\Tests
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Tests;

use PHPUnit\Framework\TestCase;
use Agents\Agents\LearningAgent;
use Agents\Services\OpenAIService;
use Agents\Models\AgentContext;
use Agents\Models\AnalysisResult;

class LearningAgentTest extends TestCase
{
    private LearningAgent $agent;
    private OpenAIService $openai;
    private string $testStoragePath;

    protected function setUp(): void
    {
        $this->testStoragePath = sys_get_temp_dir() . '/learning_test_' . uniqid();
        mkdir($this->testStoragePath, 0755, true);

        $this->openai = new OpenAIService(['mock_mode' => true]);
        $this->agent = new LearningAgent($this->openai, [
            'storage_path' => $this->testStoragePath
        ]);
    }

    protected function tearDown(): void
    {
        // 테스트 디렉토리 정리
        $files = glob($this->testStoragePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->testStoragePath);
    }

    /**
     * Agent 이름 테스트
     */
    public function testGetName(): void
    {
        $this->assertEquals('LearningAgent', $this->agent->getName());
    }

    /**
     * 유효한 입력 검증 테스트
     */
    public function testValidateValidInput(): void
    {
        $longText = str_repeat('테스트 문장입니다. ', 10);
        $this->assertTrue($this->agent->validate($longText));
    }

    /**
     * 짧은 입력 검증 테스트
     */
    public function testValidateShortInput(): void
    {
        $this->assertFalse($this->agent->validate('짧은 텍스트'));
    }

    /**
     * 빈 입력 검증 테스트
     */
    public function testValidateEmptyInput(): void
    {
        $this->assertFalse($this->agent->validate(''));
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
     * 샘플 텍스트 추가 테스트
     */
    public function testAddSampleText(): void
    {
        $sampleText = "이것은 테스트용 샘플 텍스트입니다. 충분히 길어야 합니다. " . 
                      "글쓰기 스타일 분석을 위해서는 더 많은 내용이 필요합니다.";
        
        $this->agent->addSampleText($sampleText, ['type' => 'test']);
        
        // 에러 없이 추가되면 성공
        $this->assertTrue(true);
    }

    /**
     * 패턴 학습 테스트
     */
    public function testLearn(): void
    {
        $sampleText1 = "첫 번째 샘플 텍스트입니다. 글쓰기 스타일을 분석합니다. 주로 분석적인 어조를 사용합니다.";
        $sampleText2 = "두 번째 샘플입니다. 비슷한 패턴으로 작성됩니다. 핵심을 먼저 제시하고 설명합니다.";

        $this->agent->addSampleText($sampleText1);
        $this->agent->addSampleText($sampleText2);

        $patterns = $this->agent->learn();

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('style', $patterns);
    }

    /**
     * 학습된 패턴 저장/로드 테스트
     */
    public function testPatternPersistence(): void
    {
        $sampleText = "테스트용 샘플입니다. 패턴 저장 테스트를 위한 충분히 긴 텍스트가 필요합니다.";
        
        $this->agent->addSampleText($sampleText);
        $this->agent->learn();

        // 새 인스턴스 생성
        $newAgent = new LearningAgent($this->openai, [
            'storage_path' => $this->testStoragePath
        ]);
        $newAgent->initialize();

        // 패턴이 로드되었는지 확인
        $this->assertTrue($newAgent->hasLearnedPatterns());
    }

    /**
     * 패턴 초기화 테스트
     */
    public function testResetPatterns(): void
    {
        $this->agent->addSampleText("샘플 텍스트입니다. 충분히 길어야 합니다. 테스트용입니다.");
        $this->agent->learn();
        
        $this->assertTrue($this->agent->hasLearnedPatterns());
        
        $this->agent->resetPatterns();
        
        $this->assertFalse($this->agent->hasLearnedPatterns());
    }

    /**
     * AnalysisResult 없이 처리 시 실패 테스트
     */
    public function testProcessWithoutAnalysisResult(): void
    {
        $context = new AgentContext('https://example.com');
        $result = $this->agent->process($context);

        $this->assertFalse($result->isSuccess());
    }

    /**
     * 학습된 패턴 없이 처리 테스트
     */
    public function testProcessWithoutLearnedPatterns(): void
    {
        $analysisResult = new AnalysisResult(
            translationSummary: '테스트 요약입니다.',
            keyPoints: ['포인트 1', '포인트 2'],
            criticalAnalysis: [
                'why_important' => '중요성 설명',
                'future_prediction' => '미래 전망'
            ]
        );

        $context = new AgentContext('https://example.com');
        
        // AnalysisResult 설정
        $reflection = new \ReflectionClass($context);
        $property = $reflection->getProperty('analysisResult');
        $property->setAccessible(true);
        $property->setValue($context, $analysisResult);

        $result = $this->agent->process($context);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->getData()['styled']);
    }
}

// 간단한 테스트 러너
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=== LearningAgent Unit Tests ===\n\n";
    
    $tempPath = sys_get_temp_dir() . '/learning_test_' . uniqid();
    mkdir($tempPath, 0755, true);
    
    $openai = new OpenAIService(['mock_mode' => true]);
    $agent = new LearningAgent($openai, [
        'storage_path' => $tempPath
    ]);
    
    // Test 1: Agent name
    $test1 = $agent->getName() === 'LearningAgent';
    echo "Test 1 (Agent name): " . ($test1 ? "PASS" : "FAIL") . "\n";
    
    // Test 2: Valid input
    $longText = str_repeat('테스트 문장입니다. ', 10);
    $test2 = $agent->validate($longText);
    echo "Test 2 (Valid input): " . ($test2 ? "PASS" : "FAIL") . "\n";
    
    // Test 3: Short input
    $test3 = !$agent->validate('짧음');
    echo "Test 3 (Short input): " . ($test3 ? "PASS" : "FAIL") . "\n";
    
    // Test 4: Initialize
    $agent->initialize();
    $test4 = $agent->isReady();
    echo "Test 4 (Initialize): " . ($test4 ? "PASS" : "FAIL") . "\n";
    
    // Test 5: Add sample and learn
    $agent->addSampleText("샘플 텍스트입니다. 충분히 긴 내용이 필요합니다. 테스트를 위한 것입니다.");
    $patterns = $agent->learn();
    $test5 = is_array($patterns) && isset($patterns['style']);
    echo "Test 5 (Learn): " . ($test5 ? "PASS" : "FAIL") . "\n";
    
    // Test 6: Has patterns
    $test6 = $agent->hasLearnedPatterns();
    echo "Test 6 (Has patterns): " . ($test6 ? "PASS" : "FAIL") . "\n";
    
    // Test 7: Reset
    $agent->resetPatterns();
    $test7 = !$agent->hasLearnedPatterns();
    echo "Test 7 (Reset): " . ($test7 ? "PASS" : "FAIL") . "\n";
    
    // Cleanup
    $files = glob($tempPath . '/*');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($tempPath);
    
    echo "\n=== Tests Complete ===\n";
}
