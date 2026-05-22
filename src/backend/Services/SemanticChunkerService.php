<?php
declare(strict_types=1);

namespace App\Services;

class SemanticChunkerService
{
    private const TARGET_WORDS = 280;
    private const OVERLAP_WORDS = 40;
    private const MIN_WORDS = 80;

    /** @return array<int, array{index:int,total:int,text:string,word_count:int,has_overlap:bool}> */
    public function chunk(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split('/\n\n+/u', $text) ?: [$text];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

        $chunks = [];
        $buffer = '';
        $bufferWords = 0;

        foreach ($paragraphs as $paragraph) {
            $words = $this->wordCount($paragraph);
            if ($buffer !== '' && ($bufferWords + $words) > self::TARGET_WORDS) {
                $chunks[] = trim($buffer);
                $buffer = $this->tailOverlap($buffer) . "\n\n" . $paragraph;
                $bufferWords = $this->wordCount($buffer);
            } else {
                $buffer = $buffer === '' ? $paragraph : $buffer . "\n\n" . $paragraph;
                $bufferWords += $words;
            }
        }
        if (trim($buffer) !== '') {
            $chunks[] = trim($buffer);
        }

        if ($chunks === []) {
            $chunks = [$text];
        }

        $total = count($chunks);
        $result = [];
        foreach ($chunks as $i => $chunkText) {
            $wc = $this->wordCount($chunkText);
            if ($wc < self::MIN_WORDS && $total > 1) {
                continue;
            }
            $result[] = [
                'index' => count($result),
                'total' => 0,
                'text' => $chunkText,
                'word_count' => $wc,
                'has_overlap' => $i > 0,
            ];
        }
        $finalTotal = count($result);
        foreach ($result as &$item) {
            $item['total'] = $finalTotal;
        }
        unset($item);
        return $result;
    }

    private function tailOverlap(string $text): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= self::OVERLAP_WORDS) {
            return $text;
        }
        return implode(' ', array_slice($words, -self::OVERLAP_WORDS));
    }

    private function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
