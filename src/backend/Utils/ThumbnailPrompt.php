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
     * [고정 스타일 블록] 고급 국제문제 매거진 에디토리얼 일러스트 (The French Dispatch / New Yorker 커버 느낌).
     */
    public static function getStyleLayer(): string
    {
        return "Editorial illustration for a high-end international affairs magazine.\n\n" .
            "Soft pastel color palette: muted teal, warm beige, faded coral, pale yellow, desaturated navy.\n" .
            "Hand-drawn ink linework with subtle print grain. Paper texture visible. Vintage editorial magazine feeling.\n\n" .
            "Structured composition: layered environments, map-like layouts, cutaway only when conceptually necessary, multiple narrative zones connected visually.\n" .
            "Characters small but expressive. Meaning conveyed through environment and spatial relationships.\n" .
            "Objects arranged with geometric precision. Strong balance and clarity.\n\n" .
            "Lighting bright pastel daylight. Calm intellectual mood. Tension expressed through composition, not darkness.\n" .
            "Visual density high but readable. Every area contributes to the story.\n\n" .
            "Do NOT depict meeting rooms, conference tables, think-tank offices, or generic corporate interiors unless the article is explicitly about negotiation spaces.\n" .
            "Avoid dark cinematic war scenes, photorealism, 3D render look, corporate stock illustration style.\n" .
            "Symbolism must emerge from the article's argument.\n\n" .
            "Illustration must feel like a printed magazine spread. Composition tells a story across multiple spaces simultaneously. Symbolism subtle and integrated into environments. Focus on the structural argument; the viewer should understand the article's core argument without text. Text-free.";
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
     * @param string $articleUrl 사용하지 않음 (URL로 추측하지 않도록 제거). 하위 호환용으로 시그니처 유지.
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

        $systemPrompt = "You are an editorial art director for a high-end international affairs magazine.\n\n" .
            "Translate the extracted article message into a symbolic editorial illustration scene.\n" .
            "The image must visualize dynamics, consequences, relationships, and tensions described in the article rather than literal meetings or offices.\n" .
            "Depict systems interacting across space: geography, infrastructure, institutions, people, technology, or forces depending on the topic.\n" .
            "Show cause and effect, pressure points, trade-offs, or emerging risks through composition and symbolism.\n\n" .
            "Rules:\n" .
            "- Base the scenes ONLY on the title and excerpt provided below. Do not add details, symbols, or themes not explicitly supported by that text.\n" .
            "- Do not infer content from any URL; use only the title and excerpt.\n" .
            "- Each scene must be directly grounded in the provided text. Avoid generic or unrelated imagery.\n" .
            "- Do NOT summarize the article; convert the argument into physical situations and spatial relationships.\n" .
            "- Avoid meeting rooms, conference tables, think-tank offices, decorative imagery. Symbolism must emerge from the article's argument.\n\n" .
            "Output:\n" .
            "Short cinematic scene descriptions (5–8 lines). One scene per line, no labels or numbering.\n" .
            "Tone: subtle geopolitical tension, institutional environments, strategic relationships, quiet symbolism. Focus on the structural argument so the viewer understands the core message without text.";

        $userPrompt = "Article title: " . trim($title) . "\n\n";
        if (trim($descriptionOrContent) !== '') {
            $snippet = mb_substr(trim($descriptionOrContent), 0, 4000);
            $userPrompt .= "Article content (excerpt):\n" . $snippet . "\n\n";
        }
        $userPrompt .= "Provide 5–8 lines of cinematic scene descriptions for the thumbnail illustration. Use only the information above.";

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
