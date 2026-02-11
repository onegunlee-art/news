<?php
/**
 * RAG (Retrieval-Augmented Generation) Service
 *
 * OpenAI 임베딩 + Supabase pgvector 기반 검색-증강 생성 서비스.
 * 크리틱/분석 결과를 청크로 나눠 임베딩 저장하고,
 * 쿼리 시 유사한 컨텍스트를 검색해 시스템 프롬프트에 주입.
 *
 * @package Agents\Services
 */

declare(strict_types=1);

namespace Agents\Services;

class RAGService
{
    private OpenAIService $openai;
    private SupabaseService $supabase;

    /** 청크 최대 문자 수 (한국어 기준 ~300 토큰) */
    private const CHUNK_MAX_CHARS = 800;

    public function __construct(?OpenAIService $openai = null, ?SupabaseService $supabase = null)
    {
        $this->openai = $openai ?? new OpenAIService([]);
        $this->supabase = $supabase ?? new SupabaseService([]);
    }

    public function isConfigured(): bool
    {
        return $this->openai->isConfigured() && $this->supabase->isConfigured();
    }

    // ── Retrieval ───────────────────────────────────────

    /**
     * 쿼리와 관련된 크리틱/분석 컨텍스트를 검색.
     *
     * @return array{critiques: array, analyses: array}
     */
    public function retrieveRelevantContext(string $query, int $topK = 5): array
    {
        try {
            $embedding = $this->openai->createEmbedding($query);
        } catch (\Throwable $e) {
            error_log('RAG retrieveRelevantContext embedding error: ' . $e->getMessage());
            return ['critiques' => [], 'analyses' => []];
        }
        if (empty($embedding)) {
            return ['critiques' => [], 'analyses' => []];
        }

        $critiques = $this->supabase->vectorSearch('match_critique_embeddings', $embedding, $topK) ?? [];
        $analyses  = $this->supabase->vectorSearch('match_analysis_embeddings', $embedding, $topK) ?? [];

        return [
            'critiques' => $critiques,
            'analyses'  => $analyses,
        ];
    }

    /**
     * RAG 검색 결과를 시스템 프롬프트에 주입.
     */
    public function buildSystemPromptWithRAG(string $basePrompt, array $ragContext): string
    {
        $sections = [];

        if (!empty($ragContext['critiques'])) {
            $parts = [];
            foreach ($ragContext['critiques'] as $c) {
                $text = $c['chunk_text'] ?? '';
                $score = isset($c['similarity']) ? round((float) $c['similarity'], 3) : '?';
                if ($text !== '') {
                    $parts[] = "- [유사도 {$score}] {$text}";
                }
            }
            if ($parts !== []) {
                $sections[] = "## 편집자 크리틱 (과거 피드백)\n" . implode("\n", $parts);
            }
        }

        if (!empty($ragContext['analyses'])) {
            $parts = [];
            foreach ($ragContext['analyses'] as $a) {
                $text = $a['chunk_text'] ?? '';
                $score = isset($a['similarity']) ? round((float) $a['similarity'], 3) : '?';
                if ($text !== '') {
                    $parts[] = "- [유사도 {$score}] {$text}";
                }
            }
            if ($parts !== []) {
                $sections[] = "## 과거 분석 참고자료\n" . implode("\n", $parts);
            }
        }

        if ($sections === []) {
            return $basePrompt;
        }

        return $basePrompt . "\n\n--- RAG Context (편집 전문가 지식) ---\n" . implode("\n\n", $sections);
    }

    // ── Storage ─────────────────────────────────────────

    /**
     * GPT 분석 결과를 청크로 나눠 임베딩 저장.
     */
    public function storeAnalysisEmbedding(
        ?int $newsId,
        ?string $articleUrl,
        string $text,
        string $chunkType = 'analysis',
        array $metadata = []
    ): int {
        $chunks = $this->splitIntoChunks($text);
        $stored = 0;

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->openai->createEmbedding($chunk);
                if (empty($embedding)) {
                    continue;
                }
            } catch (\Throwable $e) {
                error_log('RAG storeAnalysisEmbedding embedding error: ' . $e->getMessage());
                continue;
            }
            $row = [
                'news_id'    => $newsId,
                'article_url' => $articleUrl,
                'chunk_text' => $chunk,
                'chunk_type' => $chunkType,
                'embedding'  => $embedding,
                'metadata'   => array_merge($metadata, [
                    'chunk_chars' => mb_strlen($chunk),
                ]),
            ];
            $result = $this->supabase->insert('analysis_embeddings', $row);
            if ($result !== null) {
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * 크리틱을 청크로 나눠 임베딩 저장.
     */
    public function storeCritiqueEmbedding(
        string $critiqueId,
        string $text,
        array $metadata = []
    ): int {
        $chunks = $this->splitIntoChunks($text);
        $stored = 0;

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->openai->createEmbedding($chunk);
                if (empty($embedding)) {
                    continue;
                }
            } catch (\Throwable $e) {
                error_log('RAG storeCritiqueEmbedding embedding error: ' . $e->getMessage());
                continue;
            }
            $row = [
                'critique_id' => $critiqueId,
                'chunk_text'  => $chunk,
                'embedding'   => $embedding,
                'metadata'    => array_merge($metadata, [
                    'chunk_chars' => mb_strlen($chunk),
                ]),
            ];
            $result = $this->supabase->insert('critique_embeddings', $row);
            if ($result !== null) {
                $stored++;
            }
        }

        return $stored;
    }

    // ── Helpers ──────────────────────────────────────────

    /**
     * 텍스트를 임베딩용 청크로 분할.
     *
     * @return string[]
     */
    private function splitIntoChunks(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= self::CHUNK_MAX_CHARS) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;
        $len = mb_strlen($text);

        while ($offset < $len) {
            $remaining = $len - $offset;
            if ($remaining <= self::CHUNK_MAX_CHARS) {
                $chunks[] = mb_substr($text, $offset);
                break;
            }

            $window = mb_substr($text, $offset, self::CHUNK_MAX_CHARS);
            $bestCut = -1;
            foreach (['. ', '? ', '! ', ".\n", "?\n", "!\n", "\n\n", "\n"] as $sep) {
                $p = mb_strrpos($window, $sep);
                if ($p !== false && $p > $bestCut) {
                    $bestCut = $p + mb_strlen($sep);
                }
            }

            if ($bestCut < (int) (self::CHUNK_MAX_CHARS * 0.3)) {
                foreach ([', ', ' '] as $sep) {
                    $p = mb_strrpos($window, $sep);
                    if ($p !== false && $p > $bestCut) {
                        $bestCut = $p + mb_strlen($sep);
                    }
                }
            }

            if ($bestCut <= 0) {
                $bestCut = self::CHUNK_MAX_CHARS;
            }

            $chunks[] = mb_substr($text, $offset, $bestCut);
            $offset += $bestCut;
        }

        return $chunks;
    }
}
