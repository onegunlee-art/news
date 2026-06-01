<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use Agents\Services\RAGService;
use PDO;

/**
 * Lesson Card — transferable house rules from human edits (Admin Moat layer).
 * Matching: structure tags first; embedding is routing assist only.
 */
class JudgmentLessonService
{
    private PDO $pdo;
    private OpenAIService $openai;
    private ?RAGService $rag;
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(PDO $pdo, ?OpenAIService $openai = null, ?RAGService $rag = null)
    {
        $this->pdo = $pdo;
        $this->openai = $openai ?? new OpenAIService([]);
        $this->rag = $rag;
        $configPath = dirname(__DIR__, 3) . '/config/judgment_lessons.php';
        $this->config = is_file($configPath) ? require $configPath : [];
    }

    public function ensureTable(): void
    {
        $sqlFile = dirname(__DIR__, 3) . '/database/migrations/add_judgment_moat.sql';
        if (!is_file($sqlFile)) {
            return;
        }
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return;
        }
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
            if ($statement !== '' && stripos($statement, 'CREATE TABLE') !== false
                && stripos($statement, 'judgment_lessons') !== false) {
                try {
                    $this->pdo->exec($statement);
                } catch (\Throwable $e) {
                    error_log('JudgmentLessonService ensureTable: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForVerification(string $scqaSection, string $topicCategory = '*'): array
    {
        $this->ensureTable();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, rule, error_type, scqa_section, topic_category, polarity, frequency, status
                 FROM judgment_lessons
                 WHERE status IN ('rag', 'promoted')
                   AND (scqa_section = :section OR scqa_section = '*')
                   AND (topic_category = :topic OR topic_category = '*')
                 ORDER BY status = 'promoted' DESC, frequency DESC, id DESC
                 LIMIT 12"
            );
            $stmt->execute(['section' => $scqaSection, 'topic' => $topicCategory]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log('JudgmentLessonService findForVerification: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Collect lessons relevant to an SCQA draft (multiple sections).
     *
     * @return list<array<string, mixed>>
     */
    public function collectLessonsForScqa(array $scqa): array
    {
        $sections = [
            'answer.scenarios',
            'complication.narrative_collisions',
            'complication.perspectives',
            'structural_shift',
            'answer.implication',
        ];
        $seen = [];
        $all = [];
        foreach ($sections as $section) {
            foreach ($this->findForVerification($section) as $lesson) {
                $id = (int) ($lesson['id'] ?? 0);
                if ($id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $all[] = $lesson;
                }
            }
        }
        return $all;
    }

    /**
     * Rule-based violation hints for verifyScqa / self-correction (no extra LLM by default).
     *
     * @return list<string>
     */
    public function buildViolationHints(array $scqa, array $lessons): array
    {
        if ($lessons === []) {
            return [];
        }
        $hints = [];
        $scenarios = $scqa['answer']['scenarios'] ?? [];
        $scqaText = json_encode($scqa, JSON_UNESCAPED_UNICODE);

        foreach ($lessons as $lesson) {
            $rule = trim((string) ($lesson['rule'] ?? ''));
            $errorType = (string) ($lesson['error_type'] ?? '');
            $section = (string) ($lesson['scqa_section'] ?? '*');
            if ($rule === '') {
                continue;
            }

            if ($errorType === '확신_과잉' && $section === 'answer.scenarios') {
                foreach ($scenarios as $scenario) {
                    $prob = (int) ($scenario['probability'] ?? 0);
                    $outcome = (string) ($scenario['outcome'] ?? '');
                    if ($prob >= 75 && preg_match('/(반드시|확실|틀림없)/u', $outcome)) {
                        $hints[] = "[Lesson #{$lesson['id']}] {$rule}";
                        break;
                    }
                }
            } elseif ($errorType === '인과_비약' && str_contains($section, 'answer')) {
                $chain = $scqa['answer']['why_it_matters_chain'] ?? [];
                if (is_array($chain) && count($chain) < 3) {
                    $hints[] = "[Lesson #{$lesson['id']}] {$rule}";
                }
            } elseif ($errorType === '관점_편향' && str_contains($section, 'complication')) {
                $collisions = count($scqa['complication']['narrative_collisions'] ?? []);
                if ($collisions < 2) {
                    $hints[] = "[Lesson #{$lesson['id']}] {$rule}";
                }
            } elseif (mb_strlen($rule) > 10 && !in_array($rule, $hints, true)) {
                // Promoted rules always surface as soft hints
                if (($lesson['status'] ?? '') === 'promoted') {
                    $hints[] = "[Promoted] {$rule}";
                }
            }
        }

        if ($hints === [] && $lessons !== []) {
            // Fallback: include top promoted rule as rubric reminder
            foreach ($lessons as $lesson) {
                if (($lesson['status'] ?? '') === 'promoted') {
                    $hints[] = '[House rule] ' . ($lesson['rule'] ?? '');
                    break;
                }
            }
        }

        return array_values(array_unique($hints));
    }

    /**
     * Depth contract violations mapped to lesson-style hints.
     *
     * @param array{depth_score?: float, passed?: bool, violations?: list<string>, hints?: list<string>} $depthResult
     * @return list<string>
     */
    public function buildDepthViolationHints(array $scqa, array $depthResult): array
    {
        if ($depthResult['passed'] ?? false) {
            return [];
        }
        $hints = [];
        foreach ($depthResult['violations'] ?? [] as $violation) {
            if (str_contains($violation, 'synthesis_narrative')) {
                $hints[] = '[서사_과축약] synthesis_narrative를 검색 분석 수준(1200자·3문단·3단 구조)으로 확장하라.';
            } elseif (str_contains($violation, 'situation.narrative')) {
                $hints[] = '[서사_과축약] situation.narrative를 4~6문단, 800자 이상으로 깊이 있게 전개하라.';
            } elseif (str_contains($violation, 'view_a') || str_contains($violation, 'view_b') || str_contains($violation, 'collision')) {
                $hints[] = '[충돌_피상적] narrative_collisions의 view_a/view_b/collision을 각 200자·2~3문장 이상으로 구체화하라.';
            } elseif (str_contains($violation, 'executive_summary')) {
                $hints[] = '[서사_과축약] executive_summary를 5~8문장, 400자 이상으로 확장하라.';
            }
        }
        foreach ($depthResult['hints'] ?? [] as $hint) {
            if (!in_array($hint, $hints, true)) {
                $hints[] = $hint;
            }
        }
        if ($hints === []) {
            $hints[] = '[서사_과축약] 전체 서사 분량을 검색 클러스터 분석 수준으로 확장하라.';
        }
        return $hints;
    }

    public function isJudgmentEdit(string $path, mixed $before, mixed $after): bool
    {
        $cosmeticPatterns = $this->config['cosmetic_path_patterns'] ?? [];
        foreach ($cosmeticPatterns as $pattern) {
            if (@preg_match($pattern, $path) === 1) {
                return false;
            }
        }

        $keywords = $this->config['judgment_path_keywords'] ?? [];
        foreach ($keywords as $kw) {
            if (stripos($path, $kw) !== false) {
                return true;
            }
        }

        $beforeStr = is_array($before) ? json_encode($before, JSON_UNESCAPED_UNICODE) : (string) $before;
        $afterStr = is_array($after) ? json_encode($after, JSON_UNESCAPED_UNICODE) : (string) $after;
        if ($beforeStr === $afterStr) {
            return false;
        }

        // Substantive text change (>30 chars delta in conclusion-like fields)
        if (mb_strlen($beforeStr) > 30 || mb_strlen($afterStr) > 30) {
            return preg_match('/(answer|complication|structural_shift|scenario|collision|perspective)/i', $path) === 1;
        }

        return false;
    }

    /**
     * @return array{rule: string, error_type: string, scqa_section: string, polarity: string}|null
     */
    public function extractPrinciple(string $path, mixed $before, mixed $after): ?array
    {
        if (!$this->openai->isConfigured()) {
            return null;
        }

        $beforeStr = is_array($before) ? json_encode($before, JSON_UNESCAPED_UNICODE) : (string) $before;
        $afterStr = is_array($after) ? json_encode($after, JSON_UNESCAPED_UNICODE) : (string) $after;
        if (mb_strlen($beforeStr) > 1500) {
            $beforeStr = mb_substr($beforeStr, 0, 1500) . '…';
        }
        if (mb_strlen($afterStr) > 1500) {
            $afterStr = mb_substr($afterStr, 0, 1500) . '…';
        }

        $system = '당신은 the gist 편집 원칙 추출기입니다. 편집 diff에서 **전이 가능한 일반 규칙**만 JSON으로 추출합니다.';
        $user = <<<PROMPT
SCQA 경로: {$path}

변경 전:
{$beforeStr}

변경 후:
{$afterStr}

다음 JSON만 출력:
{
  "rule": "다른 주제에도 적용 가능한 일반 규칙 1문장 (수정 문장 그대로 복사 금지)",
  "error_type": "확신_과잉|출처_누락|인과_비약|관점_편향|어투_이탈|시점_혼동|general",
  "scqa_section": "answer.scenarios|complication.narrative_collisions|complication.perspectives|structural_shift|answer.implication|*",
  "polarity": "tighten|loosen|neutral"
}

cosmetic 편집(오타·문장 순서만)이면 {"skip": true} 만 출력.
PROMPT;

        try {
            $raw = $this->openai->chat($system, $user, [
                'model' => (string) ($this->config['extraction_model'] ?? 'gpt-4o-mini'),
                'temperature' => (float) ($this->config['extraction_temperature'] ?? 0.2),
                'max_tokens' => 500,
                'json_mode' => true,
                'timeout' => 60,
            ]);
            $data = json_decode($raw, true);
            if (!is_array($data) || !empty($data['skip'])) {
                return null;
            }
            $rule = trim((string) ($data['rule'] ?? ''));
            if ($rule === '' || mb_strlen($rule) < 12) {
                return null;
            }
            return [
                'rule' => $rule,
                'error_type' => (string) ($data['error_type'] ?? 'general'),
                'scqa_section' => $this->normalizeScqaSection((string) ($data['scqa_section'] ?? '*'), $path),
                'polarity' => in_array($data['polarity'] ?? '', ['tighten', 'loosen', 'neutral'], true)
                    ? $data['polarity'] : 'tighten',
            ];
        } catch (\Throwable $e) {
            error_log('JudgmentLessonService extractPrinciple: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array{rule: string, error_type: string, scqa_section: string, polarity: string} $principle
     * @param array<string, mixed> $evidence
     */
    public function upsertLesson(array $principle, array $evidence, ?int $reportId = null): ?int
    {
        $this->ensureTable();
        $rule = trim($principle['rule']);
        $section = $principle['scqa_section'] ?? '*';
        $errorType = $principle['error_type'] ?? 'general';

        try {
            $find = $this->pdo->prepare(
                'SELECT id, frequency FROM judgment_lessons
                 WHERE rule = :rule AND scqa_section = :section AND error_type = :etype LIMIT 1'
            );
            $find->execute(['rule' => $rule, 'section' => $section, 'etype' => $errorType]);
            $existing = $find->fetch();

            $promoteAt = (int) ($this->config['promotion_frequency'] ?? 3);

            if ($existing) {
                $newFreq = (int) $existing['frequency'] + 1;
                $status = $newFreq >= $promoteAt ? 'promoted' : 'rag';
                $upd = $this->pdo->prepare(
                    'UPDATE judgment_lessons SET frequency = :freq, status = :status,
                     evidence_json = :evidence, source_report_id = :rid, updated_at = NOW()
                     WHERE id = :id'
                );
                $upd->execute([
                    'freq' => $newFreq,
                    'status' => $status,
                    'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
                    'rid' => $reportId,
                    'id' => (int) $existing['id'],
                ]);
                $lessonId = (int) $existing['id'];
            } else {
                $ins = $this->pdo->prepare(
                    'INSERT INTO judgment_lessons
                     (rule, error_type, scqa_section, topic_category, polarity, evidence_json, frequency, status, source_report_id)
                     VALUES (:rule, :etype, :section, :topic, :polarity, :evidence, 1, :status, :rid)'
                );
                $ins->execute([
                    'rule' => $rule,
                    'etype' => $errorType,
                    'section' => $section,
                    'topic' => '*',
                    'polarity' => $principle['polarity'] ?? 'tighten',
                    'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
                    'status' => 'rag',
                    'rid' => $reportId,
                ]);
                $lessonId = (int) $this->pdo->lastInsertId();
            }

            if ($this->rag !== null && $this->rag->isConfigured()) {
                $critiqueId = 'lesson_' . $lessonId . '_' . time();
                $this->rag->storeCritiqueEmbedding($critiqueId, $rule, [
                    'type' => 'judgment_lesson',
                    'lesson_id' => $lessonId,
                    'error_type' => $errorType,
                    'scqa_section' => $section,
                ]);
            }

            return $lessonId;
        } catch (\Throwable $e) {
            error_log('JudgmentLessonService upsertLesson: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process edit diffs → Lesson Cards.
     *
     * @return array{lessons_stored: int, skipped: int, errors: list<string>}
     */
    public function processEditDiffs(int $reportId, array $editDiff): array
    {
        $stored = 0;
        $skipped = 0;
        $errors = [];

        foreach ($editDiff as $diff) {
            $path = (string) ($diff['path'] ?? '');
            $before = $diff['before'] ?? null;
            $after = $diff['after'] ?? null;

            if (!$this->isJudgmentEdit($path, $before, $after)) {
                $skipped++;
                continue;
            }

            $principle = $this->extractPrinciple($path, $before, $after);
            if ($principle === null) {
                $skipped++;
                continue;
            }

            $evidence = [
                'path' => $path,
                'before' => $before,
                'after' => $after,
                'report_id' => $reportId,
            ];

            $id = $this->upsertLesson($principle, $evidence, $reportId);
            if ($id !== null) {
                $stored++;
            } else {
                $errors[] = "Failed to store lesson for path: {$path}";
            }
        }

        return ['lessons_stored' => $stored, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function normalizeScqaSection(string $section, string $path): string
    {
        if ($section !== '' && $section !== '*') {
            return $section;
        }
        if (str_contains($path, 'scenario')) {
            return 'answer.scenarios';
        }
        if (str_contains($path, 'collision')) {
            return 'complication.narrative_collisions';
        }
        if (str_contains($path, 'perspective')) {
            return 'complication.perspectives';
        }
        if (str_contains($path, 'structural_shift')) {
            return 'structural_shift';
        }
        if (str_contains($path, 'implication') || str_contains($path, 'why_it_matters')) {
            return 'answer.implication';
        }
        return '*';
    }
}
