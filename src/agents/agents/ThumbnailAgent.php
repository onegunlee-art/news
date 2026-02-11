<?php
/**
 * Thumbnail Agent
 *
 * 기사 썸네일 생성 에이전트.
 * 우선순위: DALL·E 3 생성 → 원본 og:image 사용 → 카테고리 플레이스홀더
 *
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 2.0.0
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
     * DALL·E 3용 영문 프롬프트 생성.
     * 기사 제목/요약을 바탕으로 뉴스 에디토리얼 일러스트 프롬프트를 구성.
     */
    private function buildImagePrompt(string $title, string $description, string $category): string
    {
        $text = trim($title . ' ' . $description);
        if (mb_strlen($text) > 600) {
            $text = mb_substr($text, 0, 600);
        }

        // 카테고리별 스타일 힌트
        $styleHint = match ($category) {
            'diplomacy', 'politics' => 'geopolitical theme, world map elements, diplomatic imagery',
            'economy', 'finance'    => 'financial theme, charts, currency symbols, economic imagery',
            'entertainment'         => 'entertainment theme, vibrant colors, pop culture elements',
            'technology', 'tech'    => 'technology theme, digital elements, futuristic imagery',
            'security', 'military'  => 'security theme, strategic imagery, defense elements',
            default                 => 'professional news editorial imagery',
        };

        $prompt = "Create a high-quality editorial illustration for an international news article. "
            . "Style: flat vector art, clean minimalist design, bold colors on dark background (#1e293b), "
            . "NO text, NO letters, NO words, NO realistic human faces. "
            . "Theme: {$styleHint}. "
            . "Mood/topic inspired by: {$text}";

        return $prompt;
    }

    /**
     * 원본 og:image URL이 유효한지 확인.
     * - 플레이스홀더 URL이 아닌지
     * - 비어있지 않은지
     * - HTTP(S) URL인지
     */
    private function isValidOriginalImage(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }
        // placehold.co 플레이스홀더는 원본이 아님
        if (str_contains($url, 'placehold.co')) {
            return false;
        }
        // 빈 데이터 URI 제외
        if (str_starts_with($url, 'data:')) {
            return false;
        }
        // HTTP(S) URL이어야 함
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }
        return true;
    }

    /**
     * 카테고리/제목 기반 의미 있는 플레이스홀더 URL 생성
     */
    private function buildFallbackPlaceholder(string $title, string $category): string
    {
        // 카테고리별 색상 및 라벨
        $themes = [
            'diplomacy' => ['0f172a', '38bdf8', 'Diplomacy'],
            'economy'   => ['0f172a', '34d399', 'Economy'],
            'entertainment' => ['0f172a', 'fb923c', 'Entertainment'],
            'technology' => ['0f172a', 'a78bfa', 'Tech'],
            'security'  => ['0f172a', 'f87171', 'Security'],
        ];

        $theme = $themes[$category] ?? ['1e293b', '94a3b8', 'The+Gist'];
        $bg = $theme[0];
        $fg = $theme[1];
        $label = $theme[2];

        return "https://placehold.co/800x500/{$bg}/{$fg}?text={$label}";
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
        $originalImageUrl = $article->getImageUrl(); // 스크래핑 시 추출한 og:image

        $newImageUrl = null;
        $thumbnailSource = 'none';

        // ── 1) DALL·E 3로 썸네일 생성 (최우선) ──
        if ($this->openai->isConfigured()) {
            try {
                $prompt = $this->buildImagePrompt($title, $description, $category);
                $generated = $this->openai->createImage($prompt);
                if ($generated !== null && $generated !== '') {
                    $newImageUrl = $generated;
                    $thumbnailSource = 'dall-e-3';
                    $this->log('Thumbnail generated with DALL·E 3: ' . $newImageUrl, 'info');
                }
            } catch (\Throwable $e) {
                $this->log('DALL·E 3 thumbnail generation failed: ' . $e->getMessage(), 'warning');
            }
        } else {
            $this->log('OpenAI not configured for DALL·E, trying fallbacks', 'info');
        }

        // ── 2) DALL-E 실패 시 → 원본 기사의 og:image 사용 ──
        if (($newImageUrl === null || $newImageUrl === '') && $this->isValidOriginalImage($originalImageUrl)) {
            $newImageUrl = $originalImageUrl;
            $thumbnailSource = 'og:image';
            $this->log('Using original og:image: ' . $originalImageUrl, 'info');
        }

        // ── 3) og:image도 없으면 → 카테고리 기반 플레이스홀더 ──
        if ($newImageUrl === null || $newImageUrl === '') {
            $newImageUrl = $this->buildFallbackPlaceholder($title, $category);
            $thumbnailSource = 'placeholder';
            $this->log('Using category placeholder for: ' . mb_substr($title, 0, 50), 'info');
        }

        $updatedArticle = $article->withImageUrl($newImageUrl);
        $this->log("Thumbnail set ({$thumbnailSource}) for: " . mb_substr($title, 0, 50) . "...", 'info');

        return AgentResult::success(
            [
                'article' => $updatedArticle->toArray(),
                'thumbnail' => [
                    'image_url' => $newImageUrl,
                    'source' => $thumbnailSource,
                    'style' => $thumbnailSource === 'dall-e-3' ? 'illustration' : ($thumbnailSource === 'og:image' ? 'original' : 'placeholder'),
                ],
            ],
            ['agent' => $this->getName()]
        );
    }
}
