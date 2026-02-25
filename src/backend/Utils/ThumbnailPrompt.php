<?php
/**
 * DALL-E 썸네일 프롬프트 단일 정의
 *
 * [콘텐츠 레이어] + [고정 스타일 블록] 구조.
 * ThumbnailAgent(파이프라인)와 Admin(ai-analyze regenerate_thumbnail_dalle)에서 공유.
 *
 * @author The Gist AI System
 */

declare(strict_types=1);

namespace App\Utils;

final class ThumbnailPrompt
{
    /**
     * [고정 스타일 블록] 브랜드 스타일. 항상 동일.
     */
    public static function getStyleLayer(): string
    {
        return "Dense multi-scene editorial illustration across a layered city.\n" .
            "Architectural cutaway buildings revealing interior rooms.\n" .
            "Panel grid composition with parallel narrative scenes.\n\n" .
            "Environment-first storytelling.\n" .
            "No oversized metaphor.\n" .
            "No infographic style.\n\n" .
            "Highly symmetrical,\n" .
            "miniature stylized figures,\n" .
            "flat pastel palette,\n" .
            "thin line art,\n" .
            "vintage editorial print texture,\n" .
            "planimetric perspective,\n" .
            "magazine illustration quality,\n" .
            "text-free.\n\n" .
            "Complex cross-section city block,\n" .
            "multiple rooms visible simultaneously,\n" .
            "parallel narrative scenes unfolding at once.";
    }

    /**
     * 콘텐츠 변수로 최종 DALL-E 프롬프트 조립.
     *
     * @param string $summary  기사 요약 (한 문장)
     * @param string $keywords 핵심 키워드 (쉼표 구분)
     */
    public static function buildFullPrompt(string $summary, string $keywords): string
    {
        $summary = trim($summary) !== '' ? trim($summary) : 'Global news.';
        $keywords = trim($keywords);

        return $summary . "\n" .
            $keywords . "\n\n" .
            self::getStyleLayer();
    }

    /**
     * 콘텐츠 블록(5–8줄 시네마틱 씬 등)으로 최종 DALL-E 프롬프트 조립.
     */
    public static function buildFullPromptFromContentBlock(string $contentBlock): string
    {
        $contentBlock = trim($contentBlock) !== '' ? trim($contentBlock) : 'Global news.';
        return $contentBlock . "\n\n" . self::getStyleLayer();
    }

    /**
     * 기사 텍스트에서 GPT로 콘텐츠 레이어 추출 (에디토리얼 아트 디렉터 방식).
     * 출력: 5–8줄 시네마틱 씬 설명 → content_block.
     * $openai는 chat(string $systemPrompt, string $userPrompt): string 메서드를 가진 객체.
     *
     * @param string $articleUrl 기사 URL (프롬프트 컨텍스트용, 선택)
     * @return array{summary: string, keywords: string, content_block: string} summary/keywords는 하위 호환용, content_block이 메인.
     */
    public static function extractContentLayerFromArticle(string $title, string $descriptionOrContent, object $openai, string $articleUrl = ''): array
    {
        $fallbackBlock = mb_substr(trim($title), 0, 200) ?: 'Global news.';
        $default = [
            'summary' => $fallbackBlock,
            'keywords' => '',
            'content_block' => $fallbackBlock,
        ];

        if (!method_exists($openai, 'chat')) {
            return $default;
        }

        $systemPrompt = "You are an editorial art director for an international affairs magazine.\n\n" .
            "Extract the core geopolitical argument from the article.\n" .
            "Then translate the argument into visual narrative scenes for an editorial illustration.\n\n" .
            "Rules:\n" .
            "- Do NOT summarize the article\n" .
            "- Convert abstract ideas into physical situations\n" .
            "- Focus on diplomacy, power hierarchy, negotiation, alliance dynamics\n" .
            "- Include people behavior, spatial relationships, symbolic objects\n" .
            "- Avoid generic office scenes\n" .
            "- Avoid decorative imagery\n\n" .
            "Output:\n" .
            "Short cinematic scene descriptions (5–8 lines).\n" .
            "Each line must describe a visual situation that can be illustrated.\n\n" .
            "Tone:\n" .
            "Subtle geopolitical tension, institutional environments, strategic relationships, quiet symbolism.\n\n" .
            "Output only the 5–8 lines, one scene per line, no labels or numbering.";

        $userPrompt = '';
        if ($articleUrl !== '') {
            $userPrompt .= "Read the article from this URL:\n" . trim($articleUrl) . "\n\n";
        }
        $userPrompt .= "Article title: " . trim($title) . "\n\n";
        if (trim($descriptionOrContent) !== '') {
            $snippet = mb_substr(trim($descriptionOrContent), 0, 4000);
            $userPrompt .= "Article content (excerpt):\n" . $snippet . "\n\n";
        }
        $userPrompt .= "Provide 5–8 lines of cinematic scene descriptions for the thumbnail illustration.";

        try {
            $response = $openai->chat($systemPrompt, $userPrompt);
        } catch (\Throwable $e) {
            return $default;
        }

        $lines = preg_split('/\r\n|\r|\n/', $response);
        $sceneLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $sceneLines[] = $line;
            if (count($sceneLines) >= 8) {
                break;
            }
        }

        if (count($sceneLines) === 0) {
            return $default;
        }

        $contentBlock = implode("\n", $sceneLines);

        return [
            'summary' => $sceneLines[0] ?? $fallbackBlock,
            'keywords' => '',
            'content_block' => $contentBlock,
        ];
    }

    /**
     * 제목만 있을 때(또는 GPT 실패 시) 최소 콘텐츠 레이어로 fallback.
     */
    public static function buildFallbackPromptFromTitle(string $titleSnippet): string
    {
        $t = mb_substr(trim($titleSnippet), 0, 200) ?: 'news';
        return self::buildFullPromptFromContentBlock($t);
    }
}
