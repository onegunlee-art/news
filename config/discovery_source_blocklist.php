<?php
declare(strict_types=1);

/**
 * Discovery 출처 차단 목록 — 한국 언론 및 저품질 국내 소스 (도메인 suffix 매칭).
 * @return list<string>
 */
return [
    // 한국 주요 언론 (v2: 화이트리스트에서 완전 제외)
    'yna.co.kr',
    'chosun.com',
    'joongang.co.kr',
    'donga.com',
    'hani.co.kr',
    'khan.co.kr',
    'kbs.co.kr',
    'imbc.com',
    'sbs.co.kr',
    'ytn.co.kr',
    'mk.co.kr',
    'hankyung.com',
    'news1.kr',
    'newsis.com',
    'sedaily.com',
    'mt.co.kr',
    'fnnews.com',
    'heraldcorp.com',
    'busan.com',
    'koreatimes.co.kr',
    'koreaherald.com',

    // 개인 블로그·집합 사이트
    'tistory.com',
    'blog.naver.com',
    'naver.com',
    'daum.net',
    'dazabi.com',
];
