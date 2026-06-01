<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;

/**
 * Narrative Depth Contract — 검색 수준 깊이·길이 검증 및 synthesis 프롬프트 (3 Surface 공통)
 */
class NarrativeDepthService
{
    /** @var array<string, mixed> */
    private array $config;

    private OpenAIService $openai;

    public function __construct(?OpenAIService $openai = null)
    {
        $configPath = dirname(__DIR__, 3) . '/config/narrative_depth.php';
        $this->config = is_file($configPath) ? require $configPath : [];
        $this->openai = $openai ?? new OpenAIService([]);
    }

    public function synthesisSystemPrompt(): string
    {
        return (string) ($this->config['system_prompt_synthesis']
            ?? '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.');
    }

    public function buildSynthesisPrompt(string $context, string $topicLine): string
    {
        $topic = $topicLine !== '' ? "{$topicLine}\n\n" : '';
        return <<<PROMPT
{$topic}다음 기사 자료들을 종합 분석하라:

{$context}

분석 구조:
1. 핵심 결론을 첫 문장에 구체적으로 제시
2. 기사들의 관점을 비교 분석 (일치하는 점 vs 충돌하는 점)
3. 이 흐름이 향후 미칠 영향과 종합 판단

규칙:
- "기사1", "기사2" 등 기사 번호로 언급하지 말 것. 내용 자체로 자연스럽게 녹여서 서술
- 한국어 존댓말(~이에요, ~거든요, ~있어요)로 답변
- 마크다운 문법 사용 금지 (번호와 하이픈만 허용)
- 근거 없는 추측 금지
- 최소 3문단, 충분한 깊이로 전개
PROMPT;
    }

    /**
     * @return array{depth_score: float, passed: bool, violations: list<string>, hints: list<string>}
     */
    public function scoreScqaDepth(array $scqa): array
    {
        $minChars = $this->config['min_chars'] ?? [];
        $minParagraphs = $this->config['min_paragraphs'] ?? [];
        $violations = [];
        $checks = 0;
        $passed = 0;

        $synthesis = trim((string) ($scqa['synthesis_narrative'] ?? ''));
        if ($synthesis !== '') {
            $checks++;
            $minSyn = (int) ($minChars['synthesis_narrative'] ?? 1200);
            $minParSyn = (int) ($minParagraphs['synthesis_narrative'] ?? 3);
            if (mb_strlen($synthesis) >= $minSyn && $this->countParagraphs($synthesis) >= $minParSyn) {
                $passed++;
            } else {
                $violations[] = "synthesis_narrative: 최소 {$minSyn}자·{$minParSyn}문단 필요 (현재 "
                    . mb_strlen($synthesis) . '자·' . $this->countParagraphs($synthesis) . '문단)';
            }
        } else {
            $violations[] = 'synthesis_narrative 필드가 비어 있음';
        }

        $situation = trim((string) ($scqa['situation']['narrative'] ?? ''));
        if ($situation !== '') {
            $checks++;
            $minSit = (int) ($minChars['situation_narrative'] ?? 800);
            $minParSit = (int) ($minParagraphs['situation_narrative'] ?? 3);
            if (mb_strlen($situation) >= $minSit && $this->countParagraphs($situation) >= $minParSit) {
                $passed++;
            } else {
                $violations[] = "situation.narrative: 최소 {$minSit}자·{$minParSit}문단 필요";
            }
        }

        $exec = trim((string) ($scqa['executive_summary'] ?? ''));
        if ($exec !== '') {
            $checks++;
            $minExec = (int) ($minChars['executive_summary'] ?? 400);
            if (mb_strlen($exec) >= $minExec) {
                $passed++;
            } else {
                $violations[] = "executive_summary: 최소 {$minExec}자 필요";
            }
        }

        $collisions = $scqa['complication']['narrative_collisions'] ?? [];
        if (is_array($collisions) && $collisions !== []) {
            $minView = (int) ($minChars['collision_view'] ?? 200);
            foreach ($collisions as $i => $col) {
                if (!is_array($col)) {
                    continue;
                }
                foreach (['view_a', 'view_b', 'collision'] as $field) {
                    $text = trim((string) ($col[$field] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $checks++;
                    if (mb_strlen($text) >= $minView) {
                        $passed++;
                    } else {
                        $violations[] = "narrative_collisions[{$i}].{$field}: 최소 {$minView}자 필요";
                    }
                }
            }
        }

        $implication = trim((string) ($scqa['answer']['implication'] ?? ''));
        if ($implication !== '') {
            $checks++;
            $minImp = (int) ($minChars['implication'] ?? 120);
            if (mb_strlen($implication) >= $minImp) {
                $passed++;
            } else {
                $violations[] = "answer.implication: 최소 {$minImp}자 필요";
            }
        }

        $depthScore = $checks > 0 ? round($passed / $checks, 3) : 0.0;
        $threshold = (float) ($this->config['depth_pass_threshold'] ?? 0.7);

        return [
            'depth_score' => $depthScore,
            'passed' => $depthScore >= $threshold && $synthesis !== '',
            'violations' => $violations,
            'hints' => $this->buildDepthHints('strategic', $scqa, $violations),
        ];
    }

    /**
     * @return array{depth_score: float, passed: bool, violations: list<string>, hints: list<string>}
     */
    public function scoreGistDepth(array $gist): array
    {
        $minChars = $this->config['min_chars'] ?? [];
        $minParagraphs = $this->config['min_paragraphs'] ?? [];
        $violations = [];
        $checks = 0;
        $passed = 0;

        $synthesis = trim((string) ($gist['synthesis_narrative'] ?? ''));
        if ($synthesis !== '') {
            $checks++;
            $minSyn = (int) ($minChars['synthesis_narrative'] ?? 1200);
            $minParSyn = (int) ($minParagraphs['synthesis_narrative'] ?? 3);
            if (mb_strlen($synthesis) >= $minSyn && $this->countParagraphs($synthesis) >= $minParSyn) {
                $passed++;
            } else {
                $violations[] = "synthesis_narrative: 최소 {$minSyn}자·{$minParSyn}문단 필요";
            }
        } else {
            $violations[] = 'synthesis_narrative 필드가 비어 있음';
        }

        $macro = trim((string) ($gist['macro_so_what'] ?? ''));
        if ($macro !== '') {
            $checks++;
            $minMacro = (int) ($minChars['macro_so_what'] ?? 200);
            if (mb_strlen($macro) >= $minMacro) {
                $passed++;
            } else {
                $violations[] = "macro_so_what: 최소 {$minMacro}자 필요";
            }
        }

        $clusters = $gist['clusters'] ?? [];
        $minCluster = (int) ($minChars['cluster_narrative'] ?? 600);
        $minParCluster = (int) ($minParagraphs['cluster_narrative'] ?? 3);
        foreach ($clusters as $i => $cluster) {
            if (!is_array($cluster)) {
                continue;
            }
            $narrative = trim((string) ($cluster['narrative'] ?? ''));
            $checks++;
            if (mb_strlen($narrative) >= $minCluster && $this->countParagraphs($narrative) >= $minParCluster) {
                $passed++;
            } else {
                $violations[] = "clusters[{$i}].narrative: 최소 {$minCluster}자·{$minParCluster}문단 필요";
            }
            $perspectives = $cluster['perspectives'] ?? [];
            if (count($perspectives) < 2) {
                $violations[] = "clusters[{$i}]: perspectives 최소 2개 필요";
            }
        }

        $depthScore = $checks > 0 ? round($passed / $checks, 3) : 0.0;
        $threshold = (float) ($this->config['depth_pass_threshold'] ?? 0.7);

        return [
            'depth_score' => $depthScore,
            'passed' => $depthScore >= $threshold && $synthesis !== '',
            'violations' => $violations,
            'hints' => $this->buildDepthHints('weekly', $gist, $violations),
        ];
    }

    /**
     * @param list<string> $violations
     * @return list<string>
     */
    public function buildDepthHints(string $surface, array $draft, array $violations = []): array
    {
        $hints = [];
        foreach ($violations as $v) {
            if (str_contains($v, 'synthesis_narrative')) {
                $hints[] = 'synthesis_narrative를 검색 클러스터 분석처럼 3단 구조(결론→관점 비교→향후 영향)로 최소 1200자·3문단 이상 작성하라.';
            } elseif (str_contains($v, 'situation.narrative')) {
                $hints[] = 'situation.narrative를 4~6문단, 800자 이상으로 깊이 있게 전개하라.';
            } elseif (str_contains($v, 'executive_summary')) {
                $hints[] = 'executive_summary를 5~8문장, 400자 이상으로 확장하라.';
            } elseif (str_contains($v, 'view_a') || str_contains($v, 'view_b') || str_contains($v, 'collision')) {
                $hints[] = 'narrative_collisions의 view_a/view_b/collision 각각 2~3문장(200자 이상)으로 구체화하라.';
            } elseif (str_contains($v, 'clusters')) {
                $hints[] = '각 클러스터 narrative를 600~1200자, 3~5문단으로 관점 통합 서술을 확장하라.';
            } elseif (str_contains($v, 'macro_so_what')) {
                $hints[] = 'macro_so_what을 3~5문장, 200자 이상으로 확장하라.';
            } else {
                $hints[] = $v;
            }
        }
        if ($hints === [] && $surface === 'strategic') {
            $syn = trim((string) ($draft['synthesis_narrative'] ?? ''));
            if ($syn === '' || mb_strlen($syn) < (int) ($this->config['min_chars']['synthesis_narrative'] ?? 1200)) {
                $hints[] = 'synthesis_narrative를 반드시 포함하고 검색 분석 수준의 깊이로 작성하라.';
            }
        }
        return array_values(array_unique($hints));
    }

    public function generateSynthesisNarrative(string $context, string $topicLine): ?string
    {
        if (!$this->openai->isConfigured()) {
            return null;
        }
        $system = $this->synthesisSystemPrompt();
        $user = $this->buildSynthesisPrompt($context, $topicLine);
        $model = (string) ($this->config['model'] ?? 'gpt-5.2');
        if (str_contains($model, 'gpt-5')) {
            // keep as configured
        }
        try {
            $raw = $this->openai->chat($system, $user, [
                'model' => $model,
                'temperature' => (float) ($this->config['temperature'] ?? 0.5),
                'max_tokens' => (int) ($this->config['max_tokens']['synthesis'] ?? 2500),
                'timeout' => 120,
            ]);
            $text = trim($raw);
            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            error_log('NarrativeDepthService generateSynthesisNarrative: ' . $e->getMessage());
            return null;
        }
    }

    public function extractSynthesisFromScqa(array $scqa): string
    {
        $syn = trim((string) ($scqa['synthesis_narrative'] ?? ''));
        if ($syn !== '') {
            return $syn;
        }
        $parts = [];
        if ($exec = trim((string) ($scqa['executive_summary'] ?? ''))) {
            $parts[] = $exec;
        }
        if ($sit = trim((string) ($scqa['situation']['narrative'] ?? ''))) {
            $parts[] = $sit;
        }
        if ($imp = trim((string) ($scqa['answer']['implication'] ?? ''))) {
            $parts[] = $imp;
        }
        return implode("\n\n", $parts);
    }

    public function extractSynthesisFromGist(array $gist): string
    {
        $syn = trim((string) ($gist['synthesis_narrative'] ?? ''));
        if ($syn !== '') {
            return $syn;
        }
        $parts = [];
        if ($macro = trim((string) ($gist['macro_so_what'] ?? ''))) {
            $parts[] = $macro;
        }
        foreach ($gist['clusters'] ?? [] as $cluster) {
            if (!is_array($cluster)) {
                continue;
            }
            $n = trim((string) ($cluster['narrative'] ?? ''));
            if ($n !== '') {
                $parts[] = $n;
            }
        }
        return implode("\n\n", $parts);
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function countParagraphs(string $text): int
    {
        $chunks = preg_split('/\n\s*\n/u', trim($text)) ?: [];
        $chunks = array_filter($chunks, fn($c) => trim((string) $c) !== '');
        return max(1, count($chunks));
    }
}
