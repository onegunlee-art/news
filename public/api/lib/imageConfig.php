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
// API 키 (빈 문자열이면 해당 API 검색을 건너뛰고 고정 URL만 사용)
// =====================================================================
if (!defined('UNSPLASH_ACCESS_KEY')) {
    define('UNSPLASH_ACCESS_KEY', '7Uv6bw6t1Dkl3EP8M8WdR8nKWjqfR9EumcyQbGJcrnk');
}
if (!defined('PEXELS_API_KEY')) {
    define('PEXELS_API_KEY', 'YkfW8ZkFswO5ODe8bBKH75mdCl2cWxYd4ol39VtGW0U0IqO3dttZGlQy');
}

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
// 국가/지역/분쟁 이미지 매핑 (Unsplash - 무료)
// 규칙 3: 전쟁이나 분쟁 국가 이야기는 해당 국가 사진으로 한다
// 각 키워드에 3~5장 → 중복 확률 최소화
// =====================================================================
$countryImages = [
    // ── 우크라이나 ──
    '우크라이나' => [
        'https://images.unsplash.com/photo-1561629625-edcf10282d43?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1555109307-f7d9da25c244?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1508009603885-50cf7c8dd0d5?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1605803135498-eb40e0d1b586?w=800&h=500&fit=crop',
    ],
    'ukraine' => [
        'https://images.unsplash.com/photo-1561629625-edcf10282d43?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1555109307-f7d9da25c244?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1508009603885-50cf7c8dd0d5?w=800&h=500&fit=crop',
    ],
    '키이우' => [
        'https://images.unsplash.com/photo-1561629625-edcf10282d43?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1605803135498-eb40e0d1b586?w=800&h=500&fit=crop',
    ],

    // ── 이스라엘/팔레스타인 ──
    '이스라엘' => [
        'https://images.unsplash.com/photo-1547483238-2cbf881a559f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1544967082-d9d25d867d66?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1552423310-ba7e93a00e8c?w=800&h=500&fit=crop',
    ],
    'israel' => [
        'https://images.unsplash.com/photo-1547483238-2cbf881a559f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1544967082-d9d25d867d66?w=800&h=500&fit=crop',
    ],
    '가자' => [
        'https://images.unsplash.com/photo-1580418827493-f2b22c0a76cb?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591696205602-2f950c417cb9?w=800&h=500&fit=crop',
    ],
    'gaza' => [
        'https://images.unsplash.com/photo-1580418827493-f2b22c0a76cb?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591696205602-2f950c417cb9?w=800&h=500&fit=crop',
    ],
    '팔레스타인' => [
        'https://images.unsplash.com/photo-1580418827493-f2b22c0a76cb?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591696205602-2f950c417cb9?w=800&h=500&fit=crop',
    ],

    // ── 러시아 ──
    '러시아' => [
        'https://images.unsplash.com/photo-1513326738677-b964603b136d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1547448415-e9f5b28e570d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1520106212299-d99c443e4568?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1512495039889-52a3b799c9bc?w=800&h=500&fit=crop',
    ],
    'russia' => [
        'https://images.unsplash.com/photo-1513326738677-b964603b136d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1547448415-e9f5b28e570d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1520106212299-d99c443e4568?w=800&h=500&fit=crop',
    ],
    '모스크바' => [
        'https://images.unsplash.com/photo-1513326738677-b964603b136d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1512495039889-52a3b799c9bc?w=800&h=500&fit=crop',
    ],

    // ── 중국 ──
    '중국' => [
        'https://images.unsplash.com/photo-1508804185872-d7badad00f7d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1547981609-4b6bfe67ca0b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1474181628450-6a0f7c841f40?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1529921879218-f99546d03a9e?w=800&h=500&fit=crop',
    ],
    'china' => [
        'https://images.unsplash.com/photo-1508804185872-d7badad00f7d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1547981609-4b6bfe67ca0b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1474181628450-6a0f7c841f40?w=800&h=500&fit=crop',
    ],
    '베이징' => [
        'https://images.unsplash.com/photo-1508804185872-d7badad00f7d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1529921879218-f99546d03a9e?w=800&h=500&fit=crop',
    ],

    // ── 대만 ──
    '대만' => [
        'https://images.unsplash.com/photo-1470004914212-05527e49370b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1553503487-5661bc5a3e15?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1572019183334-58dce265e44e?w=800&h=500&fit=crop',
    ],
    '타이완' => [
        'https://images.unsplash.com/photo-1470004914212-05527e49370b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1553503487-5661bc5a3e15?w=800&h=500&fit=crop',
    ],
    'taiwan' => [
        'https://images.unsplash.com/photo-1470004914212-05527e49370b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1553503487-5661bc5a3e15?w=800&h=500&fit=crop',
    ],

    // ── 일본 ──
    '일본' => [
        'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1528164344885-47b1492d2e49?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1526481280693-3bfa7568e0f3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1545569341-9eb8b30979d9?w=800&h=500&fit=crop',
    ],
    'japan' => [
        'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1528164344885-47b1492d2e49?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1526481280693-3bfa7568e0f3?w=800&h=500&fit=crop',
    ],
    '도쿄' => [
        'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1536098561742-ca998e48cbcc?w=800&h=500&fit=crop',
    ],

    // ── 미국 ──
    '미국' => [
        'https://images.unsplash.com/photo-1501594907352-04cda38ebc29?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1485738422979-f5c462d49f04?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1422464804701-7d8356b3a42f?w=800&h=500&fit=crop',
    ],
    '워싱턴' => [
        'https://images.unsplash.com/photo-1501466044931-62695aada8e9?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1617581629397-a72507c3de9e?w=800&h=500&fit=crop',
    ],
    '백악관' => [
        'https://images.unsplash.com/photo-1501466044931-62695aada8e9?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1585399000684-d2f72660f092?w=800&h=500&fit=crop',
    ],
    '뉴욕' => [
        'https://images.unsplash.com/photo-1485738422979-f5c462d49f04?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1534430480872-3498386e7856?w=800&h=500&fit=crop',
    ],

    // ── 한국 ──
    '한국' => [
        'https://images.unsplash.com/photo-1517154421773-0529f29ea451?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1546874177-9e664107314e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1538485399081-7191377e8241?w=800&h=500&fit=crop',
    ],
    '서울' => [
        'https://images.unsplash.com/photo-1517154421773-0529f29ea451?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1546874177-9e664107314e?w=800&h=500&fit=crop',
    ],
    '한미' => [
        'https://images.unsplash.com/photo-1508433957232-3107f5fd5995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1569863959165-56dae551d4fc?w=800&h=500&fit=crop',
    ],

    // ── 북한 ──
    '북한' => [
        'https://images.unsplash.com/photo-1558959489-d7e0d4a59588?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1541580621-cb65cc53084b?w=800&h=500&fit=crop',
    ],
    '평양' => [
        'https://images.unsplash.com/photo-1558959489-d7e0d4a59588?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1541580621-cb65cc53084b?w=800&h=500&fit=crop',
    ],

    // ── 중동 ──
    '중동' => [
        'https://images.unsplash.com/photo-1466442929976-97f336a657be?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1548013146-72479768bada?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1547483238-2cbf881a559f?w=800&h=500&fit=crop',
    ],
    '이란' => [
        'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1576485375217-d6a95e34d043?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1553184118-d20b4b3b7e1b?w=800&h=500&fit=crop',
    ],
    'iran' => [
        'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1576485375217-d6a95e34d043?w=800&h=500&fit=crop',
    ],
    '시리아' => [
        'https://images.unsplash.com/photo-1544735716-ea9ef790fcfd?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1567704817674-d6e386acf494?w=800&h=500&fit=crop',
    ],
    'syria' => [
        'https://images.unsplash.com/photo-1544735716-ea9ef790fcfd?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1567704817674-d6e386acf494?w=800&h=500&fit=crop',
    ],

    // ── 유럽 ──
    '유럽' => [
        'https://images.unsplash.com/photo-1467269204594-9661b134dd2b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1491557345352-5929e343eb89?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1519677100203-a0e668c92439?w=800&h=500&fit=crop',
    ],
    'eu' => [
        'https://images.unsplash.com/photo-1467269204594-9661b134dd2b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1491557345352-5929e343eb89?w=800&h=500&fit=crop',
    ],

    // ── 그린란드 ──
    'greenland' => [
        'https://images.unsplash.com/photo-1517783999520-f068f9e28a51?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1489549132488-d00b7eee80f1?w=800&h=500&fit=crop',
    ],
    '그린란드' => [
        'https://images.unsplash.com/photo-1517783999520-f068f9e28a51?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1489549132488-d00b7eee80f1?w=800&h=500&fit=crop',
    ],
];

// =====================================================================
// 주제별 이미지 매핑 (Unsplash)
// =====================================================================
$topicImages = [
    // AI / 기술
    'openai' => [
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1684163362235-0d2e2b2c7c64?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1676573409967-986dcf64d35a?w=800&h=500&fit=crop',
    ],
    'ai' => [
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1555255707-c07966088b7b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=800&h=500&fit=crop',
    ],
    '인공지능' => [
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=800&h=500&fit=crop',
    ],
    'chatgpt' => [
        'https://images.unsplash.com/photo-1684163362235-0d2e2b2c7c64?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1676573409967-986dcf64d35a?w=800&h=500&fit=crop',
    ],
    // 경제 / 금융
    '경제' => [
        'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&h=500&fit=crop',
    ],
    '주식' => [
        'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1535320903710-d993d3d77d29?w=800&h=500&fit=crop',
    ],
    '비트코인' => [
        'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1622630998477-20aa696ecb05?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1639762681485-074b7f938ba0?w=800&h=500&fit=crop',
    ],
    'bitcoin' => [
        'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1622630998477-20aa696ecb05?w=800&h=500&fit=crop',
    ],
    '관세' => [
        'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1494412574643-ff11b0a5eb19?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591696205602-2f950c417cb9?w=800&h=500&fit=crop',
    ],
    'tariff' => [
        'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1494412574643-ff11b0a5eb19?w=800&h=500&fit=crop',
    ],
    // 반도체
    '반도체' => [
        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1580584126903-c17d41830450?w=800&h=500&fit=crop',
    ],
    'semiconductor' => [
        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=800&h=500&fit=crop',
    ],
    // 테슬라
    'tesla' => [
        'https://images.unsplash.com/photo-1620891499292-74ecc6905d3b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1560958089-b8a1929cea89?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1562053220-6f4e26f1e6ea?w=800&h=500&fit=crop',
    ],
    '테슬라' => [
        'https://images.unsplash.com/photo-1620891499292-74ecc6905d3b?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1560958089-b8a1929cea89?w=800&h=500&fit=crop',
    ],
    // K-POP / 엔터테인먼트
    'k-pop' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800&h=500&fit=crop',
    ],
    'kpop' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
    ],
    '케이팝' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
    ],
    // 외교
    '외교' => [
        'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1577415124269-fc1140815970?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?w=800&h=500&fit=crop',
    ],
    '정상회담' => [
        'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1577415124269-fc1140815970?w=800&h=500&fit=crop',
    ],
    '광고' => [
        'https://images.unsplash.com/photo-1557804506-669a67965ba0?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&h=500&fit=crop',
    ],
];

// =====================================================================
// 카테고리 기본 이미지 (키워드/국가 모두 매칭 안 될 때)
// =====================================================================
$categoryDefaults = [
    'diplomacy' => [
        'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1577415124269-fc1140815970?w=800&h=500&fit=crop',
    ],
    'economy' => [
        'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1535320903710-d993d3d77d29?w=800&h=500&fit=crop',
    ],
    'technology' => [
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&h=500&fit=crop',
    ],
    'entertainment' => [
        'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=800&h=500&fit=crop',
        'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800&h=500&fit=crop',
    ],
];

// =====================================================================
// 범용 기본 이미지 (최종 fallback)
// =====================================================================
$defaultImages = [
    'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1495020689067-958852a7765e?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1557992260-ec58e38d363c?w=800&h=500&fit=crop',
    'https://images.unsplash.com/photo-1586339949916-3e9457bef6d3?w=800&h=500&fit=crop',
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
