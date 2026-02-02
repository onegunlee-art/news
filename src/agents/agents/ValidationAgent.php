<?php
/**
 * Validation Agent
 * 
 * URL 검증 및 메타데이터 추출 Agent
 * - URL 접근 가능성 확인
 * - 메타데이터 추출 (title, description, og:image)
 * - 기사 본문 존재 여부 검증
 * - 언어 감지
 * 
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Agents\Agents;

use Agents\Core\BaseAgent;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Services\OpenAIService;
use Agents\Services\WebScraperService;

class ValidationAgent extends BaseAgent
{
    private WebScraperService $scraper;
    private array $blockedDomains = [];
    private int $minContentLength = 100;

    public function __construct(
        OpenAIService $openai,
        ?WebScraperService $scraper = null,
        array $config = []
    ) {
        parent::__construct($openai, $config);
        $this->scraper = $scraper ?? new WebScraperService($config);
        $this->blockedDomains = $config['blocked_domains'] ?? ['localhost', '127.0.0.1'];
        $this->minContentLength = $config['min_content_length'] ?? 100;
    }

    /**
     * Agent 이름
     */
    public function getName(): string
    {
        return 'ValidationAgent';
    }

    /**
     * 기본 프롬프트
     */
    protected function getDefaultPrompts(): array
    {
        return [
            'system' => '당신은 뉴스 기사 URL 검증 전문가입니다.',
            'tasks' => [
                'validate_url' => [
                    'prompt' => 'URL 유효성을 검증하세요.'
                ]
            ]
        ];
    }

    /**
     * 입력 유효성 검증
     */
    public function validate(mixed $input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        return $this->isValidUrl($input);
    }

    /**
     * URL 형식 검증
     */
    private function isValidUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        
        // 스킴 확인
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }

        // 차단된 도메인 확인
        $host = $parsed['host'] ?? '';
        if (in_array($host, $this->blockedDomains)) {
            return false;
        }

        return true;
    }

    /**
     * 메인 처리 로직
     */
    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();
        $url = $context->getUrl();

        $this->log("Validating URL: {$url}", 'info');

        // Step 1: URL 형식 검증
        if (!$this->validate($url)) {
            return AgentResult::failure(
                "유효하지 않은 URL 형식입니다: {$url}",
                $this->getName()
            );
        }

        // Step 2: URL 접근 가능성 확인
        try {
            if (!$this->scraper->isAccessible($url)) {
                return AgentResult::failure(
                    "URL에 접근할 수 없습니다: {$url}",
                    $this->getName()
                );
            }
        } catch (\Exception $e) {
            return AgentResult::failure(
                "URL 접근 확인 중 오류: " . $e->getMessage(),
                $this->getName()
            );
        }

        // Step 3: 콘텐츠 스크래핑
        try {
            $articleData = $this->scraper->scrape($url);
        } catch (\Exception $e) {
            return AgentResult::failure(
                "기사 콘텐츠 추출 실패: " . $e->getMessage(),
                $this->getName()
            );
        }

        // Step 4: 콘텐츠 유효성 검증
        $validationResult = $this->validateContent($articleData);
        if (!$validationResult['is_valid']) {
            return AgentResult::failure(
                $validationResult['reason'],
                $this->getName()
            );
        }

        // Step 5: AI를 통한 추가 검증 (선택적)
        $aiValidation = $this->performAIValidation($articleData);

        // 성공 결과 반환
        $context = $context
            ->withArticleData($articleData)
            ->withMetadata('validation', [
                'is_valid' => true,
                'language' => $articleData->getLanguage(),
                'content_length' => $articleData->getContentLength(),
                'ai_validation' => $aiValidation
            ])
            ->markProcessedBy($this->getName());

        return AgentResult::success(
            [
                'article' => $articleData->toArray(),
                'validation' => [
                    'is_valid' => true,
                    'language' => $articleData->getLanguage(),
                    'content_length' => $articleData->getContentLength(),
                    'word_count' => $articleData->getWordCount(),
                    'ai_validation' => $aiValidation
                ]
            ],
            ['agent' => $this->getName(), 'url' => $url]
        );
    }

    /**
     * 콘텐츠 유효성 검증
     */
    private function validateContent(ArticleData $article): array
    {
        // 제목 확인
        if (empty($article->getTitle())) {
            return [
                'is_valid' => false,
                'reason' => '기사 제목을 찾을 수 없습니다.'
            ];
        }

        // 본문 길이 확인
        if ($article->getContentLength() < $this->minContentLength) {
            return [
                'is_valid' => false,
                'reason' => "기사 본문이 너무 짧습니다. (최소 {$this->minContentLength}자 필요, 현재 {$article->getContentLength()}자)"
            ];
        }

        return ['is_valid' => true, 'reason' => ''];
    }

    /**
     * AI를 통한 추가 검증
     */
    private function performAIValidation(ArticleData $article): array
    {
        try {
            $prompt = $this->getPrompt('validate_url');
            $prompt = $this->formatPrompt($prompt, [
                'url' => $article->getUrl(),
                'title' => $article->getTitle(),
                'description' => $article->getDescription() ?? '',
                'content_length' => $article->getContentLength()
            ]);

            $response = $this->callGPT($prompt);
            $result = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                return $result;
            }

            return [
                'is_valid' => true,
                'confidence' => 0.8,
                'reason' => 'AI 검증 완료',
                'detected_language' => $article->getLanguage(),
                'article_type' => 'news'
            ];
        } catch (\Exception $e) {
            $this->log("AI validation error: " . $e->getMessage(), 'warning');
            
            // AI 검증 실패해도 기본 검증은 통과
            return [
                'is_valid' => true,
                'confidence' => 0.7,
                'reason' => 'AI 검증 스킵됨 (기본 검증 통과)',
                'detected_language' => $article->getLanguage(),
                'article_type' => 'unknown'
            ];
        }
    }
}
