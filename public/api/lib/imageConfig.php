<?php
/**
 * 뉴스 썸네일 이미지 통합 설정
 *
 * - 인물/국가/주제 키워드 → 고정 URL 매핑
 * - 카테고리 기본 이미지
 * - 범용 기본 이미지
 * - API 키 상수
 *
 * 새 키워드 추가 시 이 파일만 수정하면 news.php, update-images.php 모두 반영됩니다.
 */

// =====================================================================
// API 키 (Unsplash/Pexels 제거됨 - 고정 매핑만 사용)
// =====================================================================

// =====================================================================
// 인물 이미지 매핑 (Wikimedia Commons - Public Domain / CC)
// 규칙 2: 대통령이나 주요 인물이 나오면 그 사람 사진으로 한다
// =====================================================================
$personImages = [
    // ── 미국 ──
    'trump' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/56/Donald_Trump_official_portrait.jpg/800px-Donald_Trump_official_portrait.jpg',
    '트럼프' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/56/Donald_Trump_official_portrait.jpg/800px-Donald_Trump_official_portrait.jpg',
    'biden' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Joe_Biden_presidential_portrait.jpg/800px-Joe_Biden_presidential_portrait.jpg',
    '바이든' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Joe_Biden_presidential_portrait.jpg/800px-Joe_Biden_presidential_portrait.jpg',
    'harris' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/41/Kamala_Harris_Vice_Presidential_Portrait.jpg/800px-Kamala_Harris_Vice_Presidential_Portrait.jpg',
    '해리스' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/41/Kamala_Harris_Vice_Presidential_Portrait.jpg/800px-Kamala_Harris_Vice_Presidential_Portrait.jpg',
    '카멀라' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/41/Kamala_Harris_Vice_Presidential_Portrait.jpg/800px-Kamala_Harris_Vice_Presidential_Portrait.jpg',

    // ── 러시아 ──
    'putin' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/Vladimir_Putin_%282020-02-20%29.jpg/800px-Vladimir_Putin_%282020-02-20%29.jpg',
    '푸틴' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/Vladimir_Putin_%282020-02-20%29.jpg/800px-Vladimir_Putin_%282020-02-20%29.jpg',
    'lavrov' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/Sergey_Lavrov_17.03.2022_%28cropped%29.jpg/800px-Sergey_Lavrov_17.03.2022_%28cropped%29.jpg',
    '래브로프' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/Sergey_Lavrov_17.03.2022_%28cropped%29.jpg/800px-Sergey_Lavrov_17.03.2022_%28cropped%29.jpg',
    '라브로프' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/Sergey_Lavrov_17.03.2022_%28cropped%29.jpg/800px-Sergey_Lavrov_17.03.2022_%28cropped%29.jpg',

    // ── 중국 ──
    '시진핑' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/32/Xi_Jinping_2019.jpg/800px-Xi_Jinping_2019.jpg',
    'xi' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/32/Xi_Jinping_2019.jpg/800px-Xi_Jinping_2019.jpg',

    // ── 우크라이나 ──
    'zelensky' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8c/Volodymyr_Zelensky_Official_portrait.jpg/800px-Volodymyr_Zelensky_Official_portrait.jpg',
    '젤렌스키' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8c/Volodymyr_Zelensky_Official_portrait.jpg/800px-Volodymyr_Zelensky_Official_portrait.jpg',

    // ── 이스라엘 ──
    'netanyahu' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b1/Benjamin_Netanyahu_2023.jpg/800px-Benjamin_Netanyahu_2023.jpg',
    '네타냐후' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b1/Benjamin_Netanyahu_2023.jpg/800px-Benjamin_Netanyahu_2023.jpg',

    // ── 프랑스 ──
    'macron' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Emmanuel_Macron_in_2019.jpg/800px-Emmanuel_Macron_in_2019.jpg',
    '마크롱' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Emmanuel_Macron_in_2019.jpg/800px-Emmanuel_Macron_in_2019.jpg',

    // ── 독일 ──
    'scholz' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/ba/Olaf_Scholz_2024_%28cropped%29.jpg/800px-Olaf_Scholz_2024_%28cropped%29.jpg',
    '숄츠' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/ba/Olaf_Scholz_2024_%28cropped%29.jpg/800px-Olaf_Scholz_2024_%28cropped%29.jpg',

    // ── 인도 ──
    'modi' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c6/Narendra_Modi_2021.jpg/800px-Narendra_Modi_2021.jpg',
    '모디' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c6/Narendra_Modi_2021.jpg/800px-Narendra_Modi_2021.jpg',

    // ── 일본 ──
    '이시바' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/50/Shigeru_Ishiba_20241001.jpg/800px-Shigeru_Ishiba_20241001.jpg',
    'ishiba' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/50/Shigeru_Ishiba_20241001.jpg/800px-Shigeru_Ishiba_20241001.jpg',

    // ── 북한 ──
    '김정은' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/21/Kim_Jong-un_at_the_2019_Russia%E2%80%93North_Korea_summit_%28cropped%29.jpg/800px-Kim_Jong-un_at_the_2019_Russia%E2%80%93North_Korea_summit_%28cropped%29.jpg',
    'kim jong un' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/21/Kim_Jong-un_at_the_2019_Russia%E2%80%93North_Korea_summit_%28cropped%29.jpg/800px-Kim_Jong-un_at_the_2019_Russia%E2%80%93North_Korea_summit_%28cropped%29.jpg',

    // ── 한국 ──
    '윤석열' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/12/Yoon_Suk-yeol_May_2022.jpg/800px-Yoon_Suk-yeol_May_2022.jpg',
    '이재명' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0d/Lee_Jae-myung_December_2024.jpg/800px-Lee_Jae-myung_December_2024.jpg',
    '한덕수' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/Han_Duck-soo_in_Nov_2022_%28cropped%29.jpg/800px-Han_Duck-soo_in_Nov_2022_%28cropped%29.jpg',

    // ── 교황 ──
    '교황' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Pope_Francis_Korea_Haemi_Castle_19_%28cropped%29.jpg/800px-Pope_Francis_Korea_Haemi_Castle_19_%28cropped%29.jpg',
    'pope' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Pope_Francis_Korea_Haemi_Castle_19_%28cropped%29.jpg/800px-Pope_Francis_Korea_Haemi_Castle_19_%28cropped%29.jpg',
    '프란치스코' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Pope_Francis_Korea_Haemi_Castle_19_%28cropped%29.jpg/800px-Pope_Francis_Korea_Haemi_Castle_19_%28cropped%29.jpg',

    // ── 테크 CEO ──
    '머스크' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Elon_Musk_Royal_Society_%28crop2%29.jpg/800px-Elon_Musk_Royal_Society_%28crop2%29.jpg',
    'musk' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Elon_Musk_Royal_Society_%28crop2%29.jpg/800px-Elon_Musk_Royal_Society_%28crop2%29.jpg',
    'elon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/34/Elon_Musk_Royal_Society_%28crop2%29.jpg/800px-Elon_Musk_Royal_Society_%28crop2%29.jpg',
    '저커버그' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Mark_Zuckerberg_F8_2019_Keynote_%2832830578717%29_%28cropped%29.jpg/800px-Mark_Zuckerberg_F8_2019_Keynote_%2832830578717%29_%28cropped%29.jpg',
    'zuckerberg' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Mark_Zuckerberg_F8_2019_Keynote_%2832830578717%29_%28cropped%29.jpg/800px-Mark_Zuckerberg_F8_2019_Keynote_%2832830578717%29_%28cropped%29.jpg',
    '빌 게이츠' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/Bill_Gates_2017_%28cropped%29.jpg/800px-Bill_Gates_2017_%28cropped%29.jpg',
    'bill gates' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/Bill_Gates_2017_%28cropped%29.jpg/800px-Bill_Gates_2017_%28cropped%29.jpg',
    '베이조스' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/Jeff_Bezos_visits_PICA_%2832700160178%29_%28cropped%29.jpg/800px-Jeff_Bezos_visits_PICA_%2832700160178%29_%28cropped%29.jpg',
    'bezos' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/Jeff_Bezos_visits_PICA_%2832700160178%29_%28cropped%29.jpg/800px-Jeff_Bezos_visits_PICA_%2832700160178%29_%28cropped%29.jpg',
    '팀 쿡' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/Tim_Cook_%282017%2C_cropped%29.jpg/800px-Tim_Cook_%282017%2C_cropped%29.jpg',
    'tim cook' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/Tim_Cook_%282017%2C_cropped%29.jpg/800px-Tim_Cook_%282017%2C_cropped%29.jpg',
    '젠슨 황' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/Jensen_Huang_CEO_of_Nvidia.jpg/800px-Jensen_Huang_CEO_of_Nvidia.jpg',
    'jensen huang' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/Jensen_Huang_CEO_of_Nvidia.jpg/800px-Jensen_Huang_CEO_of_Nvidia.jpg',
    '젠슨황' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/Jensen_Huang_CEO_of_Nvidia.jpg/800px-Jensen_Huang_CEO_of_Nvidia.jpg',
    'nvidia' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/Jensen_Huang_CEO_of_Nvidia.jpg/800px-Jensen_Huang_CEO_of_Nvidia.jpg',
    '샘 알트만' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Sam_Altman_CropEdit.jpg/800px-Sam_Altman_CropEdit.jpg',
    'sam altman' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Sam_Altman_CropEdit.jpg/800px-Sam_Altman_CropEdit.jpg',
    'altman' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Sam_Altman_CropEdit.jpg/800px-Sam_Altman_CropEdit.jpg',
];

// =====================================================================
// 국가/지역/분쟁 이미지 매핑 (placehold.co 플레이스홀더)
// 규칙 3: 전쟁이나 분쟁 국가 이야기는 해당 국가 사진으로 한다
// 각 키워드에 3~5장 → 중복 확률 최소화
// =====================================================================
$countryImages = [
    // ── 우크라이나 ──
    '우크라이나' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'ukraine' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '키이우' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 이스라엘/팔레스타인 ──
    '이스라엘' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'israel' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '가자' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'gaza' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '팔레스타인' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 러시아 ──
    '러시아' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'russia' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '모스크바' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 중국 ──
    '중국' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'china' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '베이징' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 대만 ──
    '대만' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '타이완' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'taiwan' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 일본 ──
    '일본' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'japan' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '도쿄' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 미국 ──
    '미국' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '워싱턴' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '백악관' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '뉴욕' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 한국 ──
    '한국' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '서울' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '한미' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 북한 ──
    '북한' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '평양' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 중동 ──
    '중동' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '이란' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'iran' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '시리아' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'syria' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 유럽 ──
    '유럽' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'eu' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],

    // ── 그린란드 ──
    'greenland' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '그린란드' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
];

// =====================================================================
// 주제별 이미지 매핑 (placehold.co 플레이스홀더)
// =====================================================================
$topicImages = [
    // AI / 기술
    'openai' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'ai' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '인공지능' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'chatgpt' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    // 경제 / 금융
    '경제' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '주식' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '비트코인' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'bitcoin' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '관세' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'tariff' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    // 반도체
    '반도체' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'semiconductor' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    // 테슬라
    'tesla' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '테슬라' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    // K-POP / 엔터테인먼트
    'k-pop' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'kpop' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '케이팝' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    // 외교
    '외교' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '정상회담' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    '광고' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
];

// =====================================================================
// 카테고리 기본 이미지 (키워드/국가 모두 매칭 안 될 때)
// =====================================================================
$categoryDefaults = [
    'diplomacy' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'economy' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'technology' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
    'entertainment' => [
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
        'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    ],
];

// =====================================================================
// 저작권 회피용: 일러스트/캐리커처 스타일 기본 이미지 (ThumbnailAgent fallback)
// 뉴스/편집 느낌 위주 (물결·풍경 등 회피)
// =====================================================================
$illustrationDefaults = [
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // newspaper/news
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // diplomacy
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // globe
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // meeting
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // global/earth
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // news/reading
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // newspaper
    'https://placehold.co/800x500/1e293b/94a3b8?text=News', // document/editorial
];

// =====================================================================
// 범용 기본 이미지 (최종 fallback)
// =====================================================================
$defaultImages = [
    'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    'https://placehold.co/800x500/1e293b/94a3b8?text=News',
    'https://placehold.co/800x500/1e293b/94a3b8?text=News',
];

// =====================================================================
// 하위 호환용: 기존 $imageMap (인물 + 국가 + 주제 통합)
// =====================================================================
$imageMap = [];
foreach ($personImages as $k => $url) {
    $imageMap[$k] = is_array($url) ? $url : [$url];
}
foreach ($countryImages as $k => $urls) {
    $imageMap[$k] = $urls;
}
foreach ($topicImages as $k => $urls) {
    if (!isset($imageMap[$k])) {
        $imageMap[$k] = $urls;
    }
}
