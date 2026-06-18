<?php
/**
 * GIST EDU — Cross-cut lens quest seeds (Q-LENS-*)
 *
 * Usage:
 *   php tools/seed_lens_quests.php --dry-run
 *   php tools/seed_lens_quests.php --apply
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/src/agents/autoload.php';
require_once $root . '/public/api/edu/lib/eduQuestCatalog.php';

use Agents\Services\SupabaseService;

$dryRun = !in_array('--apply', $argv ?? [], true);

$quests = [
    [
        'quest_code' => 'Q-LENS-TRUMP-001',
        'lens' => 'trump_consistency',
        'category' => 'us_politics',
        'quest_title' => '트럼프는 왜 말할 때마다 다른 것 같을까?',
        'pro_line' => '상황마다 바꾸는 게 현실적이었다',
        'con_line' => '약속을 지키지 않으면 신뢰가 깨진다',
        'conflict_summary' => '이란·관세·협상 — 같은 대통령인데 왜 이번엔 이렇게 했을까?',
        'articles' => [
            ['news_id' => 263, 'role' => 'primary', 'title' => '이란 전쟁, 미국의 전략적 비일관성'],
            ['news_id' => 397, 'role' => 'context', 'title' => '트럼프의 새로운 무역 질서'],
            ['news_id' => 496, 'role' => 'counter', 'title' => '트럼프는 이란과 새 핵합의를 이끌 수 있을까'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-NUKE-001',
        'lens' => 'nuclear_deterrence',
        'category' => 'east_asia_security',
        'quest_title' => '핵을 가지면 더 안전해질까?',
        'pro_line' => '핵은 공격당하지 않게 막아준다',
        'con_line' => '핵은 오히려 더 위험하게 만든다',
        'conflict_summary' => '이란·북한·미중 — \'핵\' 이야기지만 나라마다 이유가 다르다',
        'articles' => [
            ['news_id' => 196, 'role' => 'primary', 'title' => '이란 핵 프로그램의 어중간한 솔루션'],
            ['news_id' => 437, 'role' => 'context', 'title' => '미중 핵경쟁 심화와 안보 딜레마'],
            ['news_id' => 152, 'role' => 'counter', 'title' => '미국에게 주어진 나쁜 출구전략'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-ALLY-001',
        'lens' => 'alliance_reliance',
        'category' => 'east_asia_security',
        'quest_title' => '위험할 때 동맹을 믿어도 될까?',
        'pro_line' => '혼자보다 동맹이 낫다',
        'con_line' => '동맹에만 기대면 약해진다',
        'conflict_summary' => '일본·한국·미국 — 안보를 \'혼자\' vs \'같이\'로 보면?',
        'articles' => [
            ['news_id' => 452, 'role' => 'primary', 'title' => '일본 하드파워의 귀환'],
            ['news_id' => 546, 'role' => 'context', 'title' => '일본의 재무장, 돌이킬 수 없다'],
            ['news_id' => 558, 'role' => 'counter', 'title' => '동북아 반도체 집중과 산업 기반'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-ECOWAR-001',
        'lens' => 'economic_war',
        'category' => 'us_china_trade',
        'quest_title' => '전쟁이면 물가·일자리는 어떻게 될까?',
        'pro_line' => '경제 타격이 전쟁을 빨리 끝낸다',
        'con_line' => '경제 제재는 평범한 사람만 아프다',
        'conflict_summary' => '관세·유가·공급망 — 전쟁과 경제는 어떻게 연결될까?',
        'articles' => [
            ['news_id' => 397, 'role' => 'primary', 'title' => '망가진 세계경제의 균형'],
            ['news_id' => 193, 'role' => 'context', 'title' => '글로벌 경제를 향한 공격'],
            ['news_id' => 220, 'role' => 'counter', 'title' => '유가 급등의 파급효과'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-AIYOUTH-001',
        'lens' => 'ai_jobs_youth',
        'category' => 'society_youth',
        'quest_title' => 'AI 시대, 청소년은 뭘 배워야 할까?',
        'pro_line' => 'AI를 일찍 배우는 게 유리하다',
        'con_line' => 'AI에 너무 의존하면 생각이 약해진다',
        'conflict_summary' => '일자리·교육·규제 — AI가 청소년에게 뭐가 달라질까?',
        'articles' => [
            ['news_id' => 507, 'role' => 'primary', 'title' => 'AI 시대의 사회계약론'],
            ['news_id' => 288, 'role' => 'context', 'title' => '청소년 AI 사용, 바람직한 관리'],
            ['news_id' => 72, 'role' => 'counter', 'title' => 'AI 규제가 까다로운 이유'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-CEASE-001',
        'lens' => 'ceasefire_diplomacy',
        'category' => 'middle_east_iran',
        'quest_title' => '휴전하면 전쟁이 끝난 걸까?',
        'pro_line' => '휴전·협상이 최선이다',
        'con_line' => '휴전은 그냥 미루는 것뿐이다',
        'conflict_summary' => '이란·외교 — \'멈춤\'과 \'끝\'은 같은 말일까?',
        'articles' => [
            ['news_id' => 528, 'role' => 'primary', 'title' => '이란 전쟁은 베트남처럼 끝날 것'],
            ['news_id' => 290, 'role' => 'context', 'title' => '단단하지만 불안정한 이슬람 공화국'],
            ['news_id' => 237, 'role' => 'counter', 'title' => '잠깐의 소강 상태를 맞이하는 자세'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-SUPPLY-001',
        'lens' => 'supply_chain_security',
        'category' => 'ai_tech',
        'quest_title' => '반도체가 끊기면 어떻게 될까?',
        'pro_line' => '국내 생산이 안전하다',
        'con_line' => '세계와 연결돼야 경쟁력이 있다',
        'conflict_summary' => '반도체·공급망 — 물건 하나가 나라 안보와 연결될 때',
        'articles' => [
            ['news_id' => 513, 'role' => 'primary', 'title' => '삼성전자 호황과 새로운 리스크'],
            ['news_id' => 220, 'role' => 'context', 'title' => '유가 급등과 공급망 충격'],
            ['news_id' => 558, 'role' => 'counter', 'title' => '동북아 반도체 집중 정책'],
        ],
    ],
    [
        'quest_code' => 'Q-LENS-ENDGAME-001',
        'lens' => 'war_endgame',
        'category' => 'europe_war',
        'quest_title' => '전쟁이 \'끝\'난다는 건 무슨 뜻일까?',
        'pro_line' => '승패가 정해지면 끝난다',
        'con_line' => '끝났어도 갈등은 남는다',
        'conflict_summary' => '우크라이나·이란 — 전쟁 종식을 어떻게 볼까?',
        'articles' => [
            ['news_id' => 87, 'role' => 'primary', 'title' => '우크라이나 전쟁의 교훈'],
            ['news_id' => 528, 'role' => 'context', 'title' => '이란 전쟁은 베트남처럼 끝날 것'],
            ['news_id' => 384, 'role' => 'counter', 'title' => '상호의존에서 상호 취약성으로'],
        ],
    ],
];

echo "=== EDU Lens Quest Seed ===\n";
echo 'mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'count: ' . count($quests) . "\n\n";

if ($dryRun) {
    foreach ($quests as $q) {
        echo "  {$q['quest_code']} [{$q['lens']}] {$q['quest_title']}\n";
    }
    exit(0);
}

$supabase = new SupabaseService([]);
if (!$supabase->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$ok = 0;
foreach ($quests as $def) {
    $lensLabel = eduQuestLensLabel($def['lens']);
    $scores = [
        'category' => $def['category'],
        'lens' => $def['lens'],
        'lens_label' => $lensLabel,
        'catalog_version' => 1,
        'seed_type' => 'lens',
    ];

    $row = [
        'quest_code' => $def['quest_code'],
        'quest_title' => $def['quest_title'],
        'grade_band' => 'middle',
        'status' => 'approved',
        'manual_arc' => 'LENS-' . strtoupper($def['lens']),
        'pro_line' => $def['pro_line'],
        'con_line' => $def['con_line'],
        'alignment_summary' => '같은 시대 뉴스지만, ' . $lensLabel . ' 각도로 보면 논쟁이 달라진다.',
        'conflict_summary' => $def['conflict_summary'],
        'hammer_hints' => json_encode([
            'quest_frame' => 'decision_inquiry',
            'time_anchor' => '2026년 기준',
            'lens' => $def['lens'],
        ], JSON_UNESCAPED_UNICODE),
        'pilot_priority' => 'B',
        'scores' => json_encode($scores, JSON_UNESCAPED_UNICODE),
    ];

    $existing = $supabase->select('edu_daily_quests', 'quest_code=eq.' . rawurlencode($def['quest_code']), 1);
    if (!empty($existing[0]['id'])) {
        $questId = $existing[0]['id'];
        $supabase->update('edu_daily_quests', 'id=eq.' . $questId, $row);
        echo "Updated {$def['quest_code']}\n";
    } else {
        $inserted = $supabase->insert('edu_daily_quests', $row);
        if ($inserted === null || empty($inserted[0]['id'])) {
            fwrite(STDERR, "Insert failed {$def['quest_code']}: " . $supabase->getLastError() . "\n");
            continue;
        }
        $questId = $inserted[0]['id'];
        echo "Inserted {$def['quest_code']}\n";
    }

    $supabase->delete('edu_quest_articles', 'quest_id=eq.' . $questId);
    $sort = 0;
    foreach ($def['articles'] as $article) {
        $supabase->insert('edu_quest_articles', [
            'quest_id' => $questId,
            'news_id' => (int) $article['news_id'],
            'role' => $article['role'],
            'sort_order' => $sort++,
            'title' => $article['title'],
            'gist_url' => 'https://www.thegist.co.kr/news/' . $article['news_id'],
        ]);
    }
    $ok++;
}

echo "\nDone: {$ok}/" . count($quests) . " lens quests (approved, no live_at)\n";
