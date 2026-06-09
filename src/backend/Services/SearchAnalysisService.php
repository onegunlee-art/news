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

    /**
     * Partner API v2 — 구조화 분석 + full_text 하위 호환
     *
     * @param list<int> $newsIds
     * @return array{
     *   full_text: string,
     *   synthesis: string,
     *   alignment_points: list<string>,
     *   conflict_points: list<string>,
     *   outlook: string
     * }
     */
    public function generatePartnerAnalysis(string $clusterName, array $newsIds): array
    {
        if (!$this->openai->isConfigured()) {
            throw new \RuntimeException('OpenAI not configured');
        }
        $ctx = $this->buildClusterContext($newsIds);
        if ($ctx['blocks'] === []) {
            throw new \RuntimeException('No articles found');
        }

        $projectRoot = dirname(__DIR__, 3) . '/';
        $openaiCfg = require $projectRoot . 'config/openai.php';
        $articleText = implode("\n\n", $ctx['blocks']);
        $topicLine = $clusterName !== '' ? "주제: \"{$clusterName}\"\n\n" : '';

        $structuredPrompt = <<<PROMPT
{$topicLine}다음 기사 자료들을 종합 분석하라:

{$articleText}

JSON만 출력:
{
  "synthesis": "핵심 결론 한 문장",
  "alignment_points": ["기사들이 공통으로 보는 관점 1", "관점 2"],
  "conflict_points": ["기사들이 갈리는 관점 1", "관점 2"],
  "outlook": "향후 영향과 종합 판단 (2~4문장)",
  "full_text": "전체 분석 산문. synthesis → 일치/충돌 비교 → outlook 순서. 한국어 존댓말. 마크다운 금지."
}

규칙:
- alignment_points: 2~4개, 기사들이 일치하거나 공유하는 관점
- conflict_points: 2~4개, 기사들이 충돌하거나 대립하는 관점
- "기사1" 등 번호 언급 금지, 근거 없는 추측 금지
PROMPT;

        try {
            $raw = $this->openai->chat(
                '당신은 뉴스 분석 전문 AI입니다. 반드시 JSON만 출력합니다.',
                $structuredPrompt,
                [
                    'max_tokens' => 2200,
                    'timeout' => 120,
                    'temperature' => 0.5,
                    'json_mode' => true,
                    'model' => $openaiCfg['model'] ?? 'gpt-4o-mini',
                ]
            );
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $fullText = trim((string) ($data['full_text'] ?? ''));
                $synthesis = trim((string) ($data['synthesis'] ?? ''));
                $alignment = self::normalizeStringList($data['alignment_points'] ?? []);
                $conflict = self::normalizeStringList($data['conflict_points'] ?? []);
                $outlook = trim((string) ($data['outlook'] ?? ''));

                if ($fullText === '' && ($synthesis !== '' || $alignment !== [] || $conflict !== [])) {
                    $parts = [];
                    if ($synthesis !== '') {
                        $parts[] = $synthesis;
                    }
                    if ($alignment !== []) {
                        $parts[] = '일치하는 점: ' . implode(' ', $alignment);
                    }
                    if ($conflict !== []) {
                        $parts[] = '충돌하는 점: ' . implode(' ', $conflict);
                    }
                    if ($outlook !== '') {
                        $parts[] = $outlook;
                    }
                    $fullText = implode("\n\n", $parts);
                }

                if ($fullText !== '') {
                    return [
                        'full_text' => $fullText,
                        'synthesis' => $synthesis,
                        'alignment_points' => $alignment,
                        'conflict_points' => $conflict,
                        'outlook' => $outlook,
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[SearchAnalysisService] partner structured analysis failed: ' . $e->getMessage());
        }

        $systemPrompt = '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.';
        $userPrompt = $this->buildPrompt($clusterName, $ctx['blocks']);
        $fullText = $this->openai->chat($systemPrompt, $userPrompt, [
            'max_tokens' => 2000,
            'timeout' => 120,
            'temperature' => 0.5,
            'model' => $openaiCfg['model'] ?? 'gpt-4o-mini',
        ]);

        return [
            'full_text' => $fullText,
            'synthesis' => '',
            'alignment_points' => [],
            'conflict_points' => [],
            'outlook' => '',
        ];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function normalizeStringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }

    public function systemPrompt(): string
    {
        return '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.';
    }
}
