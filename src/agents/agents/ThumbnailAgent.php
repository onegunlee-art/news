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

        $projectRoot = $this->projectRoot ?? dirname(__DIR__, 3);
        $imageSearchPath = $projectRoot . '/public/api/lib/imageSearch.php';
        if (!is_file($imageSearchPath)) {
            $this->log("imageSearch.php not found: {$imageSearchPath}", 'warning');
            return AgentResult::success(
                ['article' => $article->toArray()],
                ['agent' => $this->getName(), 'unchanged' => true]
            );
        }

        require_once $projectRoot . '/public/api/lib/imageConfig.php';
        require_once $imageSearchPath;

        $title = $article->getTitle();
        $description = $article->getDescription() ?? '';
        $category = $article->getMetadata()['category'] ?? '';

        $pdo = $this->config['thumbnail']['pdo'] ?? $this->config['pdo'] ?? null;
        $newImageUrl = getIllustrationImageUrl($title, $description, $category, $pdo);

        if ($newImageUrl === null || $newImageUrl === '') {
            $newImageUrl = 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800&h=500&fit=crop';
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
