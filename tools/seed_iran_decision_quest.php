<?php
/**
 * GIST EDU — Q-IRAN-DEC-202606 결정 탐구 퀘스트 시드 + 라이브 전환
 *
 * Usage:
 *   php tools/seed_iran_decision_quest.php --dry-run
 *   php tools/seed_iran_decision_quest.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';

use Agents\Services\SupabaseService;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$questCode = 'Q-IRAN-DEC-202606';
$legacyCode = 'Q-IRAN-FOREVER-001';

$hammerHints = [
    'mode' => 'convergent',
    'quest_frame' => 'decision_inquiry',
    'time_anchor' => '2026년 6월 기준',
    'shared_conclusion' => '2026년 6월, 트럼프는 이란에 군대를 보내지 않고 미사일과 공격으로 대응하기로 했다',
    'outcome_record' => [
        'filled_at' => null,
        'summary' => null,
        'verification_prompt' => '나중에 결과가 정리되면: 그때 네가 걱정한 \'대가\'와 실제가 얼마나 맞았는지 돌아보자',
    ],
    'axes' => [
        [
            'axis_id' => 'tech',
            'axis_label' => '무기·공격 방법',
            'thesis' => '미사일은 목표는 맞출 수 있어도, 이란처럼 잘 버티는 나라한테 \'이겼다\'고 말하긴 어렵다. 군대 보내는 것보다 덜 위험하지만, 전쟁을 끝내주진 못한다.',
            'author' => '로런스 프리드먼',
            'news_id' => 555,
            'contrast_prompt' => [
                'names_axis' => '미사일·공격이 그나마 맞는 방법이었는지',
                'distinguishes_from' => [
                    'politics' => '이건 트럼프나 미국 사람들 반응이 아니야. 무기·공격 방법 자체를 본다',
                    'structure' => '이건 \'전쟁은 원래 그래\'가 아니야. 이번에 미사일을 쓴 이유를 본다',
                ],
                'pivot_question' => '네 생각은 \'미사일이 맞는 방법\'이었단 거야, \'군대 보내는 것보다 덜 나쁜 선택\'이었단 거야?',
            ],
        ],
        [
            'axis_id' => 'politics',
            'axis_label' => '미국·세계 반응',
            'thesis' => '군대를 보내면 미국 사람들이 반대하고, 동맹이 떠나고, 오래 끌릴까 봐 부담스럽다. 미사일은 세게 나가는 것처럼 보이지만 발을 덜 붙이는 선택이다.',
            'author' => '이코노미스트',
            'news_id' => 422,
            'contrast_prompt' => [
                'names_axis' => '미국 사람들·다른 나라 반응 — 왜 지금 이 방법이었는지',
                'distinguishes_from' => [
                    'tech' => '이건 미사일 성능 문제가 아니야. 사람들·나라들 반응을 본다',
                    'structure' => '이건 나중 일이 아니야. 2026년 당시 미국 안팎 상황을 본다',
                ],
                'pivot_question' => '네 생각은 \'미국 사람들 반응\' 때문이었단 거야, \'다른 나라들 부담\' 때문이었단 거야?',
            ],
        ],
        [
            'axis_id' => 'structure',
            'axis_label' => '나중에 어떻게 될지',
            'thesis' => '미사일만으로는 전쟁이 끝나지 않고, 어정쩡하게 이어질 수 있다. 이 선택 때문에 나중에 더 길어질 수도 있다.',
            'author' => '기데온 로즈',
            'news_id' => 528,
            'contrast_prompt' => [
                'names_axis' => '앞으로 전쟁이 어떻게 이어질지 — 그 선택의 대가',
                'distinguishes_from' => [
                    'tech' => '이건 이번 공격이 맞았냐가 아니야. 몇 달·몇 년 뒤를 본다',
                    'politics' => '이건 트럼프 한 사람 계산이 아니야. 전쟁이 원래 이렇게 흘러가는지 본다',
                ],
                'pivot_question' => '네가 말한 \'대가\'는 \'지금 당장 피해\'야, \'앞으로 전쟁이 더 길어지는 것\'이야?',
            ],
        ],
    ],
    'fallback_adversarial' => [
        'pro' => '미사일·협상 병행이 현실적으로 맞았다',
        'con' => '더 세게 압박하거나 더 일찍 협상했어야 했다',
    ],
    'counter_map' => [
        'tech' => 'structure',
        'politics' => 'tech',
        'structure' => 'politics',
    ],
];

$articles = [
    ['news_id' => 555, 'role' => 'primary', 'title' => '이란과 영원한 전쟁의 함정', 'gist_url' => 'https://www.thegist.co.kr/news/555'],
    ['news_id' => 422, 'role' => 'context', 'title' => '끝나지 않는 전쟁의 높은 대가', 'gist_url' => 'https://www.thegist.co.kr/news/422'],
    ['news_id' => 528, 'role' => 'context', 'title' => '이란은 베트남처럼, 우크라이나는 한국처럼', 'gist_url' => 'https://www.thegist.co.kr/news/528'],
];

$row = [
    'quest_code' => $questCode,
    'quest_title' => '트럼프는 군대를 보내는 대신 미사일로만 공격했어. 왜 그랬을까? 너라면?',
    'grade_band' => 'middle',
    'status' => 'approved',
    'manual_arc' => 'ARC-IRAN-REGION',
    'pro_line' => '미사일만 쓴 선택이 맞았다고 본다',
    'con_line' => '군대를 보내거나, 다른 방법이 나았다고 본다',
    'alignment_summary' => '2026년 6월, 미국은 이란을 군대 없이 미사일과 공습으로 공격했다. 뉴스와 전문가도 \'무엇을 했는지\'는 비슷하게 설명한다.',
    'conflict_summary' => '같은 선택인데, \'왜 그랬는지·괜찮았는지\'는 다르게 본다. 무기·공격 방법 때문인지, 미국 사람들이나 다른 나라 반응 때문인지, 나중에 어떤 대가가 올지 때문인지?',
    'hammer_hints' => $hammerHints,
    'pilot_priority' => 'A',
    'live_at' => date('c'),
    'expires_at' => date('c', strtotime('+7 days')),
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
    echo "\nWould demote live_at on {$legacyCode}\n";
    echo "Then run: php tools/edu_backfill_iran_article_snapshots.php --quest-code={$questCode}\n";
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

$legacy = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($legacyCode), 1);
if (!empty($legacy[0]['id'])) {
    $supabase->update('edu_daily_quests', 'id=eq.' . $legacy[0]['id'], [
        'live_at' => null,
        'updated_at' => date('c'),
    ]);
    echo "Demoted live_at on {$legacyCode}\n";
}

$verify = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($questCode), 1);
$q = $verify[0] ?? [];
echo "\nVerify:\n";
echo '  title: ' . ($q['quest_title'] ?? '') . "\n";
echo '  status: ' . ($q['status'] ?? '') . "\n";
echo '  live_at: ' . ($q['live_at'] ?? 'null') . "\n";
$hints = is_string($q['hammer_hints'] ?? null) ? json_decode($q['hammer_hints'], true) : ($q['hammer_hints'] ?? []);
echo '  frame: ' . ($hints['quest_frame'] ?? '') . "\n";
echo '  time_anchor: ' . ($hints['time_anchor'] ?? '') . "\n";

echo "\nNext: php tools/edu_backfill_iran_article_snapshots.php --quest-code={$questCode}\n";
