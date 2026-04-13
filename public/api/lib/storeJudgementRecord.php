<?php
/**
 * Judgement Layer (관찰 모드): AI 원본 vs 에디터 최종본 시맨틱 diff 수집.
 * news.php 게시 직후 또는 backfill 배치에서 호출. 실패해도 게시/배치 성공에는 영향 없음.
 */

declare(strict_types=1);

/**
 * 게시/백필용: judgement_records 저장 + judgement_patterns 누적.
 *
 * @param int $newsId MySQL news.id
 * @param array<string,mixed> $aiOutput 프론트 aiResult 또는 analysis_feedback.gpt_analysis
 * @param array{title?:string,narration?:string|null,why_important?:string|null,content?:string} $humanOutput
 * @param 'publish'|'backfill' $source
 */
function storeJudgementRecord(int $newsId, array $aiOutput, array $humanOutput, string $source = 'publish'): void
{
    if ($newsId < 1) {
        return;
    }

    $projectRoot = judgementFindProjectRoot();
    if ($projectRoot === null || !file_exists($projectRoot . 'src/agents/autoload.php')) {
        return;
    }

    require_once $projectRoot . 'src/agents/autoload.php';

    $supabaseConfig = file_exists($projectRoot . 'config/supabase.php')
        ? require $projectRoot . 'config/supabase.php'
        : [];
    $openaiConfig = file_exists($projectRoot . 'config/openai.php')
        ? require $projectRoot . 'config/openai.php'
        : [];
    if (file_exists($projectRoot . 'config/agents.php')) {
        $agentsConfig = require $projectRoot . 'config/agents.php';
        $openaiConfig = array_merge($openaiConfig, $agentsConfig['agents']['analysis'] ?? []);
    }

    try {
        $openai = new \Agents\Services\OpenAIService($openaiConfig);
        $supabase = new \Agents\Services\SupabaseService($supabaseConfig);
        if (!$supabase->isConfigured() || !$openai->isConfigured()) {
            return;
        }

        if ($source === 'backfill' && judgementRecordExistsForNewsId($supabase, $newsId)) {
            return;
        }

        $aiSanitized = judgementSanitizeJson($aiOutput);
        $humanSanitized = judgementSanitizeJson($humanOutput);

        $aiText = judgementBuildAiCompareText($aiSanitized);
        $humanText = judgementBuildHumanCompareText($humanSanitized);

        if ($aiText === '' || mb_strlen($aiText) < 10) {
            return;
        }

        $semanticDiff = judgementExtractSemanticDiff($openai, $aiText, $humanText);

        $row = [
            'news_id' => $newsId,
            'ai_output' => $aiSanitized,
            'human_output' => $humanSanitized,
            'semantic_diff' => $semanticDiff,
            'source' => $source,
        ];

        $inserted = $supabase->insert('judgement_records', $row);
        if ($inserted === null) {
            error_log('[storeJudgementRecord] insert failed: ' . $supabase->getLastError());
            return;
        }

        judgementUpsertPatterns($supabase, $semanticDiff);
    } catch (\Throwable $e) {
        error_log('[storeJudgementRecord] news_id=' . $newsId . ' error: ' . $e->getMessage());
    }
}

/**
 * 해당 기사에 judgement 기록이 이미 있으면 true (백필 중복 방지).
 */
function judgementRecordExistsForNewsId(\Agents\Services\SupabaseService $supabase, int $newsId): bool
{
    $rows = $supabase->select('judgement_records', 'news_id=eq.' . $newsId, 1);
    return is_array($rows) && $rows !== [];
}

/**
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function judgementSanitizeJson(array $data): array
{
    $out = [];
    foreach ($data as $k => $v) {
        if (!is_string($k) || strlen($k) > 200) {
            continue;
        }
        if (is_string($v)) {
            $out[$k] = mb_substr($v, 0, 80000);
        } elseif (is_array($v)) {
            $out[$k] = judgementSanitizeJson($v);
        } elseif (is_bool($v) || is_int($v) || is_float($v) || $v === null) {
            $out[$k] = $v;
        }
    }
    return $out;
}

/**
 * @param array<string,mixed> $ai
 */
function judgementBuildAiCompareText(array $ai): string
{
    $parts = [];
    $title = trim((string) ($ai['news_title'] ?? ''));
    if ($title !== '') {
        $parts[] = '[제목] ' . $title;
    }
    $narr = trim((string) ($ai['narration'] ?? ''));
    if ($narr === '' && isset($ai['translation_summary'])) {
        $narr = trim((string) $ai['translation_summary']);
    }
    if ($narr !== '') {
        $parts[] = '[내레이션] ' . $narr;
    }
    $why = '';
    if (isset($ai['critical_analysis']) && is_array($ai['critical_analysis'])) {
        $why = trim((string) ($ai['critical_analysis']['why_important'] ?? ''));
    }
    if ($why === '' && isset($ai['geopolitical_implication'])) {
        $why = trim((string) $ai['geopolitical_implication']);
    }
    if ($why !== '') {
        $parts[] = '[왜 중요한가/함의] ' . $why;
    }
    $body = trim((string) ($ai['content_summary'] ?? ''));
    if ($body !== '') {
        $parts[] = '[본문 요약] ' . mb_substr($body, 0, 4000);
    }
    $kps = $ai['key_points'] ?? null;
    if (is_array($kps) && $kps !== []) {
        $slice = array_slice($kps, 0, 12);
        $parts[] = '[주요 포인트] ' . implode(' | ', array_map(static fn ($p) => (string) $p, $slice));
    }
    return implode("\n\n", $parts);
}

/**
 * @param array<string,mixed> $h
 */
function judgementBuildHumanCompareText(array $h): string
{
    $parts = [];
    $title = trim((string) ($h['title'] ?? ''));
    if ($title !== '') {
        $parts[] = '[제목] ' . $title;
    }
    $narr = trim((string) ($h['narration'] ?? ''));
    if ($narr !== '') {
        $parts[] = '[내레이션] ' . $narr;
    }
    $why = trim((string) ($h['why_important'] ?? ''));
    if ($why !== '') {
        $parts[] = '[왜 중요한가] ' . $why;
    }
    $content = trim(strip_tags((string) ($h['content'] ?? '')));
    if ($content !== '') {
        $parts[] = '[본문] ' . mb_substr($content, 0, 4000);
    }
    return implode("\n\n", $parts);
}

/**
 * @return array<string,mixed>
 */
function judgementExtractSemanticDiff(\Agents\Services\OpenAIService $openai, string $aiText, string $humanText): array
{
    $system = <<<'SYS'
당신은 뉴스 편집 분석가입니다. AI가 생성한 초안 텍스트와 편집장이 확정한 최종 텍스트를 비교하여, 편집 과정에서 드러난 "판단 패턴"만 추출합니다.
반드시 요청된 JSON 형식으로만 응답하세요. 추측은 하되, 두 텍스트에 근거가 없는 내용은 넣지 마세요.
SYS;

    $user = <<<USER
[AI 초안]
{$aiText}

[편집장 최종본]
{$humanText}

다음 JSON만 출력하세요 (한국어):
{
  "judgement_patterns": [
    {
      "category": "짧은 분류 (예: tone, risk, structure, emphasis, addition, removal)",
      "description": "어떤 판단 변화가 있었는지 한 문장",
      "ai_approach": "AI 쪽 경향 요약 (짧게)",
      "editor_correction": "편집장 쪽 경향 요약 (짧게)"
    }
  ],
  "overall_direction": "전체 편집 방향 한 문장 요약"
}

패턴은 최대 8개까지. 의미 있는 차이가 거의 없으면 judgement_patterns는 빈 배열로 두세요.
USER;

    $raw = $openai->chat($system, $user, [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.2,
        'max_tokens' => 1200,
        'timeout' => 90,
        'json_mode' => true,
    ]);

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['parse_error' => true, 'raw_preview' => mb_substr($raw, 0, 500)];
    }

    return $decoded;
}

/**
 * @param array<string,mixed> $semanticDiff
 */
function judgementUpsertPatterns(\Agents\Services\SupabaseService $supabase, array $semanticDiff): void
{
    $patterns = $semanticDiff['judgement_patterns'] ?? null;
    if (!is_array($patterns)) {
        return;
    }

    foreach ($patterns as $p) {
        if (!is_array($p)) {
            continue;
        }
        $category = trim((string) ($p['category'] ?? 'general'));
        $description = trim((string) ($p['description'] ?? ''));
        if ($description === '') {
            continue;
        }
        $aiApp = isset($p['ai_approach']) ? trim((string) $p['ai_approach']) : null;
        $edCor = isset($p['editor_correction']) ? trim((string) $p['editor_correction']) : null;

        $hash = hash('sha256', $category . "\0" . mb_strtolower($description));

        $existing = $supabase->select('judgement_patterns', 'pattern_hash=eq.' . $hash, 1);
        if (is_array($existing) && $existing !== [] && isset($existing[0]['id'])) {
            $id = $existing[0]['id'];
            $freq = (int) ($existing[0]['frequency'] ?? 0) + 1;
            $weight = min(1.0, $freq / 50.0);
            $supabase->update('judgement_patterns', 'id=eq.' . rawurlencode((string) $id), [
                'frequency' => $freq,
                'weight' => $weight,
                'last_seen_at' => date('c'),
                'ai_approach' => $aiApp,
                'editor_correction' => $edCor,
            ]);
        } else {
            $supabase->insert('judgement_patterns', [
                'pattern_hash' => $hash,
                'category' => mb_substr($category, 0, 200),
                'description' => mb_substr($description, 0, 2000),
                'ai_approach' => $aiApp !== null ? mb_substr($aiApp, 0, 4000) : null,
                'editor_correction' => $edCor !== null ? mb_substr($edCor, 0, 4000) : null,
                'frequency' => 1,
                'weight' => min(1.0, 1 / 50.0),
                'is_active' => true,
                'last_seen_at' => date('c'),
            ]);
        }
    }
}

function judgementFindProjectRoot(): ?string
{
    $candidates = [
        __DIR__ . '/../../../',
        __DIR__ . '/../../../../',
        __DIR__ . '/../../',
    ];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    return null;
}
