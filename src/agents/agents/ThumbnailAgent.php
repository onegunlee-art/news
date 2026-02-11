<?php
/**
 * Thumbnail Agent
 *
 * 기사 썸네일을 저작권 회피용 일러스트/캐리커처 스타일로 교체.
 * ValidationAgent 이후 실행되며, og:image 대신 getIllustrationImageUrl() 결과를 사용합니다.
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

class ThumbnailAgent extends BaseAgent
{
    private ?string $projectRoot = null;

    public function __construct(OpenAIService $openai, array $config = [])
    {
        parent::__construct($openai, $config);
        $this->projectRoot = $config['project_root'] ?? null;
    }

    public function getName(): string
    {
        return 'ThumbnailAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => 'Thumbnail image selection agent.',
            'tasks' => [],
        ];
    }

    public function validate(mixed $input): bool
    {
        if ($input instanceof AgentContext) {
            return $input->getArticleData() !== null;
        }
        return false;
    }

    /**
     * DALL·E 3용 영문 프롬프트 생성. 기사 제목/요약을 바탕으로 에디토리얼 일러스트 설명.
     */
    private function buildImagePrompt(string $title, string $description, string $category): string
    {
        $text = trim($title . ' ' . $description);
        if (mb_strlen($text) > 800) {
            $text = mb_substr($text, 0, 800);
        }
        $prompt = 'Editorial, professional illustration for a news article. Style: clean, modern, no realistic faces. ';
        $prompt .= 'Topic or mood suggested by the following (use only as inspiration, do not copy text): ';
        $prompt .= $text;
        return $prompt;
    }

    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();

        $article = $context->getArticleData();
        if ($article === null) {
            return AgentResult::failure(
                '기사 데이터가 없습니다. ValidationAgent를 먼저 실행하세요.',
                $this->getName()
            );
        }

        $title = $article->getTitle();
        $description = $article->getDescription() ?? '';
        $category = $article->getMetadata()['category'] ?? '';

        $newImageUrl = null;

        // 1) OpenAI DALL·E 3로 썸네일 생성 (API 키가 있고 mock 아님)
        if ($this->openai->isConfigured()) {
            $prompt = $this->buildImagePrompt($title, $description, $category);
            $generated = $this->openai->createImage($prompt);
            if ($generated !== null && $generated !== '') {
                $newImageUrl = $generated;
                $this->log('Thumbnail generated with DALL·E 3: ' . $newImageUrl, 'info');
            }
        }

        // 2) 실패 시 기존 일러스트 검색(Unsplash/Pexels 등)
        if ($newImageUrl === null || $newImageUrl === '') {
            $projectRoot = $this->projectRoot ?? dirname(__DIR__, 3);
            $imageSearchPath = $projectRoot . '/public/api/lib/imageSearch.php';
            if (is_file($imageSearchPath)) {
                require_once $projectRoot . '/public/api/lib/imageConfig.php';
                require_once $imageSearchPath;
                $pdo = $this->config['thumbnail']['pdo'] ?? $this->config['pdo'] ?? null;
                $newImageUrl = getIllustrationImageUrl($title, $description, $category, $pdo);
            }
        }

        if ($newImageUrl === null || $newImageUrl === '') {
            $newImageUrl = 'https://placehold.co/800x500/1e293b/94a3b8?text=News';
        }

        $updatedArticle = $article->withImageUrl($newImageUrl);
        $this->log("Thumbnail replaced for: " . mb_substr($title, 0, 50) . "...", 'info');

        return AgentResult::success(
            [
                'article' => $updatedArticle->toArray(),
                'thumbnail' => [
                    'image_url' => $newImageUrl,
                    'style' => 'illustration',
                ],
            ],
            ['agent' => $this->getName()]
        );
    }
}
