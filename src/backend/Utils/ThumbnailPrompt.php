<?php
/**
 * DALL-E 썸네일 프롬프트 단일 정의
 *
 * [콘텐츠 레이어] + [스타일 레이어] 구조.
 * ThumbnailAgent(파이프라인)와 Admin(ai-analyze regenerate_thumbnail_dalle)에서 공유.
 *
 * @author The Gist AI System
 */

declare(strict_types=1);

namespace App\Utils;

final class ThumbnailPrompt
{
    /**
     * [STYLE LAYER] 고정 문자열. 편집 일러스트 스타일.
     */
    public static function getStyleLayer(): string
    {
        return "Flat editorial illustration\n" .
            "Symmetrical composition\n" .
            "Architectural city cross-section\n" .
            "Multiple small narrative scenes happening simultaneously\n" .
            "Clean line art, thin outlines\n" .
            "Muted pastel palette with one accent color\n" .
            "Vintage newspaper illustration mood\n" .
            "Planimetric perspective, minimal shadows\n" .
            "Stylized human figures with expressive poses\n" .
            "Playful but intellectual visual metaphor\n" .
            "Magazine cover composition\n" .
            "No text";
    }

    /**
     * 콘텐츠 변수로 최종 DALL-E 프롬프트 조립.
     *
     * @param string $coreTheme   기사 핵심 주제
     * @param string $keyElements 주요 인물/기관/개념
     * @param string $metaphorIdea 상징적 상황
     */
    public static function buildFullPrompt(string $coreTheme, string $keyElements, string $metaphorIdea): string
    {
        $coreTheme = trim($coreTheme) !== '' ? trim($coreTheme) : 'global news';
        $keyElements = trim($keyElements);
        $metaphorIdea = trim($metaphorIdea) !== '' ? trim($metaphorIdea) : $coreTheme;

        return "Create an editorial illustration thumbnail for a news article.\n\n" .
            "[CONTENT LAYER]\n" .
            "Core theme: " . $coreTheme . "\n" .
            "Key elements: " . $keyElements . "\n" .
            "Metaphor idea: " . $metaphorIdea . "\n\n" .
            "[STYLE LAYER]\n" .
            self::getStyleLayer() . "\n\n" .
            "High detail, balanced layout, professional global news thumbnail.\n\n" .
            "Generate one strong visual metaphor that represents the article's core conflict or idea.";
    }

    /**
     * 기사 텍스트에서 GPT로 CONTENT 변수(Core theme, Key elements, Metaphor idea) 추출.
     * $openai는 chat(string $systemPrompt, string $userPrompt): string 메서드를 가진 객체.
     *
     * @return array{core_theme: string, key_elements: string, metaphor_idea: string}
     */
    public static function extractContentLayerFromArticle(string $title, string $descriptionOrContent, object $openai): array
    {
        $default = [
            'core_theme' => mb_substr(trim($title), 0, 200) ?: 'news',
            'key_elements' => '',
            'metaphor_idea' => '',
        ];

        if (!method_exists($openai, 'chat')) {
            return $default;
        }

        $systemPrompt = 'You are an editor summarizing a news article for a thumbnail illustration brief. ' .
            'Output exactly three lines in English, no other text. Format (one line each): ' .
            'Core theme: [one short phrase] ' .
            'Key elements: [persons, institutions, or concepts] ' .
            'Metaphor idea: [one symbolic situation for visual metaphor]';

        $userPrompt = "Article title: " . trim($title) . "\n\n";
        if (trim($descriptionOrContent) !== '') {
            $snippet = mb_substr(trim($descriptionOrContent), 0, 2000);
            $userPrompt .= "Summary or excerpt: " . $snippet . "\n\n";
        }
        $userPrompt .= "Provide Core theme, Key elements, and Metaphor idea for the thumbnail.";

        try {
            $response = $openai->chat($systemPrompt, $userPrompt);
        } catch (\Throwable $e) {
            return $default;
        }

        $coreTheme = '';
        $keyElements = '';
        $metaphorIdea = '';

        $lines = preg_split('/\r\n|\r|\n/', $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'Core theme:') === 0) {
                $coreTheme = trim(preg_replace('/\s+/', ' ', (string) substr($line, 11)));
            } elseif (stripos($line, 'Key elements:') === 0) {
                $keyElements = trim(preg_replace('/\s+/', ' ', (string) substr($line, 13)));
            } elseif (stripos($line, 'Metaphor idea:') === 0) {
                $metaphorIdea = trim(preg_replace('/\s+/', ' ', (string) substr($line, 14)));
            }
        }

        if ($coreTheme === '' && $keyElements === '' && $metaphorIdea === '') {
            return $default;
        }

        return [
            'core_theme' => $coreTheme !== '' ? $coreTheme : $default['core_theme'],
            'key_elements' => $keyElements,
            'metaphor_idea' => $metaphorIdea !== '' ? $metaphorIdea : $default['core_theme'],
        ];
    }

    /**
     * 제목만 있을 때(또는 GPT 실패 시) 최소 콘텐츠 레이어로 fallback.
     * 하위 호환: 기존 buildDalleThumbnailPrompt 호출부가 없어지므로 이 메서드만 남김.
     */
    public static function buildFallbackPromptFromTitle(string $titleSnippet): string
    {
        $t = mb_substr(trim($titleSnippet), 0, 200) ?: 'news';
        return self::buildFullPrompt($t, '', '');
    }
}
