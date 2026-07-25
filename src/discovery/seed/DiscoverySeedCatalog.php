<?php
declare(strict_types=1);

namespace Discovery;

/**
 * 고정 디자인 검증용 시드 카탈로그 (7/16~7/25, 하루 9개).
 * 재실행해도 동일한 콘텐츠 — 랜덤 없음.
 */
final class DiscoverySeedCatalog
{
    public const DATE_START = '2026-07-16';
    public const DATE_END = '2026-07-25';
    public const CHANGES_PER_DAY = 9;

    /** @return list<string> */
    public static function dates(): array
    {
        $out = [];
        $cur = new \DateTimeImmutable(self::DATE_START);
        $end = new \DateTimeImmutable(self::DATE_END);
        while ($cur <= $end) {
            $out[] = $cur->format('Y-m-d');
            $cur = $cur->modify('+1 day');
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function changesForDate(string $date): array
    {
        $dayIndex = array_search($date, self::dates(), true);
        if ($dayIndex === false) {
            return [];
        }

        $changes = [];
        for ($rank = 1; $rank <= self::CHANGES_PER_DAY; $rank++) {
            $changes[] = self::buildChange((int) $dayIndex, $rank);
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private static function buildChange(int $dayIndex, int $rank): array
    {
        $slot = $dayIndex * self::CHANGES_PER_DAY + ($rank - 1);
        $category = self::categoryForRank($rank, $dayIndex);
        $topic = self::pickTopic($category, $slot);

        return [
            'category' => $category,
            'title' => $topic['title'],
            'summary' => $topic['summary'],
            'briefing' => [
                'what_changed' => $topic['what'],
                'why_changed' => $topic['why'],
                'why_important' => $topic['important'],
                'future_impact' => $topic['impact'],
                'highlights' => $topic['highlights'],
            ],
            'sources' => self::sourcesFor($topic, $slot),
            'poll' => [
                'question' => $topic['poll_q'],
                'options' => $topic['poll_opts'],
            ],
            '_seed_vote_total' => 80 + ($slot * 11) % 71,
            '_seed_vote_percents' => self::votePercentsFor($slot),
        ];
    }

    private static function categoryForRank(int $rank, int $dayIndex): string
    {
        return match (true) {
            $rank <= 4 => 'geopolitics',
            $rank <= 7 => 'business',
            $rank === 8 => 'tech',
            ($dayIndex % 2) === 0 => 'climate',
            default => 'other',
        };
    }

    /** @return array<string, mixed> */
    private static function pickTopic(string $category, int $slot): array
    {
        $bank = self::topics()[$category];

        return $bank[$slot % count($bank)];
    }

    /** @return list<int> */
    private static function votePercentsFor(int $slot): array
    {
        $bases = [[45, 25, 20, 10], [40, 28, 22, 10], [38, 30, 20, 12], [42, 24, 24, 10]];

        return $bases[$slot % count($bases)];
    }

    /** @return list<array<string, mixed>> */
    private static function sourcesFor(array $topic, int $slot): array
    {
        $out = [];
        foreach ($topic['source_names'] as $i => $name) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)) ?: 'source';
            $out[] = [
                'name' => $name,
                'url' => sprintf('https://seed.thegist.co.kr/design/%d/%s-%d', $slot, $slug, $i + 1),
                'article_title' => $topic['title'] . ' — ' . $name . ' report',
                'verified' => true,
            ];
        }

        return $out;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function topics(): array
    {
        return [
            'geopolitics' => self::geopoliticsTopics(),
            'business' => self::businessTopics(),
            'tech' => self::techTopics(),
            'climate' => self::climateTopics(),
            'other' => self::otherTopics(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function geopoliticsTopics(): array
    {
        return [
            self::t('EU, AI 규제 집행 일정을 2026년 하반기로 앞당기다', 'EU 집행위가 AI Act 핵심 조항 적용 시점을 조정하고 고위험 AI 분류 가이드라인 초안을 공개했습니다.', 'EU AI Act', ['Reuters', 'BBC', 'EU 공식 문서'], 'EU AI 규제가 글로벌 AI 출시 일정에 미칠 영향은?', ['출시 지연', 'EU 전용 모델', '규제 완화', '영향 제한']),
            self::t('NATO, 동유럽 방공 자산 증원 계획을 확정하다', 'NATO가 동유럽 방공·대드론 체계 강화 배치 계획을 발표했습니다.', 'NATO 방공', ['Reuters', 'NATO', 'AP'], 'NATO 방공 증원이 지역 안보에 미칠 영향은?', ['긴장 고조', '억지력 강화', '외교 축소', '단기 효과']),
            self::t('미·중, 반도체 장비 수출 통제 목록을 업데이트하다', '양국이 노광·검사 장비 등 핵심 품목 통제 범위를 확대했습니다.', '반도체 통제', ['Reuters', 'FT', 'BBC'], '장비 통제가 파운드리 투자에 미칠까요?', ['투자 지연', '지역 분산', '국내 대체', '제한적']),
            self::t('UN, 북극 항로 안전·환경 프레임워크 논의를 시작하다', 'UN working group이 북극 항로 운항 규칙 마련을 논의합니다.', '북극 항로', ['UN', 'Reuters', 'Guardian'], '북극 규제가 해운 비용에 미칠까요?', ['비용 상승', '시간 단축', '규제 미비', '불확실']),
            self::t('중동, 해협 통과 선박 보험료 재조정 논의 확대', '재보험사들이 해협 리스크 프리미엄 재산정을 시작했습니다.', '해협 보험', ['Reuters', 'BBC', 'Lloyd\'s'], '보험료가 에너지 가격에 반영될까요?', ['단기 반영', '중장기', '정치적 완화', '미미']),
            self::t('인도·EU, 무역·기술 파트너십 2차 협상 일정 확정', '디지털·그린·공급망 협력 2차 회의 일정이 잡혔습니다.', '인도-EU', ['Reuters', 'EU 공식 문서', 'FT'], '협상 가속의 수혜 업종은?', ['IT', '제조', '농업', '불분명']),
            self::t('대만 해협, 군·민 항로 분리 운영 논의 surfaced', '민간 항공·해운 분리 운영안이 업계 포럼에서 논의됐습니다.', '대만 해협', ['Reuters', 'BBC', 'Nikkei'], '분리 운영이 공급망 리스크를 줄일까요?', ['감소', '비용 증가', '정치 반발', '현실성 낮음']),
            self::t('러·우, 흑해 곡물 통로 협상 재개', 'UN·터키 중재 아래 기술 회의가 열렸습니다.', '흑해 곡물', ['Reuters', 'UN', 'AP'], '통로 재개가 곡물 가격에?', ['하락', '변동성만', '효과 제한', '상승']),
            self::t('일본, 방위 산업 수출 승인 절차 간소화', '방산 수출 심사 항목 축소 개정안이 발표됐습니다.', '일본 방산', ['Reuters', 'Nikkei', 'BBC'], '방산 수출 확대 영향은?', ['균형 변화', '미국 보완', '중국 반발', '제한적']),
            self::t('브라질·중국, 희토류·리튬 MOU 논의', '핵심 광물 장기 공급 프레임워크 협의가 시작됐습니다.', '희토류 MOU', ['Reuters', 'FT', 'Bloomberg'], 'MOU가 배터리 원가에?', ['2~3년', '당장', '실효 낮음', '불확실']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function businessTopics(): array
    {
        return [
            self::t('Fed, 연내 금리 dot plot 공개', 'FOMC가 경제 전망·금리 중앙값을 업데이트했습니다.', 'Fed', ['Reuters', 'Fed', 'Bloomberg'], 'Fed 경로가 신흥국에?', ['달러 강세', '신흥국 압박', '완화', '불확실']),
            self::t('ECB, 은행 자본 규정 일부 개정', 'ECB가 중소은행·녹색금융 자본 요건 가이드를 발표했습니다.', 'ECB', ['Reuters', 'ECB', 'FT'], '완화가 대출 성장에?', ['단기 도움', '구조 한계', '리스크', '미미']),
            self::t('글로벌 M&A, AI 인프라 거래 재개', 'PE·전략적 매수자가 데이터센터 M&A를 재가동했습니다.', 'AI M&A', ['Reuters', 'Bloomberg', 'FT'], 'M&A 붐 지속?', ['2년+', '1년 정점', '규제 둔화', '거품']),
            self::t('OPEC+, 3분기 증산 속도 조정 신호', '다음 회의에서 증산 미세 조정 가능성이 제기됐습니다.', 'OPEC+', ['Reuters', 'Bloomberg', 'IEA'], '유가 변수는?', ['수요', '지정학', '셰일', '달러']),
            self::t('항공화물, 아시아-유럽 운임 반등', '전자·자동차 부품 수요로 운임이 상승했습니다.', '항공 화물', ['Reuters', 'IATA', 'Bloomberg'], '운임이 물가에?', ['3개월', '6개월+', '제한적', '아니오']),
            self::t('SWIFT, CBDC 파일럿 2단계 확대', 'CBDC 국제 결제 연동 테스트 참여 은행이 늘었습니다.', 'CBDC', ['Reuters', 'BIS', 'FT'], 'CBDC가 달러에?', ['장기 위협', '보완', '실효 낮음', '불확실']),
            self::t('자동차 OEM, EV mix 목표 상향', '2027년 EV·하이브리드 mix 목표가 상향됐습니다.', 'EV mix', ['Reuters', 'Bloomberg', 'FT'], '배터리 수요는?', ['급증', '점진', '보조금', '미달']),
            self::t('세계은행, 기후 적응 펀드 2차 기준 공개', '취약국 자금 배분 우선순위가 공개됐습니다.', '기후 펀드', ['World Bank', 'Reuters', 'Guardian'], '빠른 반영?', ['1년', '2년+', '지연', '불확실']),
            self::t('컨테이너선사, 2026 CAPEX 상향', 'LNG dual-fuel 선박 투자가 확대됩니다.', '선사 CAPEX', ['Reuters', 'Bloomberg', 'Clarksons'], '운임 반영?', ['장기', '단기', '상쇄', '미미']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function techTopics(): array
    {
        return [
            self::t('AI 기업, 안전 벤치마크 공동 프레임 공개', '모델 안전 평가 기준 초안이 공동 발표됐습니다.', 'AI 안전', ['Reuters', 'TechCrunch', 'FT'], '규제와 맞물릴까?', ['기준화', '자율만', '형식적', '불확실']),
            self::t('TSMC, 2nm 양산 일정 공식화', '2nm tape-out·물량 배분 원칙이 공개됐습니다.', 'TSMC 2nm', ['Reuters', 'Nikkei', 'Bloomberg'], 'AI 칩 병목 해소?', ['2027', '2028+', '수요 더 빠름', '불확실']),
            self::t('EU·미, DC 전력 공개 의무화 논의', '대형 DC 전력·수자원 공개 의무화안이 논의 중입니다.', 'DC 전력', ['Reuters', 'EU 공식 문서', 'Bloomberg'], '투자 제약?', ['지연', '효율 촉진', '형식적', '미미']),
            self::t('Arm·Qualcomm, AI PC 로드맵 업데이트', 'Arm AI PC 칩 일정·성능 목표가 업데이트됐습니다.', 'AI PC', ['Reuters', 'FT', 'The Verge'], 'x86 잠식?', ['3년', '5년+', '니치', '아니오']),
            self::t('Starlink·Kuiper, LEO 서비스 확대', 'LEO 위성 기업·해상 서비스가 확대됩니다.', 'LEO', ['Reuters', 'Bloomberg', 'SpaceNews'], '통신사와?', ['경쟁', '보완', '니치', '불확실']),
            self::t('클라우드, AI API 가격·한도 조정', 'rate limit·enterprise tier가 재조정됐습니다.', 'AI API', ['Reuters', 'Bloomberg', 'TechCrunch'], '스타트업 유리?', ['절감', 'trade-off', '대기업', '미미']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function climateTopics(): array
    {
        return [
            self::t('EU ETS, 2027 경매 물량 조정안', '배출권 경매 일정·총량 조정 초안이 발표됐습니다.', 'EU ETS', ['Reuters', 'EU 공식 문서', 'Carbon Brief'], '탄소 가격?', ['상승', '하락', '단기', '불확실']),
            self::t('재생에너지, 2026 설치 전망 상향', 'IEA·BNEF가 태양·풍력 전망을 상향했습니다.', '재생에너지', ['IEA', 'Reuters', 'Bloomberg'], '전력 가격?', ['지역별', '하락', '저장 한계', '불확실']),
            self::t('기후 L&D 기금 1차 집행', '취약국 손실·손해 지원 1차 집행이 시작됐습니다.', 'L&D', ['UN', 'Reuters', 'Guardian'], '규모 충분?', ['부족', '확대', '충분', '불확실']),
            self::t('deforestation 공급망 가이드', 'EU 규정 대응 공급망 추적 가이드가 공개됐습니다.', 'deforestation', ['Reuters', 'EU 공식 문서', 'WWF'], '가격 반영?', ['반영', '흡수', '일부', '미미']),
            self::t('북극 해빙, 여름 최소 면적 전망 업데이트', '기후 기관이 해빙 전망을 업데이트했습니다.', '북극 해빙', ['Reuters', 'NSIDC', 'Guardian'], '해운·정책?', ['가속', '단기', '제한', '불확실']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function otherTopics(): array
    {
        return [
            self::t('WHO, 팬데믹 협약 2차 협상 일정', '회원국이 2차 협상 일정을 확정했습니다.', '팬데믹', ['WHO', 'Reuters', 'AP'], '대응력 향상?', ['점진', '즉각', '형식', '불확실']),
            self::t('ISS, 상업 모듈 부착 일정 업데이트', 'NASA·상업 파트너 ISS 후속 모듈 일정이 업데이트됐습니다.', 'ISS', ['Reuters', 'NASA', 'SpaceNews'], '상업 우주?', ['가속', '지연', '정부', '불확실']),
            self::t('올림픽, 디지털 중계권 입찰', 'IOC가 OTT 중계권 입찰을 시작했습니다.', '올림픽', ['Reuters', 'Bloomberg', 'AP'], 'TV 대체?', ['점진', '급격', 'TV 유지', '불확실']),
            self::t('UNWTO, 관광 회복 전망 상향', '국제 관광 회복 속도 전망이 상향됐습니다.', '관광', ['UNWTO', 'Reuters', 'Bloomberg'], '항공·호텔?', ['반영됨', '앞으로', '편중', '미미']),
            self::t('FAO·USDA, 곡물 재고 전망 조정', 'wheat·corn 재고 전망이 조정됐습니다.', '곡물', ['Reuters', 'USDA', 'FAO'], '식품 가격?', ['하락', '상승', '단기', '불확실']),
        ];
    }

    /** @param list<string> $sourceNames @param list<string> $pollOpts */
    private static function t(string $title, string $summary, string $keyword, array $sourceNames, string $pollQ, array $pollOpts): array
    {
        return [
            'title' => $title,
            'summary' => $summary,
            'what' => $summary,
            'why' => "{$keyword} 관련 정책·시장 기대가 겹치며 이번 조정으로 이어졌습니다.",
            'important' => '글로벌 공급망·금융·다국적 기업 전략에 직접적 함의가 있습니다.',
            'impact' => '향후 3~6개월 내 관련 부문 가격·투자·규제 대응에 파급이 예상됩니다.',
            'highlights' => [
                "{$keyword} 관련 쟁점이 정책·시장 논의 중심에 올랐습니다.",
                '주요 기관의 세부 가이드라인 공개가 이어질 전망입니다.',
                '공급망·금융·기술 투자에 2차 파급 가능성이 있습니다.',
                '다음 분기 지표·협상 결과가 방향을 가를 것으로 보입니다.',
            ],
            'source_names' => $sourceNames,
            'poll_q' => $pollQ,
            'poll_opts' => $pollOpts,
        ];
    }
}
