<?php
declare(strict_types=1);

/**
 * Discovery 출처 화이트리스트 — 도메인 suffix 매칭 (host === domain 또는 *.domain).
 * @return list<string>
 */
return [
    // 글로벌 통신·주요 언론
    'reuters.com',
    'apnews.com',
    'bbc.com',
    'bbc.co.uk',
    'bloomberg.com',
    'ft.com',
    'wsj.com',
    'economist.com',
    'nikkei.com',
    'asia.nikkei.com',
    'theguardian.com',
    'nytimes.com',
    'washingtonpost.com',
    'aljazeera.com',
    'dw.com',
    'france24.com',
    'npr.org',

    // 미국 정부
    'whitehouse.gov',
    'state.gov',
    'defense.gov',
    'treasury.gov',
    'federalreserve.gov',
    'congress.gov',

    // 국제기구·EU
    'europa.eu',
    'nato.int',
    'un.org',
    'imf.org',
    'worldbank.org',
    'oecd.org',
    'wto.org',
    'who.int',
    'ecb.europa.eu',

    // 싱크탱크
    'csis.org',
    'brookings.edu',
    'carnegieendowment.org',
    'chathamhouse.org',
    'cfr.org',
    'warontherocks.com',
    'lawfaremedia.org',
    'atlanticcouncil.org',
    'rand.org',

    // 과학·기술 전문
    'nature.com',
    'science.org',
    'arstechnica.com',
    'technologyreview.com',
];
