<?php
/**
 * Thumbnail Agent v3.0
 *
 * 뉴스 기사 썸네일 생성 에이전트.
 * 기사 제목을 기반으로 메타포 카툰 스타일의 DALL·E 3 프롬프트를 생성합니다.
 *
 * 우선순위: DALL·E 3 생성 → 원본 og:image → 카테고리 플레이스홀더
 *
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 3.0.0
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

    // ══════════════════════════════════════════════════════════
    //  프롬프트 생성 (핵심)
    // ══════════════════════════════════════════════════════════

    /**
     * 기사 제목 기반 DALL-E 3 썸네일 프롬프트 생성.
     * 메타포 중심의 재치 있는 카툰 스타일, 텍스트/인물 직접 표현 없음.
     */
    private function buildThumbnailPrompt(string $title): string
    {
        $titleSnippet = mb_substr(trim($title), 0, 200);
        if ($titleSnippet === '') {
            $titleSnippet = 'news';
        }

        return "Start by using the original headline of the article from the provided URL as the default basis for the thumbnail concept. " .
            "Based on the article title (without extracting or quoting the full text), create a custom thumbnail concept art in a witty metaphorical cartoon style that visually represents the key idea implied by the title: \"{$titleSnippet}\". " .
            "Style: Playful metaphor cartoon (no literal portraits), with a medium level of satire. " .
            "Main characters: Include 1–2 protagonist characters representing the key country or countries, expressed through national characteristics or flags in a stylized, symbolic way. " .
            "Composition: Vertical (portrait) orientation with a wide cinematic feel optimized for a tall thumbnail. " .
            "Background: Keep the background clean and not overly complex so the main symbols and characters stand out clearly. " .
            "Visual elements: The image must include symbolic objects, at least one clear national symbol, and visible flags integrated naturally into the scene. " .
            "No text in the image. " .
            "Imagery should convey the concept of the article title without any text. " .
            "Clever symbolic elements and humor are encouraged. " .
            "Do NOT include any written titles or captions in the thumbnail itself.";
    }

    // ══════════════════════════════════════════════════════════
    //  유틸리티
    // ══════════════════════════════════════════════════════════

    /**
     * 원본 og:image URL이 유효한지 확인.
     */
    private function isValidOriginalImage(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }
        if (str_contains($url, 'placehold.co')) {
            return false;
        }
        if (str_starts_with($url, 'data:')) {
            return false;
        }
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

    // ══════════════════════════════════════════════════════════
    //  메인 프로세스
    // ══════════════════════════════════════════════════════════

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
        $originalImageUrl = $article->getImageUrl();

        $newImageUrl = null;
        $thumbnailSource = 'none';
        $usedPrompt = '';

        // ── 1) GPT → DALL·E 3 생성 (최우선) ──
        if ($this->openai->isConfigured()) {
            try {
                $prompt = $this->buildThumbnailPrompt($title);
                $usedPrompt = $prompt;
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
                    'prompt_used' => $thumbnailSource === 'dall-e-3' ? mb_substr($usedPrompt, 0, 500) : null,
                ],
            ],
            ['agent' => $this->getName()]
        );
    }
}
