<?php
/**
 * GIST EDU — READ ONLY narration excerpts from MySQL news table
 */
declare(strict_types=1);

namespace Services\Edu;

use PDO;

class GistNarrationReader
{
    /**
     * @param list<int> $newsIds
     * @return list<array{news_id: int, title: string, excerpt: string}>
     */
    public function readExcerpts(array $newsIds, int $maxCharsPerArticle = 400): array
    {
        $newsIds = array_values(array_filter(array_map('intval', $newsIds)));
        if ($newsIds === []) {
            return [];
        }

        require_once dirname(__DIR__, 4) . '/public/api/edu/lib/eduMysql.php';
        $pdo = eduMysql();
        $cols = eduNewsColumns($pdo);

        $select = ['id'];
        if (in_array('title', $cols, true)) {
            $select[] = 'title';
        }
        if (in_array('narration', $cols, true)) {
            $select[] = 'narration';
        } elseif (in_array('content', $cols, true)) {
            $select[] = 'content';
        }

        $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
        $sql = 'SELECT ' . implode(', ', $select) . " FROM news WHERE id IN ({$placeholders}) AND status = 'published'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($newsIds);

        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $text = (string) ($row['narration'] ?? $row['content'] ?? '');
            $text = trim(strip_tags($text));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'news_id' => (int) $row['id'],
                'title' => (string) ($row['title'] ?? ''),
                'excerpt' => mb_substr($text, 0, $maxCharsPerArticle),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{news_id: int, title: string, excerpt: string}> $excerpts
     */
    public function formatFewShot(array $excerpts): string
    {
        if ($excerpts === []) {
            return '';
        }
        $lines = [];
        foreach ($excerpts as $e) {
            $lines[] = sprintf('[%s] %s', $e['title'] ?? 'gist', $e['excerpt'] ?? '');
        }
        return mb_substr(implode("\n", $lines), 0, 1500);
    }
}
