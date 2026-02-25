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
     * 기사 텍스트에서 GPT로 CONTENT 변수(Summary, Keywords) 추출.
     * $openai는 chat(string $systemPrompt, string $userPrompt): string 메서드를 가진 객체.
     *
     * @return array{summary: string, keywords: string}
     */
    public static function extractContentLayerFromArticle(string $title, string $descriptionOrContent, object $openai): array
    {
        $default = [
            'summary' => mb_substr(trim($title), 0, 200) ?: 'news',
            'keywords' => '',
        ];

        if (!method_exists($openai, 'chat')) {
            return $default;
        }

        $systemPrompt = 'You are an editor summarizing a news article for a thumbnail illustration brief. ' .
            'Output exactly two lines in English, no other text. ' .
            'Line 1: Summary: [one sentence summarizing the article]. ' .
            'Line 2: Keywords: [comma-separated key terms: persons, institutions, concepts, themes].';

        $userPrompt = "Article title: " . trim($title) . "\n\n";
        if (trim($descriptionOrContent) !== '') {
            $snippet = mb_substr(trim($descriptionOrContent), 0, 2000);
            $userPrompt .= "Summary or excerpt: " . $snippet . "\n\n";
        }
        $userPrompt .= "Provide Summary (one sentence) and Keywords (comma-separated) for the thumbnail.";

        try {
            $response = $openai->chat($systemPrompt, $userPrompt);
        } catch (\Throwable $e) {
            return $default;
        }

        $summary = '';
        $keywords = '';

        $lines = preg_split('/\r\n|\r|\n/', $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'Summary:') === 0) {
                $summary = trim(preg_replace('/\s+/', ' ', (string) substr($line, 8)));
            } elseif (stripos($line, 'Keywords:') === 0) {
                $keywords = trim(preg_replace('/\s+/', ' ', (string) substr($line, 9)));
            }
        }

        if ($summary === '' && $keywords === '') {
            return $default;
        }

        return [
            'summary' => $summary !== '' ? $summary : $default['summary'],
            'keywords' => $keywords,
        ];
    }

    /**
     * 제목만 있을 때(또는 GPT 실패 시) 최소 콘텐츠 레이어로 fallback.
     */
    public static function buildFallbackPromptFromTitle(string $titleSnippet): string
    {
        $t = mb_substr(trim($titleSnippet), 0, 200) ?: 'news';
        return self::buildFullPrompt($t, '');
    }
}
