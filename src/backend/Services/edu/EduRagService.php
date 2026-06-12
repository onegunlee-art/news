<?php
/**
 * GIST EDU — READ-ONLY RAG layer (news pipeline untouched)
 */
declare(strict_types=1);

namespace Services\Edu;

use Agents\Services\SupabaseService;
use App\Services\IntelligenceEmbeddingService;

class EduRagService
{
    private const MAX_CONTEXT_CHARS = 1500;
    private const TOP_K = 3;

    private SupabaseService $supabase;
    private ?IntelligenceEmbeddingService $intelligence;

    public function __construct(?SupabaseService $supabase = null, ?IntelligenceEmbeddingService $intelligence = null)
    {
        $this->supabase = $supabase ?? new SupabaseService([]);
        $this->intelligence = $intelligence;
    }

    /**
     * @param list<int> $newsIds
     * @return array{alignment: string, conflict: string, articles: list<array<string, mixed>>}
     */
    public function findArcArticles(array $newsIds, string $query = ''): array
    {
        if (!$this->supabase->isConfigured() || $newsIds === []) {
            return ['alignment' => '', 'conflict' => '', 'articles' => []];
        }

        $filter = 'news_id=in.(' . implode(',', array_map('intval', $newsIds)) . ')';
        $rows = $this->supabase->select('analysis_embeddings', $filter . '&chunk_type=eq.published', 30) ?? [];

        $snippets = [];
        foreach ($rows as $row) {
            $nid = (int) ($row['news_id'] ?? 0);
            $text = mb_substr((string) ($row['chunk_text'] ?? ''), 0, 200);
            $meta = $row['metadata'] ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }
            $snippets[] = [
                'news_id' => $nid,
                'topic_label' => (string) ($meta['topic_label'] ?? ''),
                'excerpt' => $text,
            ];
        }

        $alignment = $this->joinSnippets($snippets, 'topic_label');
        $conflict = $query !== '' ? $query : '';

        return [
            'alignment' => mb_substr($alignment, 0, 500),
            'conflict' => mb_substr($conflict, 0, 500),
            'articles' => array_slice($snippets, 0, self::TOP_K),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findMixUpPairs(string $topic, string $region = '', int $topK = 3): array
    {
        $intel = $this->intelligence ?? new IntelligenceEmbeddingService();
        if (!$intel->isConfigured()) {
            return [];
        }

        $query = $topic;
        if ($region !== '') {
            $query .= ' ' . $region;
        }

        $options = [
            'match_count' => min($topK * 2, 10),
            'min_relevance' => 55,
        ];
        if ($region !== '') {
            $options['filter_region'] = $region;
        }
        if ($topic !== '') {
            $options['filter_topic'] = $topic;
        }

        $rows = $intel->search($query, $options);
        $pairs = [];
        $sources = [];

        foreach ($rows as $row) {
            $meta = $row['metadata'] ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }
            $source = (string) ($row['source_api'] ?? $meta['source'] ?? 'external');
            if (isset($sources[$source])) {
                continue;
            }
            $sources[$source] = true;
            $pairs[] = [
                'source' => $source,
                'excerpt' => mb_substr((string) ($row['chunk_text'] ?? ''), 0, 280),
                'region' => $meta['region'] ?? [],
                'topic' => $meta['topic'] ?? [],
                'week' => (string) ($meta['week'] ?? ''),
                'relevance' => (float) ($row['similarity'] ?? $row['relevance_score'] ?? 0),
            ];
            if (count($pairs) >= $topK) {
                break;
            }
        }

        return $pairs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findTemporalShift(string $topic, int $weeks = 8, int $topK = 3): array
    {
        $intel = $this->intelligence ?? new IntelligenceEmbeddingService();
        if (!$intel->isConfigured()) {
            return [];
        }

        $rows = $intel->search($topic, [
            'match_count' => min(20, $topK * 4),
            'min_relevance' => 50,
            'filter_topic' => $topic,
        ]);

        usort($rows, static function (array $a, array $b): int {
            $wa = (string) (($a['metadata']['week'] ?? '') ?: '0000-W00');
            $wb = (string) (($b['metadata']['week'] ?? '') ?: '0000-W00');
            return strcmp($wa, $wb);
        });

        $out = [];
        foreach ($rows as $row) {
            $meta = $row['metadata'] ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }
            $out[] = [
                'week' => (string) ($meta['week'] ?? ''),
                'source' => (string) ($row['source_api'] ?? ''),
                'excerpt' => mb_substr((string) ($row['chunk_text'] ?? ''), 0, 200),
            ];
            if (count($out) >= $topK) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWritingPatterns(string $query, int $topK = 3): array
    {
        if (!$this->supabase->isConfigured()) {
            return [];
        }

        $rows = $this->supabase->select('judgement_records', 'order=created_at.desc', min(50, $topK * 10)) ?? [];
        $patterns = [];
        $q = mb_strtolower($query);

        foreach ($rows as $row) {
            $diff = $row['semantic_diff'] ?? [];
            if (is_string($diff)) {
                $diff = json_decode($diff, true) ?: [];
            }
            $direction = (string) ($diff['overall_direction'] ?? '');
            $items = $diff['judgement_patterns'] ?? [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $category = (string) ($item['category'] ?? '');
                $editorFix = (string) (
                    $item['editor_correction']
                    ?? $item['editor_fix']
                    ?? $item['human_approach']
                    ?? ''
                );
                $blob = mb_strtolower($category . ' ' . $editorFix . ' ' . $direction);
                if ($q !== '' && !str_contains($blob, $q) && count($patterns) > 0) {
                    continue;
                }
                $patterns[] = [
                    'category' => $category,
                    'editor_fix' => mb_substr($editorFix, 0, 200),
                    'editor_correction' => mb_substr($editorFix, 0, 200),
                    'direction' => mb_substr($direction, 0, 120),
                ];
                if (count($patterns) >= $topK) {
                    return $patterns;
                }
            }
        }

        return $patterns;
    }

    /**
     * AGI judgement_patterns (weight/frequency) — READ only
     *
     * @return list<array<string, mixed>>
     */
    public function getJudgementPatterns(int $topK = 3): array
    {
        if (!$this->supabase->isConfigured()) {
            return [];
        }

        $rows = $this->supabase->select('judgement_patterns', 'order=weight.desc', min(30, $topK * 10)) ?? [];
        $patterns = [];

        foreach ($rows as $row) {
            $category = (string) ($row['category'] ?? $row['pattern_type'] ?? 'pattern');
            $correction = (string) ($row['editor_correction'] ?? $row['human_approach'] ?? $row['description'] ?? '');
            if ($correction === '') {
                continue;
            }
            $patterns[] = [
                'category' => $category,
                'editor_fix' => mb_substr($correction, 0, 200),
                'editor_correction' => mb_substr($correction, 0, 200),
                'weight' => (float) ($row['weight'] ?? $row['frequency'] ?? 0),
            ];
            if (count($patterns) >= $topK) {
                break;
            }
        }

        return $patterns;
    }

    /** @param list<array<string, mixed>> $pairs */
    public function formatMixUpContext(array $pairs): string
    {
        if ($pairs === []) {
            return '';
        }
        $lines = [];
        foreach ($pairs as $p) {
            $lines[] = sprintf(
                '- [%s] %s',
                $p['source'] ?? 'external',
                $p['excerpt'] ?? ''
            );
        }
        $text = implode("\n", $lines);
        return mb_substr($text, 0, self::MAX_CONTEXT_CHARS);
    }

    /** @param list<array<string, mixed>> $patterns */
    public function formatWritingPatterns(array $patterns): string
    {
        if ($patterns === []) {
            return '';
        }
        $lines = [];
        foreach ($patterns as $p) {
            $lines[] = sprintf(
                '- [%s] %s',
                $p['category'] ?? 'pattern',
                $p['editor_correction'] ?? $p['editor_fix'] ?? ''
            );
        }
        return mb_substr(implode("\n", $lines), 0, self::MAX_CONTEXT_CHARS);
    }

    /** @param list<array<string, mixed>> $snippets */
    private function joinSnippets(array $snippets, string $key): string
    {
        $parts = [];
        foreach ($snippets as $s) {
            $v = trim((string) ($s[$key] ?? $s['excerpt'] ?? ''));
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        return implode(' / ', array_unique($parts));
    }
}
