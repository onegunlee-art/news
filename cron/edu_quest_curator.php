<?php
/**
 * GIST EDU — Quest Curator
 * 수, 토, 일 새벽 3시 실행 (crontab: 0 3 * * 0,3,6)
 * 
 * the gist 기사 풀에서 충돌점 2개 이상인 기사를 발굴하여 퀘스트 후보 생성
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/agents/autoload.php';
require_once $projectRoot . '/public/api/edu/lib/bootstrap.php';
require_once $projectRoot . '/public/api/edu/lib/_llm.php';

function curatorLog(string $msg, $data = null): void {
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($logDir . '/edu_quest_curator.log', $line . "\n", FILE_APPEND | LOCK_EX);
    echo $line . "\n";
}

curatorLog('Quest Curator started');

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    curatorLog('ERROR: Supabase not configured');
    exit(1);
}

$articles = $supabase->select(
    'news',
    'status=eq.published&ai_summary=not.is.null&order=published_at.desc',
    50
);

curatorLog('Fetched articles', ['count' => count($articles)]);

$llm = eduLlm();
$candidates = [];

foreach ($articles as $article) {
    $newsId = $article['id'] ?? null;
    $title = $article['title'] ?? '';
    $aiSummary = $article['ai_summary'] ?? '';
    
    if (empty($newsId) || empty($aiSummary)) continue;
    
    $existing = $supabase->select(
        'edu_quest_articles',
        'news_id=eq.' . $newsId,
        1
    );
    if (!empty($existing)) continue;
    
    $systemPrompt = <<<PROMPT
너는 교육용 논쟁 퀘스트 큐레이터야. 기사를 분석해서 학생들이 토론할 수 있는 충돌점(conflict)을 찾아.

출력 형식 (JSON):
{
  "has_debate_potential": true/false,
  "conflict_count": 숫자,
  "conflicts": [
    {
      "axis": "충돌 축 이름",
      "pro_position": "찬성 입장 한 줄",
      "con_position": "반대 입장 한 줄"
    }
  ],
  "quest_title": "퀘스트 제목 (물음표로 끝나는 질문형)",
  "alignment_summary": "배경 설명 2-3문장",
  "grade_suitability": "middle" 또는 "high"
}

조건:
- 충돌점이 2개 이상이어야 has_debate_potential = true
- 정치적으로 민감하거나 선정적인 주제는 피해
- 학생들이 양측 입장을 모두 이해할 수 있어야 함
PROMPT;

    $response = $llm->haiku($systemPrompt, [
        ['role' => 'user', 'content' => "기사 제목: {$title}\n\n요약:\n{$aiSummary}"]
    ]);
    
    if (!empty($response['error'])) {
        curatorLog('LLM error', ['news_id' => $newsId, 'error' => $response['error']]);
        continue;
    }
    
    $content = $response['content'] ?? '';
    $jsonMatch = [];
    if (preg_match('/\{[\s\S]*\}/', $content, $jsonMatch)) {
        $analysis = json_decode($jsonMatch[0], true);
    } else {
        continue;
    }
    
    if (empty($analysis['has_debate_potential']) || ($analysis['conflict_count'] ?? 0) < 2) {
        continue;
    }
    
    $candidates[] = [
        'news_id' => $newsId,
        'title' => $title,
        'analysis' => $analysis,
    ];
    
    curatorLog('Candidate found', [
        'news_id' => $newsId,
        'conflicts' => $analysis['conflict_count'],
        'title' => $analysis['quest_title'] ?? '',
    ]);
    
    if (count($candidates) >= 5) break;
}

curatorLog('Curator completed', ['candidates' => count($candidates)]);

foreach ($candidates as $c) {
    $analysis = $c['analysis'];
    $conflicts = $analysis['conflicts'] ?? [];
    
    $proLine = $conflicts[0]['pro_position'] ?? '찬성';
    $conLine = $conflicts[0]['con_position'] ?? '반대';
    
    $conflictSummary = implode(' / ', array_map(fn($x) => $x['axis'] ?? '', $conflicts));
    
    $hammerHints = [
        'pro' => $conflicts[1]['con_position'] ?? $conLine,
        'con' => $conflicts[1]['pro_position'] ?? $proLine,
    ];
    
    $questCode = 'Q-AUTO-' . date('ymd') . '-' . strtoupper(substr(md5($c['news_id'] . time()), 0, 4));
    
    $questData = [
        'quest_code' => $questCode,
        'quest_title' => $analysis['quest_title'] ?? $c['title'],
        'grade_band' => $analysis['grade_suitability'] ?? 'high',
        'status' => 'draft',
        'pro_line' => $proLine,
        'con_line' => $conLine,
        'alignment_summary' => $analysis['alignment_summary'] ?? '',
        'conflict_summary' => $conflictSummary,
        'hammer_hints' => json_encode($hammerHints, JSON_UNESCAPED_UNICODE),
        'pilot_priority' => 'C',
    ];
    
    try {
        $inserted = $supabase->insert('edu_daily_quests', $questData);
        $questId = $inserted[0]['id'] ?? null;
        
        if ($questId) {
            $supabase->insert('edu_quest_articles', [
                'quest_id' => $questId,
                'news_id' => $c['news_id'],
                'role' => 'primary',
                'sort_order' => 0,
                'title' => $c['title'],
                'gist_url' => 'https://www.thegist.co.kr/news/' . $c['news_id'],
            ]);
            
            curatorLog('Quest created', ['quest_id' => $questId, 'code' => $questCode]);
        }
    } catch (Throwable $e) {
        curatorLog('Insert error', ['error' => $e->getMessage()]);
    }
}

curatorLog('Quest Curator finished');
