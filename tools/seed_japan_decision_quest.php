<?php
/**
 * GIST EDU — Q-G09-DEC-2022 일본 결정탐구 퀘스트 시드
 *
 * Usage:
 *   php tools/seed_japan_decision_quest.php --dry-run
 *   php tools/seed_japan_decision_quest.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\SupabaseService;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$questCode = 'Q-G09-DEC-2022';

$hammerHints = [
    'mode' => 'convergent',
    'quest_frame' => 'decision_inquiry',
    'time_anchor' => '2022년 결정 기준 (2026년에도 이 방향 지속)',
    'shared_conclusion' => '2022년, 일본은 멀리 있는 적의 기지를 맞출 수 있는 미사일을 갖추기로 공식 결정했다',
    'outcome_record' => [
        'filled_at' => null,
        'summary' => null,
        'verification_prompt' => '나중에 뉴스가 정리되면: 그때 네가 걱정한 \'대가\'와 실제가 얼마나 맞았는지 돌아보자',
    ],
    'axes' => [
        [
            'axis_id' => 'tech',
            'axis_label' => '주변 나라·대만 위험',
            'thesis' => '중국 압박이 커지고 대만 문제가 심각해지니까, 일본이 먼저 맞대응할 무기가 필요하다. 미사일은 협박받지 않으려고 갖추는 쪽에 가깝다.',
            'author' => '더 이코노미스트',
            'news_id' => 546,
            'contrast_prompt' => [
                'names_axis' => '중국·대만 때문에 미사일이 필요했는지',
                'distinguishes_from' => [
                    'politics' => '이건 미국이 도와주느냐 문제가 아니야. 옆나라·지역 위험을 본다',
                    'structure' => '이건 예전 일본과 지금 일본 이야기가 아니야. 지금 주변 상황이 위험해서라는 쪽',
                ],
                'pivot_question' => '네 생각은 \'중국·대만이 위험해서\'였단 거야, \'일본이 먼저 공격당할까 봐\'였단 거야?',
            ],
        ],
        [
            'axis_id' => 'politics',
            'axis_label' => '미국·동맹',
            'thesis' => '미국이 바로 도와주지 않거나 늦게 올 수도 있다. 일본이 스스로 버티려면 미사일 같은 카드가 필요하다. 미국과 같이 쓰려는 준비이기도 하다.',
            'author' => '포린 어페어스',
            'news_id' => 546,
            'contrast_prompt' => [
                'names_axis' => '미국·동맹 때문에 미사일을 갖춘 건지',
                'distinguishes_from' => [
                    'tech' => '이건 중국·대만 위협 자체가 아니야. 미국이 어떻게 행동할지·동맹을 본다',
                    'structure' => '이건 일본이 원래 평화주의였다는 이야기가 아니야. 미국과의 관계·역할을 본다',
                ],
                'pivot_question' => '네 생각은 \'미국이 늦게 올까 봐\'였단 거야, \'미국과 같이 싸우려고\'였단 거야?',
            ],
        ],
        [
            'axis_id' => 'structure',
            'axis_label' => '예전 일본 vs 지금 일본',
            'thesis' => '예전엔 최소한 방어만이었는데, 10년 넘게 법·예산·무기가 바뀌어서 이제는 실제로 싸울 준비를 하는 나라다. 2022년 미사일 결정은 그 흐름의 큰 한 걸음이다.',
            'author' => '기사 종합',
            'news_id' => 452,
            'contrast_prompt' => [
                'names_axis' => '일본이 원래 그랬던 나라에서 바뀌어서 미사일을 갖춘 건지',
                'distinguishes_from' => [
                    'tech' => '이건 지금 중국·대만 상황이 아니야. 일본 안에서 10년 넘게 바뀐 방향을 본다',
                    'politics' => '이건 미국·동맹 계산이 아니야. 일본 스스로 이렇게 살겠다는 선택을 본다',
                ],
                'pivot_question' => '네 생각은 \'주변 때문에 어쩔 수 없이\'였단 거야, \'일본이 스스로 강해지기로 한\' 쪽이었단 거야?',
            ],
        ],
    ],
    'fallback_adversarial' => [
        'pro' => '주변 위험·동맹 현실 때문에 필요한 결정이었다',
        'con' => '아직 전쟁도 없는데 너무 과하고 긴장만 키운다',
    ],
    'counter_map' => [
        'tech' => 'structure',
        'politics' => 'tech',
        'structure' => 'politics',
    ],
];

$articles = [
    ['news_id' => 546, 'role' => 'primary', 'title' => '일본의 재무장, 더 강해진 일본은 돌이킬 수 없다', 'gist_url' => 'https://www.thegist.co.kr/news/546'],
    ['news_id' => 452, 'role' => 'context', 'title' => '일본 하드파워의 귀환', 'gist_url' => 'https://www.thegist.co.kr/news/452'],
    ['news_id' => 558, 'role' => 'context', 'title' => '흔들리고 있는 우리나라, 일본, 대만의 산업 기반', 'gist_url' => 'https://www.thegist.co.kr/news/558'],
];

$row = [
    'quest_code' => $questCode,
    'quest_title' => '일본은 2022년 \'먼 적을 맞출 미사일\'을 갖추기로 했어. 왜 그랬을까? 너라면?',
    'grade_band' => 'middle',
    'status' => 'approved',
    'manual_arc' => 'ARC-JAPAN-DEFENSE',
    'pro_line' => '그 결정(미사일 갖추기)이 필요했다고 본다',
    'con_line' => '그 결정이 너무 과하거나 위험하다고 본다',
    'alignment_summary' => '2022년 일본은 멀리 있는 적 기지를 맞출 미사일을 공식적으로 갖추기로 했다. 뉴스·전문가도 무엇을 했는지는 비슷하게 설명한다.',
    'conflict_summary' => '같은 결정인데, 왜 그랬는지·괜찮았는지는 다르게 본다. 주변 나라(중국·대만) 위험 때문인지, 미국·동맹 때문인지, 일본이 예전과 달리 강한 나라로 바뀌는 흐름 때문인지?',
    'hammer_hints' => $hammerHints,
    'pilot_priority' => 'A',
    'live_at' => null,
    'expires_at' => null,
];

$supabase = new SupabaseService([]);
if (!$dryRun && !$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured. Set config/supabase.php or use --dry-run\n");
    exit(1);
}

echo "=== seed {$questCode} (decision_inquiry) ===\n";
echo 'mode: ' . ($dryRun ? 'dry-run' : 'LIVE') . "\n\n";

if ($dryRun) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "\nNote: live_at=null — Q-IRAN-DEC live 유지\n";
    exit(0);
}

$existing = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
if (!empty($existing[0]['id'])) {
    $questId = $existing[0]['id'];
    $supabase->update('edu_daily_quests', 'id=eq.' . $questId, $row);
    echo "Updated quest {$questCode}\n";
} else {
    $inserted = $supabase->insert('edu_daily_quests', $row);
    if ($inserted === null || empty($inserted[0]['id'])) {
        fwrite(STDERR, 'Insert failed: ' . $supabase->getLastError() . "\n");
        exit(1);
    }
    $questId = $inserted[0]['id'];
    echo "Inserted quest {$questCode}\n";
}

$supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
$sort = 0;
foreach ($articles as $article) {
    $supabase->insert('edu_quest_articles', [
        'quest_id' => $questId,
        'news_id' => (int) $article['news_id'],
        'role' => $article['role'],
        'sort_order' => $sort++,
        'title' => $article['title'],
        'gist_url' => $article['gist_url'],
    ]);
}
echo 'Synced ' . count($articles) . " articles\n";

$verify = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
$q = $verify[0] ?? [];
echo "\nVerify:\n";
echo '  title: ' . ($q['quest_title'] ?? '') . "\n";
echo '  status: ' . ($q['status'] ?? '') . "\n";
echo '  live_at: ' . ($q['live_at'] ?? 'null') . "\n";
