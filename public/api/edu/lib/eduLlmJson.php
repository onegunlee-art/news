<?php
/**
 * GIST EDU — shared LLM JSON extraction (API layer wrapper)
 */
declare(strict_types=1);

/** @return array<string, mixed>|null */
function eduParseLlmJson(array $llmResponse, ?array $fallback = null): ?array
{
    $root = eduFindProjectRoot();
    require_once $root . 'src/backend/Services/edu/EduLlmJson.php';

    return \Services\Edu\EduLlmJson::parse($llmResponse, $fallback);
}
