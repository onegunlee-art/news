<?php
declare(strict_types=1);

namespace Services\Edu;

/**
 * Shared LLM JSON extraction (prompt JSON + regex/fence parse)
 */
final class EduLlmJson
{
    /**
     * @param array<string, mixed> $llmResponse
     * @param array<string, mixed>|null $fallback
     * @return array<string, mixed>|null
     */
    public static function parse(array $llmResponse, ?array $fallback = null): ?array
    {
        if (!empty($llmResponse['error'])) {
            return $fallback;
        }
        $content = (string) ($llmResponse['content'] ?? '');
        if ($content === '') {
            return $fallback;
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/u', $content, $fence)) {
            $parsed = json_decode($fence[1], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $parsed = json_decode($match[0], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return $fallback;
    }
}
