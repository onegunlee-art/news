<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\EntityCanonicalMap;

class TextCleanerService
{
    public function clean(string $raw, ?string $title = null): string
    {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = $this->removeBoilerplate($text);
        if ($title) {
            $text = preg_replace('/^' . preg_quote(trim($title), '/') . '\s*/u', '', $text) ?? $text;
        }
        $text = EntityCanonicalMap::canonicalizeText(trim($text));
        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }

    private function removeBoilerplate(string $text): string
    {
        $patterns = [
            '/Subscribe to\s+[^\n]{0,200}/iu',
            '/Sign Up\s*[?\->]?\s*/iu',
            '/Enter your email[^.]{0,100}\.?/iu',
            '/delivered to your inbox[^.]{0,100}\.?/iu',
            '/Get the latest[^.]{0,150}\.?/iu',
            '/Read more[^.]{0,100}\.?/iu',
            '/Advertisement\s*/iu',
            '/Share this article[^.]{0,100}\.?/iu',
        ];
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }
        return $text;
    }

    public function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        return count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
