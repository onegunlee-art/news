<?php
/**
 * Q-NUKE-AXIS-630 fixture (seed + isolation 공용)
 */
declare(strict_types=1);

/** @return list<array<string, mixed>> */
function eduNuke630Axes(): array
{
    return [
        [
            'axis_id' => 'military',
            'axis_label' => '군사로는 막기 어렵다',
            'thesis' => '핵무기가 있어도 드론·미사일 같은 값싼 공격은 막지 못한다. 러시아는 핵 위협을 했는데도 실제로는 핵이 아니라 일반 무기로만 맞대응했다.',
            'author' => 'Foreign Affairs',
            'news_id' => 630,
            'contrast_prompt' => [
                'names_axis' => '핵이 있어도 드론·미사일 공격은 못 막는다는 쪽',
                'distinguishes_from' => [
                    'norms' => '이건 약속·규칙 이야기가 아니야. 실제로 공격이 통하는지·군사력으로 막히는지 본다',
                    'defense' => '이건 방어 체계를 더 키우자는 이야기가 아니야. 핵 억지 자체가 약해졌다는 쪽',
                ],
                'pivot_question' => '네 생각은 \'핵이 있어도 작은 공격은 막히지 않는다\'였단 거야, \'큰 전쟁만 막으면 된다\'였단 거야?',
            ],
        ],
        [
            'axis_id' => 'norms',
            'axis_label' => '새 약속이 필요하다',
            'thesis' => '핵시설·원전을 재래식 공격하지 않겠다는 새 규칙이 필요하다. 인도·파키스탄처럼 서로 핵 관련 시설 목록을 바꾸고 공격하지 않겠다고 약속하는 방식이 선례다.',
            'author' => 'Foreign Affairs',
            'news_id' => 630,
            'contrast_prompt' => [
                'names_axis' => '나라들끼리 약속·규칙으로 막아야 한다는 쪽',
                'distinguishes_from' => [
                    'military' => '이건 핵·미사일 힘이 약해졌다는 이야기가 아니야. 약속과 규칙을 새로 만들자는 쪽',
                    'defense' => '이건 방공·기지 방호를 더 키우자는 이야기가 아니야. 공격 자체를 금지하는 규칙을 본다',
                ],
                'pivot_question' => '네 생각은 \'약속으로 공격을 막자\'였단 거야, \'맞아도 버티는 방어\'였단 거야?',
            ],
        ],
        [
            'axis_id' => 'defense',
            'axis_label' => '방어에 투자해야 한다',
            'thesis' => '핵탄두를 더 많이 갖는 것보다, 기지 방호·방공·드론 방어 같은 회복력에 투자하는 게 더 중요하다. 값싼 드론 떼를 막으려면 비싼 요격만으로는 오래 못 버틴다.',
            'author' => 'Foreign Affairs',
            'news_id' => 630,
            'contrast_prompt' => [
                'names_axis' => '핵을 늘리기보다 방어·회복력에 투자해야 한다는 쪽',
                'distinguishes_from' => [
                    'military' => '이건 핵 억지가 약해졌다는 진단이 아니야. 뭘 더 갖추고 지키느냐(방어)를 본다',
                    'norms' => '이건 국제 약속·규칙 이야기가 아니야. 실제 방어 체계·기지 보호를 본다',
                ],
                'pivot_question' => '네 생각은 \'핵을 더 갖춰야 한다\'였단 거야, \'방공·기지 방호를 키워야 한다\'였단 거야?',
            ],
        ],
    ];
}

/** @return list<array{news_id: int, role: string, title: string, gist_url: string}> */
function eduNuke630QuestArticles(): array
{
    return [
        [
            'news_id' => 630,
            'role' => 'primary',
            'title' => '핵 억지의 \'기묘한\' 패배',
            'gist_url' => 'https://www.thegist.co.kr/news/630',
        ],
        [
            'news_id' => 475,
            'role' => 'context',
            'title' => '우리나라와 일본의 핵무장을 막는 방법',
            'gist_url' => 'https://www.thegist.co.kr/news/475',
        ],
        [
            'news_id' => 449,
            'role' => 'context',
            'title' => '인도-파키스탄 간 전쟁이 다시 발발한다면',
            'gist_url' => 'https://www.thegist.co.kr/news/449',
        ],
        [
            'news_id' => 615,
            'role' => 'context',
            'title' => '북한에 대한 영향력을 놓고 경쟁하고 있는 중국과 러시아',
            'gist_url' => 'https://www.thegist.co.kr/news/615',
        ],
    ];
}

function eduNuke630QuestFixture(): array
{
    $hammerHints = [
        'mode' => 'convergent',
        'quest_frame' => 'myth_bust',
        'time_anchor' => '2025~2026년 사례 기준',
        'shared_conclusion' => '핵무기가 있어도 드론·미사일 같은 값싼 공격은 막기 어렵다는 게 최근 사례에서 드러났다',
        'hook_short' => '핵 억지가 큰 전쟁은 막아도, 미사일·드론 같은 일반 공격은 왜 못 막고 있는 걸까?',
        'hook_full' => '보통은 핵무기가 있으면 나라들이 함부로 못 싸운다고들 해. 그런데 러시아 폭격기 기지가 드론에 맞았고, 핵이 아니라 일반 무기로만 맞대응했어. 이스라엘·인도·파키스탄도 비슷해. 그럼 핵은 정말 우릴 지켜줄까? 우리나라도 핵을 가져야 할까?',
        'axes' => eduNuke630Axes(),
        'counter_map' => [
            'military' => 'norms',
            'norms' => 'defense',
            'defense' => 'military',
        ],
        'fallback_adversarial' => [
            'pro' => '핵이 있으면 적어도 큰 전쟁은 막을 수 있다',
            'con' => '핵만으로는 드론·미사일 같은 공격까지 막기 어렵다',
        ],
    ];

    return [
        'quest_code' => 'Q-NUKE-AXIS-630',
        'quest_title' => '핵 있으면 정말 안전할까? 우리나라도 핵을 가져야 할까?',
        'pro_line' => '핵무장이 필요하다고 본다',
        'con_line' => '핵만으로는 부족하다고 본다',
        'alignment_summary' => '최근 사례(우크라이나·이스라엘·인도-파키스탄)에서 핵무기가 있어도 드론·미사일 공격을 막지 못했다는 점은 많은 분석가가 동의한다.',
        'conflict_summary' => '같은 사실인데, 어떻게 대응해야 할지는 다르게 본다. 군사적으로 핵 억지가 약해졌다는 쪽인지, 새 국제 약속이 필요하다는 쪽인지, 방어·회복력에 투자해야 한다는 쪽인지?',
        'hammer_hints' => $hammerHints,
    ];
}
