<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use Agents\Services\SupabaseService;
use PDO;

/**
 * 검색 클러스터 분석 — search-analysis.php와 동일한 프롬프트·컨텍스트 (Admin 저장용)
 */
class SearchAnalysisService
{
    private OpenAIService $openai;
    private SupabaseService $supabase;
    private PDO $pdo;

    public function __construct(PDO $pdo, ?OpenAIService $openai = null, ?SupabaseService $supabase = null)
    {
        $this->pdo = $pdo;
        $projectRoot = dirname(__DIR__, 3) . '/';
        $openaiConfig = require $projectRoot . 'config/openai.php';
        $this->openai = $openai ?? new OpenAIService($openaiConfig);
        $this->supabase = $supabase ?? new SupabaseService([]);
    }

    /**
     * @param list<int> $newsIds
     * @return array{
     *   blocks: list<string>,
     *   articles: array<int, array<string, mixed>>,
     *   entities: list<string>,
     *   topic_labels: list<string>,
     *   titles: array<int, string>
     * }
     */
    public function buildClusterContext(array $newsIds): array
    {
        $newsIds = array_values(array_filter(array_map('intval', $newsIds), fn ($id) => $id > 0));
        if ($newsIds === []) {
            throw new \InvalidArgumentException('news_ids required');
        }

        $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT id, title, why_important, narration, description FROM news WHERE id IN ({$placeholders})"
        );
        $st->execute($newsIds);
        $articles = [];
        $titles = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            $articles[$id] = $row;
            $titles[$id] = (string) ($row['title'] ?? '');
        }

        $chunks = [];
        $entities = [];
        $topicLabels = [];
        if ($this->supabase->isConfigured()) {
            foreach ($newsIds as $nid) {
                $rows = $this->supabase->select(
                    'analysis_embeddings',
                    'select=chunk_text,metadata&news_id=eq.' . $nid . '&order=created_at.asc',
                    5
                );
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $chunks[$nid][] = $r;
                        $meta = $r['metadata'] ?? [];
                        if (is_array($meta)) {
                            foreach ($meta['entities'] ?? [] as $ent) {
                                $ent = trim((string) $ent);
                                if ($ent !== '') {
                                    $entities[$ent] = true;
                                }
                            }
                            $tl = trim((string) ($meta['topic_label'] ?? ''));
                            if ($tl !== '') {
                                $topicLabels[$tl] = true;
                            }
                        }
                    }
                }
            }
        }

        $articleBlocks = [];
        foreach ($newsIds as $nid) {
            $art = $articles[$nid] ?? null;
            if (!$art) {
                continue;
            }
            $whyImportant = trim((string) ($art['why_important'] ?? ''));
            $narration = trim((string) ($art['narration'] ?? ''));

            $chunkSummary = '';
            if (!empty($chunks[$nid])) {
                $topChunk = $chunks[$nid][0];
                $chunkSummary = mb_substr(trim((string) ($topChunk['chunk_text'] ?? '')), 0, 800);
            }

            $block = '---';
            if ($whyImportant !== '') {
                $block .= "\n핵심: {$whyImportant}";
            }
            if ($chunkSummary !== '') {
                $block .= "\n분석: {$chunkSummary}";
            } elseif ($narration !== '') {
                $block .= "\n분석: " . mb_substr($narration, 0, 800);
            }
            $articleBlocks[] = $block;
        }

        return [
            'blocks' => $articleBlocks,
            'articles' => $articles,
            'entities' => array_keys($entities),
            'topic_labels' => array_keys($topicLabels),
            'titles' => $titles,
        ];
    }

    public function buildPrompt(string $clusterName, array $articleBlocks): string
    {
        $articleText = implode("\n\n", $articleBlocks);
        $topicLine = $clusterName !== '' ? "주제: \"{$clusterName}\"\n\n" : '';

        return <<<PROMPT
{$topicLine}다음 기사 자료들을 종합 분석하라:

{$articleText}

분석 구조:
1. 핵심 결론을 첫 문장에 구체적으로 제시
2. 기사들의 관점을 비교 분석 (일치하는 점 vs 충돌하는 점)
3. 이 흐름이 향후 미칠 영향과 종합 판단

규칙:
- "기사1", "기사2" 등 기사 번호로 언급하지 말 것. 내용 자체로 자연스럽게 녹여서 서술
- 한국어 존댓말(~이에요, ~거든요, ~있어요)로 답변
- 마크다운 문법 사용 금지 (번호와 하이픈만 허용)
- 근거 없는 추측 금지
PROMPT;
    }

    /**
     * @param list<int> $newsIds
     */
    public function generateAnalysis(string $clusterName, array $newsIds): string
    {
        if (!$this->openai->isConfigured()) {
            throw new \RuntimeException('OpenAI not configured');
        }
        $ctx = $this->buildClusterContext($newsIds);
        if ($ctx['blocks'] === []) {
            throw new \RuntimeException('No articles found');
        }
        $systemPrompt = '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.';
        $userPrompt = $this->buildPrompt($clusterName, $ctx['blocks']);
        $projectRoot = dirname(__DIR__, 3) . '/';
        $openaiCfg = require $projectRoot . 'config/openai.php';

        return $this->openai->chat($systemPrompt, $userPrompt, [
            'max_tokens' => 2000,
            'timeout' => 120,
            'temperature' => 0.5,
            'model' => $openaiCfg['model'] ?? 'gpt-4o-mini',
        ]);
    }

    public function systemPrompt(): string
    {
        return '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.';
    }
}
