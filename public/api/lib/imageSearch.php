<?php
/**
 * 이미지 검색 (고정 매핑 전용 - Unsplash/Pexels API 제거됨)
 *
 * 핵심 함수:
 *   smartImageUrl($title, $category, $pdo)
 *     → 인물/국가/주제 고정 매핑 → fallback (API 없음)
 *     → DB 중복 체크 포함
 *
 * 필요 파일: imageConfig.php (같은 디렉터리에서 require)
 */

require_once __DIR__ . '/imageConfig.php';

// =====================================================================
// 한글 → 영문 검색어 변환 테이블 (API 검색용)
// =====================================================================
$korToEngMap = [
    '경제' => 'economy finance',
    '주식' => 'stock market',
    '비트코인' => 'bitcoin cryptocurrency',
    '반도체' => 'semiconductor chip',
    '인공지능' => 'artificial intelligence',
    '외교' => 'diplomacy summit',
    '정상회담' => 'summit meeting',
    '관세' => 'tariff trade',
    '무역' => 'trade commerce',
    '전쟁' => 'war conflict',
    '분쟁' => 'conflict dispute',
    '군사' => 'military defense',
    '미사일' => 'missile defense',
    '핵' => 'nuclear',
    '기후' => 'climate change',
    '환경' => 'environment nature',
    '에너지' => 'energy power',
    '석유' => 'oil petroleum',
    '우주' => 'space exploration',
    '로봇' => 'robot technology',
    '자동차' => 'automobile car',
    '항공' => 'aviation airplane',
    '의료' => 'medical healthcare',
    '코로나' => 'covid pandemic',
    '백신' => 'vaccine medical',
    '올림픽' => 'olympic games',
    '월드컵' => 'world cup football',
    '영화' => 'movie cinema',
    '음악' => 'music concert',
    '패션' => 'fashion style',
    '부동산' => 'real estate property',
    '금리' => 'interest rate',
    '인플레이션' => 'inflation economy',
    '실업' => 'unemployment economy',
    '스타트업' => 'startup business',
    '사이버' => 'cybersecurity hacking',
    '해킹' => 'hacking cybersecurity',
    '선거' => 'election vote',
    '국회' => 'parliament congress',
    '탄핵' => 'impeachment politics',
    '시위' => 'protest rally',
    '난민' => 'refugee crisis',
    '이민' => 'immigration border',
    '테러' => 'terrorism security',
    '케이팝' => 'kpop music',
    'k-pop' => 'kpop music',
    '한류' => 'korean wave hallyu',
    '드라마' => 'kdrama television',
    '넷플릭스' => 'netflix streaming',
    '게임' => 'gaming esports',
    '애플' => 'apple technology',
    '구글' => 'google technology',
    '아마존' => 'amazon ecommerce',
    '삼성' => 'samsung electronics',
    '현대' => 'hyundai automobile',
    'sk' => 'sk hynix semiconductor',
];

/**
 * 제목에서 API 검색에 쓸 영문 쿼리를 생성한다.
 * 한글 키워드 → 영문 변환, 영문 단어는 그대로, 최대 4단어.
 */
function buildSearchQuery(string $title): string {
    global $korToEngMap;
    $titleLower = mb_strtolower($title);
    $parts = [];

    // 한글 키워드 매칭
    foreach ($korToEngMap as $kor => $eng) {
        if (mb_strpos($titleLower, $kor) !== false) {
            $parts[] = $eng;
            if (count($parts) >= 2) break;
        }
    }

    // 영문 단어 추출 (3자 이상)
    preg_match_all('/[a-zA-Z]{3,}/', $title, $m);
    foreach (($m[0] ?? []) as $w) {
        $w = strtolower($w);
        if (!in_array($w, ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'has', 'his', 'how', 'its', 'may', 'new', 'now', 'old', 'see', 'way', 'who', 'did', 'get', 'let', 'say', 'she', 'too', 'use', 'with', 'this', 'that', 'from', 'will', 'have', 'been', 'said', 'each', 'make', 'like', 'than', 'them', 'some', 'into', 'could', 'after', 'about', 'would', 'there', 'their', 'which', 'other'])) {
            $parts[] = $w;
        }
        if (count($parts) >= 4) break;
    }

    return implode(' ', array_slice($parts, 0, 4)) ?: 'news';
}

// 영문 뉴스 주제 → 일러스트 검색어 (기사와 어울리는 이미지용)
$illustrationTopicMap = [
    'saudi' => 'middle east diplomacy', 'uae' => 'middle east diplomacy', 'gulf' => 'middle east',
    'feud' => 'diplomacy conflict', 'rivalry' => 'diplomacy', 'yemen' => 'middle east',
    'iran' => 'middle east', 'syria' => 'middle east', 'israel' => 'diplomacy',
    'russia' => 'europe diplomacy', 'ukraine' => 'europe conflict', 'china' => 'asia global',
    'trump' => 'politics', 'biden' => 'politics', 'election' => 'politics',
];

/**
 * 일러스트/캐리커처 스타일 검색용 쿼리 (제목+설명에서 키워드 추출 후 "illustration" 추가)
 */
function buildSearchQueryForIllustration(string $title, string $description = ''): string {
    global $illustrationTopicMap;
    $combined = mb_strtolower($title . ' ' . mb_substr($description, 0, 200, 'UTF-8'));

    // 영문 주제 키워드가 있으면 그에 맞는 검색어 우선 (Saudi-UAE, Gulf 등)
    foreach ($illustrationTopicMap as $keyword => $searchPhrase) {
        if (mb_strpos($combined, $keyword) !== false) {
            return $searchPhrase . ' illustration';
        }
    }

    $base = buildSearchQuery($title . ' ' . $description);
    if ($base === 'news') {
        return 'editorial illustration';
    }
    return trim($base) . ' illustration';
}

/**
 * 저작권 회피용: 일러스트/캐리커처 스타일 썸네일 URL 반환.
 * (og:image 대신 사용하여 원본 기사 이미지 저작권 이슈 회피)
 */
function getIllustrationImageUrl(string $title, string $description = '', string $category = '', ?PDO $pdo = null): string {
    global $illustrationDefaults;
    if (!isset($illustrationDefaults) || !is_array($illustrationDefaults) || empty($illustrationDefaults)) {
        $illustrationDefaults = [
            'https://placehold.co/800x500/1e293b/94a3b8?text=The+Gist',
            'https://placehold.co/800x500/334155/64748b?text=News',
        ];
    }

    $usedUrls = [];
    if ($pdo) {
        try {
            $usedUrls = $pdo->query("SELECT DISTINCT image_url FROM news WHERE image_url IS NOT NULL AND image_url != ''")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $usedUrls = [];
        }
    }

    $pick = pickUnused($illustrationDefaults, $usedUrls, $title);
    return ($pick !== null && $pick !== '') ? $pick : $illustrationDefaults[0];
}

/**
 * URL 배열에서 $excludeUrls에 없는 첫 번째 URL을 반환한다.
 * 전부 사용 중이면 해시 기반으로 하나 선택.
 */
function pickUnused(array $urls, array $excludeUrls, string $title): ?string {
    if (empty($urls)) return null;

    // 미사용 URL 우선
    foreach ($urls as $u) {
        if (!in_array($u, $excludeUrls, true)) return $u;
    }

    // 전부 사용 중이면 해시 기반 선택 (같은 제목 → 같은 이미지)
    $idx = abs(crc32($title)) % count($urls);
    return $urls[$idx];
}

/**
 * 메인 함수: 기사 제목·카테고리 기반으로 최적의 썸네일 URL을 반환한다.
 *
 * 우선순위:
 *   1. 인물 키워드 → Wikimedia 고정 URL
 *   2. 국가/분쟁 키워드 → 고정 URL 풀 (중복 체크)
 *   3. 주제 키워드 → 고정 URL 풀 (중복 체크)
 *   4. 카테고리 기본 → 범용 기본
 */
function smartImageUrl(string $title, string $category, ?PDO $pdo = null): string {
    global $personImages, $countryImages, $topicImages, $categoryDefaults, $defaultImages;

    $titleLower = mb_strtolower($title);

    // DB에서 사용 중인 이미지 URL 목록 조회 (중복 방지용)
    $usedUrls = [];
    if ($pdo) {
        try {
            $usedUrls = $pdo->query("SELECT DISTINCT image_url FROM news WHERE image_url IS NOT NULL AND image_url != ''")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $usedUrls = [];
        }
    }

    // 1. 인물 키워드 매칭 (고정 1장이므로 중복 체크 안 함 — 같은 인물은 같은 사진이 맞음)
    foreach ($personImages as $keyword => $url) {
        if (mb_strpos($titleLower, mb_strtolower($keyword)) !== false) {
            return is_array($url) ? $url[0] : $url;
        }
    }

    // 2. 국가/분쟁 키워드 매칭
    foreach ($countryImages as $keyword => $urls) {
        if (mb_strpos($titleLower, mb_strtolower($keyword)) !== false) {
            $pick = pickUnused($urls, $usedUrls, $title);
            if ($pick) return $pick;
        }
    }

    // 3. 주제 키워드 매칭
    foreach ($topicImages as $keyword => $urls) {
        if (mb_strpos($titleLower, mb_strtolower($keyword)) !== false) {
            $pick = pickUnused($urls, $usedUrls, $title);
            if ($pick) return $pick;
        }
    }

    // 4. 카테고리 기본 이미지
    $cat = strtolower($category ?: '');
    if (isset($categoryDefaults[$cat])) {
        $pick = pickUnused($categoryDefaults[$cat], $usedUrls, $title);
        if ($pick) return $pick;
    }

    // 5. 범용 기본 이미지
    return pickUnused($defaultImages, $usedUrls, $title) ?: $defaultImages[0];
}
