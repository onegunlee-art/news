<?php
/**
 * P2 — 축 순서 인도 코치 (안 떠먹임)
 * 기준: docs/P2_COACH_NO_SPOONFEED.md
 */
declare(strict_types=1);

require_once __DIR__ . '/eduQuest.php';
require_once __DIR__ . '/eduBlueprint.php';
require_once __DIR__ . '/eduCoachLevel.php';
require_once __DIR__ . '/eduCoachGuideElementary.php';
require_once __DIR__ . '/eduCoachGuideBridge.php';
require_once __DIR__ . '/eduCoachGuideMiddle.php';
require_once __DIR__ . '/eduCoachGuideUpper.php';

const EDU_COACH_GUIDE_QUEST_CODE = 'Q-AUTO-NUKE-630';
const EDU_COACH_GUIDE_QUEST_CODE_DC_150 = 'Q-AUTO-DC-150';
const EDU_COACH_GUIDE_QUEST_CODE_IRAN_196 = 'Q-AUTO-IRAN-196';
const EDU_COACH_GUIDE_QUEST_CODE_YOUTH_288 = 'Q-AUTO-YOUTH-288';
const EDU_COACH_GUIDE_STALL_ESCAPE = 3;
/** P2-B hook: per-student fact length (summary → medium → full) */
const EDU_COACH_GUIDE_FACT_DISPLAY = 'summary';

/** @param array<string, mixed> $quest */
function eduQuestUsesAxisGuide(array $quest): bool
{
    if (($quest['quest_code'] ?? '') === EDU_COACH_GUIDE_QUEST_CODE) {
        return true;
    }
    $hints = eduQuestHammerHints($quest);

    return ($hints['coach_mode'] ?? '') === 'axis_guide_v1';
}

/** 630 정규화 3축 — 군사 / 규범(§2 흡수) / 방어 */
function eduCoachGuide630Axes(): array
{
    return [
        [
            'axis_id' => 'military',
            'point' => '핵·억지가 재래식·드론 공격을 실제로 막는지',
            'core_question' => '핵이 있으면 재래식 공격과 전쟁 확대를 정말 막을 수 있을까?',
            'article_fact' => '2025년 6월 우크라이나는 \'거미줄 작전\'으로 러시아 전략폭격기 기지를 타격했고, 러시아는 핵이 아니라 재래식 보복만 했다.',
            'weak_scaffold' => '우크라이나 사례만 보면 — 드론 타격 후 **핵 대신 재래식** 보복이었어. 이게 네 \'핵=억지\' 말을 **강하게** 해주나, **약하게** 해주나?',
        ],
        [
            'axis_id' => 'norms',
            'point' => '나라들끼리 약속·규범으로 공격을 막을 수 있는지',
            'core_question' => '핵시설·원전을 재래식으로 안 치겠다는 약속, 새로 만들 수 있을까?',
            'article_fact' => '2025년 인도-파키스탄은 핵보유국인데도 재래식 충돌이 났고, 인도·파키스탄은 매년 핵 관련 시설 목록을 바꿔 주며 상호 비공격을 약속한다.',
            'weak_scaffold' => '인도·파키스탄처럼 **약속·목록 교환**이 재래식 충돌을 막는 데 **도움이 됐다/안 됐다** — 네 말로 한쪽만 골라봐.',
            'choice_options' => ['도움이 됐다', '안 됐다'],
        ],
        [
            'axis_id' => 'defense',
            'point' => '핵을 더 늘리기 vs 기지·방공·회복력에 쓸지',
            'core_question' => '예산이 한정돼 있다면 — 너는 **무엇에 먼저** 쓸 것 같아? (핵 현대화 / 기지·방공·드론 방어 / 규범 협상)',
            'article_fact' => '값싼 드론 떼를 막으려면 비싼 요격만으로는 오래 버티기 어렵고, 기지 방호·회복탄력성 이야기가 나온다.',
            'weak_scaffold' => '드론·미사일 앞에서 — **핵을 더 갖추는 쪽**과 **방공·기지를 키우는 쪽** 중 **하나만** 고르면 뭐부터야?',
            'choice_options' => ['핵 현대화', '기지·방공·드론 방어', '규범 협상'],
            'weak_choice_options' => ['핵을 더 갖추는 쪽', '방공·기지를 키우는 쪽'],
        ],
    ];
}

/** 150 정규화 3축 — 규모 / 정책(§2) / 송전망·투자(§3+§4 merge) */
function eduCoachGuide150Axes(): array
{
    return [
        [
            'axis_id' => 'scale',
            'point' => '데이터센터가 실제로 얼마나 큰 전력을 쓰는가',
            'core_question' => '애슈번 데이터센터 전력 규모가 얼마나 큰가?',
            'article_fact' => '버지니아 애슈번에는 데이터센터가 약 150개 있고, 이들이 쓰는 전력은 필라델피아 전체와 비슷한 수준이다.',
            'weak_scaffold' => '애슈번만 봐 — DC **150개**, 전력은 **필라델피아급**이야. \'AI=범인\' 말을 **강하게** 해주나 **약하게**?',
        ],
        [
            'axis_id' => 'policy',
            'point' => '정치·기업은 전기요금 문제에 어떻게 대응하려 하는가',
            'core_question' => '트럼프·빅테크의 전력 공급 서약이 뭘 의미하는가?',
            'article_fact' => '도널드 트럼프는 3월 4일 빅테크 리더들을 불러 자체 전력 공급을 구축·조달해 전기요금을 올리지 않겠다는 서약에 서명하게 했다.',
            'weak_scaffold' => '트럼프가 빅테크에 **자체 전력 공급·요금 동결** 서약 받았어. 이게 \'DC=범인\' 프레임을 **키워** 아니면 **줄여**?',
            'weak_choice_options' => ['키운다', '줄인다'],
        ],
        [
            'axis_id' => 'grid_investment',
            'point' => '요금 상승 원인이 AI만인가, 데이터센터가 발전·망 투자에 기여할 수 있는가',
            'core_question' => '요금이 올랐다면 — **주로 AI·데이터센터 탓**인가, **송전망·시장 제약**인가? (한쪽만)',
            'article_fact' => '전기요금 상승은 AI 수요뿐 아니라 송전망·에너지시장 전반의 제약과 연결된다고 본다. 데이터센터 수요는 신규 발전·전력망 투자를 뒷받침해 다른 소비자 요금 압력을 낮출 여지도 있다.',
            'weak_scaffold' => 'AI 탓만이면 **송전망·시장**은 어디에 끼워? 반대로 DC가 **새 발전·망 투자**를 부를 수 있다면 — 범인 프레임과 **맞나 안 맞나**?',
            'choice_options' => ['AI·데이터센터 탓', '송전망·시장 제약'],
            'weak_choice_options' => ['맞다', '안 맞다'],
        ],
    ];
}

/** 196 정규화 3축 — 정권 의지 / 우라늄 / 해법(§3+§4 merge) */
function eduCoachGuide196Axes(): array
{
    return [
        [
            'axis_id' => 'regime',
            'point' => '전쟁 이후 이란 지도부가 핵 프로그램을 얼마나 밀어붙이는가',
            'core_question' => '모즈타바 체제가 핵 의지를 **강하게** 키웠다 / **약해졌다** — 네 말로 한쪽만.',
            'article_fact' => '새 최고지도자 모즈타바 하메네이는 선친보다 핵무기 획득에 더 적극적인 것으로 알려져 있으며 가족을 잃은 뒤 복수심까지 더해졌다고 본문은 전한다.',
            'weak_scaffold' => '지도부 교체·복수심만 봐 — 핵 의지가 **커졌다/작아졌다** 중 **하나**만 골라봐.',
            'choice_options' => ['강하게 키웠다', '약해졌다'],
            'weak_choice_options' => ['커졌다', '작아졌다'],
        ],
        [
            'axis_id' => 'uranium',
            'point' => '이란 내부에 남은 고농축 우라늄 400kg',
            'core_question' => '400kg 우라늄이 남아 있다면 — **새 위협**이 커진다 / **통제 가능**하다 중 하나만.',
            'article_fact' => '약 400kg의 고농축 우라늄이 이란 내부에 매장돼 있으며 이는 핵폭탄 약 10개를 만들기에 충분한 양이라고 본문은 설명한다.',
            'weak_scaffold' => '**400kg**, **10개분** — 이게 **위협 키운다/아니다** 중 어디에 더 가깝다고 봐?',
            'choice_options' => ['새 위협이 커진다', '통제 가능하다'],
            'weak_choice_options' => ['위협 키운다', '아니다'],
        ],
        [
            'axis_id' => 'options',
            'point' => '특수부대·반복 폭격·협상 — 세 해법의 실행 가능성',
            'core_question' => '특수부대·폭격·협상 중 — **실행 가능성**이 가장 낮은 쪽은 어디라고 봐?',
            'article_fact' => '특수부대 투입은 1,000명 이상의 병력과 지속적인 공중 지원이 필요한 대규모 작전이며, 2015년 합의는 트럼프가 2018년 파기했다고 본문은 적는다.',
            'weak_scaffold' => '**대규모 특수부대** vs **협상 파기** — 세 해법 중 **가장 어중간한** 하나만 골라봐.',
            'choice_options' => ['특수부대', '반복 폭격', '협상'],
        ],
    ];
}

/** 288 정규화 3축 — 질문틀(§1) / 기회(§2) / 대체재·정책(§3+§5 merge) */
function eduCoachGuide288Axes(): array
{
    return [
        [
            'axis_id' => 'framing',
            'point' => '위험을 사용 시간으로 볼지, 정서·사회 맥락으로 볼지',
            'core_question' => '청소년 AI 위험 — **시간**이 먼저냐, **삶의 조건·정서**가 먼저냐? (한쪽만)',
            'article_fact' => '2025년 11월 미국 대규모 조사에서 AI의 효과는 사용 시간보다 개인의 삶의 조건과 정서적 환경에 따라 크게 달라지는 것으로 나타났다.',
            'weak_scaffold' => '조사는 **시간**보다 **맥락**을 강조해. 네 말은 **시간파**야 **맥락파**야?',
            'choice_options' => ['시간', '삶의 조건·정서'],
            'weak_choice_options' => ['시간파', '맥락파'],
        ],
        [
            'axis_id' => 'mobility',
            'point' => '일부 청소년에게 AI가 기회·진로 도구가 되는가',
            'core_question' => '파워 유저 집단 — AI가 **기회를 연다** / **별 차이 없다** 중 하나만.',
            'article_fact' => '전체의 약 10%인 낙관적 파워 유저 집단 가운데 3분의 2 이상은 AI가 새로운 기회를 열어준다고 답했고, 절반 이상은 문제 해결 자신감과 희망을 높여준다고 응답했다.',
            'weak_scaffold' => '**10%** 파워 유저, **3분의 2**가 기회 — 이게 **시간 제한** 논쟁을 **강하게/약하게** 해줘?',
            'choice_options' => ['기회를 연다', '별 차이 없다'],
        ],
        [
            'axis_id' => 'substitute_policy',
            'point' => '정서적 대체재 vs 안전장치·인간 연결 정책',
            'core_question' => '정책은 **일괄 금지** / **안전장치+인간 연결** 중 어디에 더 무게를 둬야 할까?',
            'article_fact' => '괴롭힘·차별·경제적 압박을 겪을 가능성이 더 높은 집단은 AI 사용 빈도도 높았고, 본문은 유해 상호작용을 줄이는 안전장치와 정신건강 서비스로 연결되는 경로를 제시한다.',
            'weak_scaffold' => '오프라인 지지 부족 + AI **대체재** — **금지**가 맞아, **안전장치+연결**이 맞아? **하나**만.',
            'choice_options' => ['일괄 금지', '안전장치+인간 연결'],
        ],
    ];
}

/** @return list<array<string, string>> */
function eduCoachGuideAxesForNewsId(int $newsId): array
{
    return match ($newsId) {
        150 => eduCoachGuide150Axes(),
        196 => eduCoachGuide196Axes(),
        288 => eduCoachGuide288Axes(),
        default => eduCoachGuide630Axes(),
    };
}

/** @param array<string, mixed> $quest */
function eduCoachGuideNewsIdFromQuest(array $quest): int
{
    $articles = $quest['articles'] ?? [];
    if (is_array($articles) && !empty($articles[0]['news_id'])) {
        return (int) $articles[0]['news_id'];
    }

    return match ($quest['quest_code'] ?? '') {
        EDU_COACH_GUIDE_QUEST_CODE_DC_150 => 150,
        EDU_COACH_GUIDE_QUEST_CODE_IRAN_196 => 196,
        EDU_COACH_GUIDE_QUEST_CODE_YOUTH_288 => 288,
        default => 630,
    };
}

/** @param array<string, string> $axis @param array<string, string> $canonical */
function eduCoachGuideMergeAxisChoiceFields(array $axis, array $canonical): array
{
    foreach (['choice_options', 'weak_choice_options'] as $key) {
        if (!empty($canonical[$key]) && empty($axis[$key])) {
            $axis[$key] = $canonical[$key];
        }
    }

    return $axis;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideAxes(array $quest): array
{
    $hints = eduQuestHammerHints($quest);
    $fromHints = $hints['_guide_axes'] ?? null;
    $canonicalList = eduCoachGuideAxesForNewsId(eduCoachGuideNewsIdFromQuest($quest));
    $canonicalById = [];
    foreach ($canonicalList as $canonical) {
        $canonicalById[(string) ($canonical['axis_id'] ?? '')] = $canonical;
    }

    if (is_array($fromHints) && $fromHints !== []) {
        $merged = [];
        foreach ($fromHints as $axis) {
            if (!is_array($axis)) {
                continue;
            }
            $id = (string) ($axis['axis_id'] ?? '');
            $merged[] = eduCoachGuideMergeAxisChoiceFields($axis, $canonicalById[$id] ?? []);
        }

        return $merged !== [] ? $merged : $canonicalList;
    }

    return $canonicalList;
}

function eduCoachGuideAttachHints(array $hints, int $newsId = 630): array
{
    $hints['coach_mode'] = 'axis_guide_v1';
    $hints['_guide_axes'] = eduCoachGuideAxesForNewsId($newsId);
    $hints['mode'] = 'adversarial';

    return $hints;
}

function eduCoachDetectEvasion(string $message): ?string
{
    $m = mb_strtolower(trim($message));
    if ($m === '') {
        return 'empty';
    }
    if (preg_match('/(둘\s*다|둘다|모두\s*다?|전부|균형\s*(이)?\s*중|병행\s*(해야|이)?)/u', $m)) {
        return 'both';
    }
    if (preg_match('/(모르겠|잘\s*모르|글쎄|아\s*모르|(?<![가-힣])몰라(?![가-힣]))/u', $m)) {
        return 'unknown';
    }
    if (preg_match('/(나라마다|경우마다|상황마다|케이스\s*바이)/u', $m)) {
        return 'deflect';
    }
    if (preg_match('/(결론.{0,6}(뭐|어디|어떻)|정답|알려\s*줘)/u', $m)) {
        return 'ask_conclusion';
    }
    if (preg_match('/(기사\s*(가|는)|the\s*gist|본문\s*(이|가))\s*(그렇|말하|쓰)/u', $m)) {
        return 'defer_article';
    }
    if (preg_match('/^(중요하고.*중요|필요하고.*필요)$/u', $m)) {
        return 'list';
    }

    return null;
}

/** 짧아도 내용 있는 답 — 회피 표현 없을 때 통과 (길이만으로 탈락 금지) */
function eduCoachHasSubstantiveAnswer(string $message): bool
{
    $m = trim($message);
    if ($m === '') {
        return false;
    }
    if (preg_match('/^(음+|아+|그래|맞아|네+|응+)\.?$/u', $m)) {
        return false;
    }
    $hangul = preg_replace('/[^가-힣]/u', '', $m) ?? '';
    if (mb_strlen($hangul) >= 3) {
        return true;
    }

    return mb_strlen($m) >= 10;
}

function eduCoachAxisStudentPass(string $message, ?string $evasion): bool
{
    if ($evasion !== null) {
        return false;
    }

    return eduCoachHasSubstantiveAnswer($message);
}

function eduCoachSpoonfeedGuard(string $text): string
{
    $snippets = [];
    $out = preg_replace_callback(
        '/\{\{snippet\|\w+\}\}\s*[\s\S]*?\s*\{\{\/snippet\}\}/u',
        static function (array $m) use (&$snippets): string {
            $key = '___SNIP_' . count($snippets) . '___';
            $snippets[$key] = $m[0];

            return $key;
        },
        $text
    ) ?? $text;

    $patterns = [
        '/~?(중요하지|가\s*답이지|가\s*맞지)\??/u' => '?',
        '/정리하면\s*[^.!?]+[.!?]/u' => '',
        '/많은\s*분석가/u' => '기사에는',
        '/방어\s*투자\s*쪽으로\s*정리/u' => '네 말로 한 문장 정리',
        '/the\s*gist[^\s]*/iu' => '',
        '/gist랑\s*[^\s.]*/iu' => '',
        '/기사는\s*[^.!?—]+(?:쪽|방향)으로\s*갔[^.!?]*[.!?]?/u' => '',
    ];
    foreach ($patterns as $pat => $rep) {
        $out = preg_replace($pat, $rep, $out) ?? $out;
    }

    $out = trim(preg_replace('/\s+/u', ' ', $out) ?? $out);
    foreach ($snippets as $key => $snippet) {
        $out = str_replace($key, trim($snippet), $out);
    }

    return $out;
}

function eduCoachGuideNormalizeCompareKey(string $text): string
{
    $t = mb_strtolower(trim($text));
    $t = preg_replace('/[?？!！。．\.…,，、\s]+/u', '', $t) ?? $t;
    $t = preg_replace('/(을까|일까|할까|될까|는가|인가|겠어|거야|정말|진짜|것|거)$/u', '', $t) ?? $t;

    return $t;
}

function eduCoachGuideTextsOverlap(string $a, string $b): bool
{
    if ($a === '' || $b === '') {
        return false;
    }
    $ak = eduCoachGuideNormalizeCompareKey($a);
    $bk = eduCoachGuideNormalizeCompareKey($b);
    if ($ak === '' || $bk === '') {
        return false;
    }
    if ($ak === $bk || str_contains($ak, $bk) || str_contains($bk, $ak)) {
        return true;
    }
    similar_text($ak, $bk, $pct);

    return $pct >= 68.0;
}

/** @param array<string, mixed> $quest @return list<array<string, string>> */
function eduCoachGuideResolveAxes(array $quest, int $coachLevel): array
{
    return match (eduCoachLevelCoachPath($coachLevel)) {
        'l1' => eduCoachGuideElementaryAxes($quest),
        'l2' => eduCoachGuideBridgeAxes($quest),
        'l3' => eduCoachGuideMiddleAxes($quest),
        'l4' => eduCoachGuideUpperAxes($quest),
        default => eduCoachGuideAxes($quest),
    };
}

/** @param array<string, string> $axis */
function eduCoachGuideIntroForCoachLevel(
    array $axis,
    int $index,
    int $total,
    string $openingContext,
    string $hookShort,
    int $coachLevel
): string {
    if (eduCoachLevelCoachPath($coachLevel) === 'l4') {
        return eduCoachGuideUpperIntroAxis($axis, $index, $total, $openingContext, $hookShort);
    }

    return eduCoachGuideIntroAxis($axis, $index, $total, $openingContext, $hookShort);
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool}
 */
function eduCoachGuideHandleOpening(
    array $blueprint,
    array $quest,
    string $opening,
    int $coachLevel = EDU_COACH_LEVEL_ADVANCED
): array {
    if (!eduCoachGuideElementaryReady()) {
        $coachLevel = EDU_COACH_LEVEL_L5;
    }

    $path = eduCoachLevelCoachPath($coachLevel);
    if ($path === 'l1') {
        return eduCoachGuideElementaryHandleOpening($blueprint, $quest, $opening, $coachLevel);
    }
    if ($path === 'l2') {
        return eduCoachGuideBridgeHandleOpening($blueprint, $quest, $opening, $coachLevel);
    }
    if ($path === 'l3') {
        return eduCoachGuideMiddleHandleOpening($blueprint, $quest, $opening, $coachLevel);
    }
    if ($path === 'l4') {
        return eduCoachGuideUpperHandleOpening($blueprint, $quest, $opening, $coachLevel);
    }

    $axes = eduCoachGuideAxes($quest);
    $storeLevel = eduCoachLevelNormalize($coachLevel);
    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_opening' => $opening,
        'reason' => $opening,
        'opening_submitted' => true,
        'guide_axis_index' => 0,
        'guide_axis_stall' => 0,
        'guide_axis_answers' => [],
        'coach_level' => $storeLevel,
        'phase' => 'guide_axis',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $evasion = eduCoachDetectEvasion($opening);
    $hints = eduQuestHammerHints($quest);
    $hookShort = trim((string) ($hints['hook_short'] ?? ''));
    if ($evasion !== null) {
        $msg = eduCoachGuideEvasionReply($evasion, $axes[0], 0, $axes, false);
    } else {
        $msg = eduCoachGuideIntroAxis($axes[0], 0, count($axes), $opening, $hookShort);
    }

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer?: bool}
 */
function eduCoachGuideHandleTurn(
    array $blueprint,
    array $quest,
    string $message,
    int $coachLevel = EDU_COACH_LEVEL_ADVANCED
): array {
    if (!eduCoachGuideElementaryReady()) {
        $coachLevel = EDU_COACH_LEVEL_L5;
    }

    $path = eduCoachLevelCoachPath($coachLevel);
    if ($path === 'l1') {
        return eduCoachGuideElementaryHandleTurn($blueprint, $quest, $message, $coachLevel);
    }
    if ($path === 'l2') {
        return eduCoachGuideBridgeHandleTurn($blueprint, $quest, $message, $coachLevel);
    }
    if ($path === 'l3') {
        return eduCoachGuideMiddleHandleTurn($blueprint, $quest, $message, $coachLevel);
    }
    if ($path === 'l4') {
        return eduCoachGuideUpperHandleTurn($blueprint, $quest, $message, $coachLevel);
    }

    $phase = (string) ($blueprint['phase'] ?? '');
    $axes = eduCoachGuideAxes($quest);

    if ($phase === 'guide_conclusion') {
        return eduCoachGuideHandleConclusion($blueprint, $quest, $message, $axes);
    }

    if ($phase !== 'guide_axis') {
        return [
            'blueprint' => $blueprint,
            'message' => '계속 이야기해줘.',
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $idx = (int) ($blueprint['guide_axis_index'] ?? 0);
    $stall = (int) ($blueprint['guide_axis_stall'] ?? 0);
    $evasion = eduCoachDetectEvasion($message);
    $axis = $axes[$idx] ?? $axes[0];
    $axisAnswerMessage = $message;
    $pendingWhy = is_array($blueprint['guide_axis_pending_why'] ?? null)
        ? $blueprint['guide_axis_pending_why']
        : null;
    $pendingWhyIncomplete = false;

    if ($pendingWhy !== null && ($pendingWhy['axis_id'] ?? '') === ($axis['axis_id'] ?? '')) {
        $storedChoice = trim((string) ($pendingWhy['choice'] ?? ''));
        $sameChoice = $storedChoice !== ''
            && eduCoachGuideNormalizeCompareKey($message) === eduCoachGuideNormalizeCompareKey($storedChoice);

        if (!$sameChoice && eduCoachAxisStudentPass($message, $evasion)) {
            $axisAnswerMessage = $storedChoice !== ''
                ? $storedChoice . ' — ' . trim($message)
                : trim($message);
            $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_pending_why' => null]);
        } else {
            $pendingWhyIncomplete = true;
        }
    } elseif (eduCoachGuideMessageMatchesChoiceOption($message, $axis)) {
        $choice = trim($message);
        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_pending_why' => [
                'axis_id' => (string) ($axis['axis_id'] ?? ''),
                'choice' => $choice,
            ],
            'guide_axis_stall' => 0,
        ]);

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(eduCoachGuideWhyFollowUpMessage($axis, $choice)),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    if (!$pendingWhyIncomplete && eduCoachAxisStudentPass($axisAnswerMessage, $evasion)) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axis['axis_id']] = $axisAnswerMessage;
        $idx++;
        $stall = 0;

        if ($idx >= count($axes)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'guide_axis_stall' => 0,
                'phase' => 'guide_conclusion',
            ]);
            $msg = '지금까지 따져본 걸로 **네 결론**은 한 문장으로? '
                . "'나는 ~라고 본다'로만 — **네 말**이어야 해.";

            return [
                'blueprint' => $blueprint,
                'message' => eduCoachSpoonfeedGuard($msg),
                'ui_hint' => 'guide_conclusion',
                'done_guide' => false,
            ];
        }

        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_answers' => $answers,
            'guide_axis_index' => $idx,
            'guide_axis_stall' => 0,
            'guide_axis_pending_why' => null,
        ]);
        $msg = "좋아, 다음으로 넘어가자.\n\n" . eduCoachGuideIntroAxis($axes[$idx], $idx, count($axes));

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $stall++;
    if ($stall >= EDU_COACH_GUIDE_STALL_ESCAPE) {
        $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
        $answers[$axis['axis_id'] . '_skipped'] = $message;
        $idx++;
        $stall = 0;

        if ($idx >= count($axes)) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_axis_answers' => $answers,
                'guide_axis_index' => $idx,
                'phase' => 'guide_conclusion',
            ]);
            $msg = "여기서 막혔구나 — 일단 넘어가자. **네 결론**은 한 문장으로? ('나는 ~라고 본다')";

            return [
                'blueprint' => $blueprint,
                'message' => eduCoachSpoonfeedGuard($msg),
                'ui_hint' => 'guide_conclusion',
                'done_guide' => false,
            ];
        }

        $blueprint = eduMergeBlueprint($blueprint, [
            'guide_axis_answers' => $answers,
            'guide_axis_index' => $idx,
            'guide_axis_stall' => 0,
            'guide_axis_pending_why' => null,
        ]);
        $msg = "막히면 괜찮아 — **다음 축**으로 넘어가자.\n\n" . eduCoachGuideIntroAxis($axes[$idx], $idx, count($axes));

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard($msg),
            'ui_hint' => 'guide_axis',
            'done_guide' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, ['guide_axis_stall' => $stall]);
    $useScaffold = $stall >= 2;
    $msg = eduCoachGuideEvasionReply($evasion, $axis, $idx, $axes, $useScaffold);

    return [
        'blueprint' => $blueprint,
        'message' => eduCoachSpoonfeedGuard($msg),
        'ui_hint' => 'guide_axis',
        'done_guide' => false,
    ];
}

/**
 * @param list<array<string, string>> $axes
 * @param array<string, mixed> $blueprint
 * @return array{blueprint: array, message: string, ui_hint: string, done_guide: bool, advance_hammer: bool}
 */
function eduCoachGuideHandleConclusion(array $blueprint, array $quest, string $message, array $axes): array
{
    $evasion = eduCoachDetectEvasion($message);
    if (!eduCoachAxisStudentPass($message, $evasion) || $evasion === 'ask_conclusion') {
        $stall = (int) ($blueprint['guide_conclusion_stall'] ?? 0) + 1;
        $blueprint = eduMergeBlueprint($blueprint, ['guide_conclusion_stall' => $stall]);
        if ($stall >= EDU_COACH_GUIDE_STALL_ESCAPE) {
            $blueprint = eduMergeBlueprint($blueprint, [
                'guide_student_conclusion' => $message !== '' ? $message : '(미정)',
                'phase' => 'hammer',
            ]);

            return [
                'blueprint' => $blueprint,
                'message' => '애매해도 괜찮아 — 다음 단계로 넘어가자. 아까 다른 시각 하나 짚어볼게.',
                'ui_hint' => 'hammer',
                'done_guide' => true,
                'advance_hammer' => true,
            ];
        }

        return [
            'blueprint' => $blueprint,
            'message' => eduCoachSpoonfeedGuard(
                '결론은 **내가 안 줘**. 지금까지 **네가** 말한 걸 바탕으로 — '
                . '**나는 ~라고 본다** 한 문장만. **네 생각**만 말해줘.'
            ),
            'ui_hint' => 'guide_conclusion',
            'done_guide' => false,
            'advance_hammer' => false,
        ];
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'guide_student_conclusion' => $message,
        'phase' => 'hammer',
    ]);

    return [
        'blueprint' => $blueprint,
        'message' => '고마워 — **네 결론** 받았어. 이제 다른 시각 하나 짚어볼게.',
        'ui_hint' => 'hammer',
        'done_guide' => true,
        'advance_hammer' => true,
    ];
}

/** @param array<string, string> $axis P2-B: article_fact_full for longer tiers */
function eduCoachGuideFactForDisplay(array $axis, string $displayMode = EDU_COACH_GUIDE_FACT_DISPLAY): string
{
    if ($displayMode === 'full' && !empty($axis['article_fact_full'])) {
        return trim((string) $axis['article_fact_full']);
    }
    if ($displayMode === 'medium' && !empty($axis['article_fact_medium'])) {
        return trim((string) $axis['article_fact_medium']);
    }

    return trim((string) ($axis['article_fact'] ?? ''));
}

function eduCoachGuideWrapArticleSnippet(string $text, string $displayMode = EDU_COACH_GUIDE_FACT_DISPLAY): string
{
    if ($text === '') {
        return '';
    }

    return "{{snippet|{$displayMode}}}\n{$text}\n{{/snippet}}";
}

/** @param array<string, string> $axis */
function eduCoachGuideIntroAxis(
    array $axis,
    int $index,
    int $total,
    string $openingContext = '',
    string $hookShort = ''
): string {
    unset($total);
    $point = $axis['point'] ?? '';
    $fact = eduCoachGuideFactForDisplay($axis);
    $q = $axis['core_question'] ?? '';
    $snippet = eduCoachGuideWrapArticleSnippet($fact);
    $snippetBlock = $snippet !== '' ? "\n\n{$snippet}" : '';

    $hookOverlap = $index === 0 && (
        ($hookShort !== '' && eduCoachGuideTextsOverlap($hookShort, $q))
        || ($openingContext !== '' && eduCoachGuideTextsOverlap($openingContext, $q))
    );

    if ($hookOverlap) {
        return '방금 말한 걸 더 따져보자. **' . $point . '**' . $snippetBlock
            . "\n\n위 기사 조각만 놓고 — 네 말을 **강하게** 해주나 **약하게** 해주나?";
    }

    $lead = $index === 0 ? '한 가지부터 따져보자.' : '이번엔 이걸 생각해보자.';

    return "{$lead} **{$point}**{$snippetBlock}\n\n{$q}";
}

/**
 * @param array<string, string> $axis
 * @param list<array<string, string>> $axes
 */
function eduCoachGuideEvasionReply(?string $evasion, array $axis, int $index, array $axes, bool $weakScaffold): string
{
    if ($weakScaffold && !empty($axis['weak_scaffold'])) {
        return $axis['weak_scaffold'];
    }

    if ($evasion === null || $evasion === 'empty') {
        return $axis['core_question'] ?? '네 생각을 한 줄로 말해줘.';
    }

    return match ($evasion) {
        'both', 'list' => '예산·시간은 **하나씩**만 고를 수 있어. **1순위 하나**만 — '
            . ($axis['core_question'] ?? '지금 축에서 한쪽만 골라봐.'),
        'unknown' => '모르겠다는 건 아직 이 축을 안 따진 거야. '
            . ($axis['core_question'] ?? '')
            . ' **맞다 / 틀리다 / 잘 모르겠다** 셋 중 하나만?',
        'deflect' => '나라마다 다를 수 있어. 이 조각만 보고 — '
            . eduCoachGuideWrapArticleSnippet(eduCoachGuideFactForDisplay($axis))
            . ' 이게 네 생각을 **강하게** 해주나 **약하게** 해주나?',
        'defer_article' => '아까 **기사 조각** 중 어느 부분을 말하는 거야? 하나만 짚고, **그게 네 생각이랑** 같은지 다른지만 말해.',
        'ask_conclusion' => '결론은 **내가 안 줘**. 지금은 '
            . ($axis['point'] ?? '이 주제') . ' — ' . ($axis['core_question'] ?? ''),
        default => ($axis['core_question'] ?? '네 생각을 한 줄로 말해줘.'),
    };
}

/** @param array<string, mixed> $blueprint @return list<string> */
function eduCoachGuideReflectionLines(array $blueprint): array
{
    $opening = trim((string) ($blueprint['guide_opening'] ?? $blueprint['reason'] ?? ''));
    $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
    $conclusion = trim((string) ($blueprint['guide_student_conclusion'] ?? ''));
    $rebuttal = trim((string) ($blueprint['rebuttal'] ?? ''));

    $lines = [];
    if ($opening !== '') {
        $lines[] = '처음: ' . mb_substr($opening, 0, 60);
    }
    $i = 1;
    foreach ($answers as $key => $ans) {
        if (str_ends_with((string) $key, '_skipped')) {
            continue;
        }
        $lines[] = "축{$i}: " . mb_substr((string) $ans, 0, 50);
        $i++;
    }
    if ($conclusion !== '') {
        $lines[] = '네 결론: ' . mb_substr($conclusion, 0, 60);
    }
    if ($rebuttal !== '') {
        $lines[] = '다른 시각 들은 뒤: ' . mb_substr($rebuttal, 0, 40);
    }

    while (count($lines) < 3) {
        $lines[] = '네가 말한 내용을 바탕으로 생각이 이어졌어.';
    }

    return array_slice($lines, 0, 3);
}

/** @param array<string, mixed> $blueprint */
function eduCoachGuideBuildStudentReason(array $blueprint): string
{
    $parts = [];
    $opening = trim((string) ($blueprint['guide_opening'] ?? $blueprint['reason'] ?? ''));
    if ($opening !== '') {
        $parts[] = $opening;
    }
    $answers = is_array($blueprint['guide_axis_answers'] ?? null) ? $blueprint['guide_axis_answers'] : [];
    foreach ($answers as $key => $ans) {
        if (str_ends_with((string) $key, '_skipped')) {
            continue;
        }
        $t = trim((string) $ans);
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    $conclusion = trim((string) ($blueprint['guide_student_conclusion'] ?? ''));
    if ($conclusion !== '') {
        $parts[] = $conclusion;
    }

    return implode(' / ', $parts);
}

/** @param array<string, mixed> $blueprint */
function eduCoachGuideProgress(array $blueprint): int
{
    $phase = (string) ($blueprint['phase'] ?? '');
    $idx = (int) ($blueprint['guide_axis_index'] ?? 0);
    $axisDivisor = eduCoachLevelAxisDivisor((int) ($blueprint['coach_level'] ?? EDU_COACH_LEVEL_L5));
    $base = 15;
    if ($phase === 'guide_axis') {
        return min(55, $base + (int) (30 * ($idx / max(1, $axisDivisor))));
    }
    if ($phase === 'guide_conclusion') {
        return 60;
    }
    if ($phase === 'hammer') {
        return 72;
    }
    if ($phase === 'reflection') {
        return 85;
    }

    return eduBlueprintProgress($blueprint);
}

/** 강화/약화 프롬프트 — 입장 선택(정답 없음) */
function eduCoachGuideStrengthenWeakenOptions(): array
{
    return ['강하게', '약하게'];
}

function eduCoachGuideMessageIsStrengthenWeakenPrompt(string $message): bool
{
    return (bool) preg_match('/강하게.*약하게/u', $message);
}

/** @param array<string, string> $axis */
function eduCoachGuideMessageMatchesChoiceOption(string $message, array $axis): bool
{
    $m = trim($message);
    if ($m === '') {
        return false;
    }
    if (preg_match('/[—\-].{4,}/u', $m)) {
        return false;
    }
    if (mb_strlen($m) > 48) {
        return false;
    }

    $mn = eduCoachGuideNormalizeCompareKey($m);
    foreach (eduCoachGuideStrengthenWeakenOptions() as $opt) {
        if ($mn === eduCoachGuideNormalizeCompareKey($opt)) {
            return true;
        }
    }

    $allOpts = array_merge(
        is_array($axis['choice_options'] ?? null) ? $axis['choice_options'] : [],
        is_array($axis['weak_choice_options'] ?? null) ? $axis['weak_choice_options'] : []
    );
    foreach ($allOpts as $opt) {
        $on = eduCoachGuideNormalizeCompareKey((string) $opt);
        if ($on !== '' && $mn === $on) {
            return true;
        }
    }

    return false;
}

/** @param array<string, string> $axis */
function eduCoachGuideWhyFollowUpMessage(array $axis, string $choice): string
{
    $c = trim($choice);
    $point = trim((string) ($axis['point'] ?? ''));
    if ($point !== '') {
        return "**{$c}** 골랐구나. **{$point}** — 왜 그렇게 봤어? 한두 문장만.";
    }

    return "**{$c}** — **왜** 그쪽이야? 짧게 이유만 말해줘.";
}

function eduCoachGuideNarrativePromptOneLine(string $message, int $maxLen = 46): string
{
    $plain = preg_replace('/\*\*/u', '', $message) ?? $message;
    $plain = preg_replace('/\{\{snippet[\s\S]*?\{\{\/snippet\}\}/u', '', $plain) ?? $plain;
    $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    if ($plain === '') {
        return '';
    }
    $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $plain) ?: [])));
    $first = $lines[0] ?? $plain;
    if (mb_strlen($first) <= $maxLen) {
        return $first;
    }

    return mb_substr($first, 0, $maxLen - 1) . '…';
}

/** @param array<string, string> $axis */
function eduCoachGuideAxisChoiceOptions(array $axis, string $assistantMessage): array
{
    $scaffold = trim((string) ($axis['weak_scaffold'] ?? ''));
    if ($scaffold !== '' && eduCoachGuideMessageUsesWeakScaffold($assistantMessage, $scaffold)) {
        $weak = $axis['weak_choice_options'] ?? null;
        if (is_array($weak) && $weak !== []) {
            return array_values(array_map('strval', $weak));
        }
    }

    $options = $axis['choice_options'] ?? null;
    if (!is_array($options) || $options === []) {
        return [];
    }

    return array_values(array_map('strval', $options));
}

function eduCoachGuideMessageUsesWeakScaffold(string $message, string $scaffold): bool
{
    if (eduCoachGuideTextsOverlap($scaffold, $message)) {
        return true;
    }
    $plain = static function (string $text): string {
        $t = preg_replace('/\*\*/u', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    };
    $msg = $plain($message);
    $sc = $plain($scaffold);
    if ($sc === '' || $msg === '') {
        return false;
    }
    $prefix = mb_substr($sc, 0, min(24, mb_strlen($sc)));

    return $prefix !== '' && str_contains($msg, $prefix);
}

/**
 * axis_guide 응답용 — 선택형일 때만 choice_question/options (서술형 FSM 무관, JSON 추가만).
 *
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array{choice_question: true, options: list<string>, choice_question_text: string}|null
 */
function eduCoachGuideChoiceMeta(array $blueprint, array $quest, string $assistantMessage): ?array
{
    $phase = (string) ($blueprint['phase'] ?? '');
    if ($phase !== 'guide_axis' || trim($assistantMessage) === '') {
        return null;
    }

    if (!empty($blueprint['guide_axis_pending_why'])) {
        return null;
    }

    if (eduCoachGuideMessageIsStrengthenWeakenPrompt($assistantMessage)) {
        $options = eduCoachGuideStrengthenWeakenOptions();

        return [
            'choice_question' => true,
            'options' => $options,
            'choice_question_text' => eduCoachGuideChoiceQuestionText($assistantMessage, $options),
        ];
    }

    $idx = (int) ($blueprint['guide_axis_index'] ?? 0);
    $coachLevel = eduCoachLevelNormalize((int) ($blueprint['coach_level'] ?? EDU_COACH_LEVEL_L5));
    $axes = eduCoachGuideResolveAxes($quest, $coachLevel);
    $axis = $axes[$idx] ?? null;
    if ($axis === null) {
        return null;
    }

    $options = eduCoachGuideAxisChoiceOptions($axis, $assistantMessage);
    if ($options === []) {
        return null;
    }

    if (!eduCoachGuideAssistantPromptsChoice($assistantMessage, $axis, $options)) {
        return null;
    }

    return [
        'choice_question' => true,
        'options' => $options,
        'choice_question_text' => eduCoachGuideChoiceQuestionText($assistantMessage, $options, $axis),
    ];
}

/** @param array<string, string> $axis @param list<string> $options */
function eduCoachGuideAssistantPromptsChoice(string $message, array $axis, array $options): bool
{
    $scaffold = trim((string) ($axis['weak_scaffold'] ?? ''));
    if ($scaffold !== '' && eduCoachGuideMessageUsesWeakScaffold($message, $scaffold)) {
        return true;
    }

    $core = trim((string) ($axis['core_question'] ?? ''));
    if ($core !== '' && str_contains($message, $core)) {
        return true;
    }

    if ($core !== '' && eduCoachGuideTextsOverlap($core, $message)) {
        return true;
    }

    $hits = 0;
    foreach ($options as $opt) {
        $needle = preg_replace('/\s+/u', '', (string) $opt) ?? '';
        $hay = preg_replace('/\s+/u', '', $message) ?? '';
        if ($needle !== '' && str_contains($hay, $needle)) {
            $hits++;
        }
    }
    if ($hits >= 2) {
        return true;
    }

    if (preg_match('/1순위\s*하나/u', $message) && count($options) >= 2) {
        return true;
    }

    $point = trim((string) ($axis['point'] ?? ''));
    if ($point !== '' && str_contains($message, $point) && count($options) >= 2) {
        return true;
    }

    return false;
}

/** @param list<string> $options */
function eduCoachGuideStripInlineChoiceFromQuestion(string $question, array $options): string
{
    $out = trim($question);
    if ($out === '' || $options === []) {
        return $out;
    }

    $out = preg_replace('/\s*\([^)]*(?:\/|／)[^)]*\)\s*$/u', '', $out) ?? $out;
    $out = preg_replace('/\s*\(한쪽만\)\s*$/u', '', $out) ?? $out;
    $out = preg_replace('/\s*[\—\-]\s*네\s*말로\s*한쪽만\.?\s*$/u', '', $out) ?? $out;

    if (eduCoachGuideMessageIsStrengthenWeakenPrompt($out)) {
        $out = preg_replace('/\s*위\s*기사\s*조각만[\s\S]*$/u', '', $out) ?? $out;
        $out = preg_replace('/\s*[\—\-]?\s*네\s*말을?\s*\*\*강하게\*\*[\s\S]*$/u', '', $out) ?? $out;
    }

    foreach ($options as $opt) {
        $escaped = preg_quote((string) $opt, '/');
        $out = preg_replace('/\s*[\—\-]\s*\*\*' . $escaped . '\*\*\s*\/\s*/u', ' — ', $out) ?? $out;
        $out = preg_replace('/\s*\/\s*\*\*' . $escaped . '\*\*/u', '', $out) ?? $out;
    }

    $out = preg_replace('/\s*\/\s*[\s\S]*$/u', '', $out) ?? $out;
    $out = trim(preg_replace('/\s+/u', ' ', $out) ?? $out);

    return $out !== '' ? $out : trim($question);
}

/** @param list<string> $options @param array<string, string> $axis */
function eduCoachGuideChoiceQuestionText(string $assistantMessage, array $options, array $axis = []): string
{
    $core = trim((string) ($axis['core_question'] ?? ''));
    if ($core !== '' && (str_contains($assistantMessage, $core) || eduCoachGuideTextsOverlap($core, $assistantMessage))) {
        $plainCore = preg_replace('/\*\*/u', '', $core) ?? $core;

        return eduCoachGuideStripInlineChoiceFromQuestion($plainCore, $options);
    }

    if (eduCoachGuideMessageIsStrengthenWeakenPrompt($assistantMessage)) {
        return '위 기사 조각만 놓고 — 네 말을 어떻게 받쳐 주나?';
    }

    $plain = preg_replace('/\{\{snippet\|\w+\}\}[\s\S]*?\{\{\/snippet\}\}/u', '', $assistantMessage) ?? $assistantMessage;
    $plain = preg_replace('/\*\*/u', '', $plain) ?? $plain;
    $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', trim($plain)) ?: [])));
    $questionLine = $lines !== [] ? $lines[count($lines) - 1] : trim($plain);

    return eduCoachGuideStripInlineChoiceFromQuestion($questionLine, $options);
}

/**
 * chat.php axis_guide JSON에 선택형 필드만 병합 (서술형 경로·FSM 변경 없음).
 *
 * @param array<string, mixed> $response
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @return array<string, mixed>
 */
function eduCoachGuideAttachChoiceFields(
    array $response,
    array $blueprint,
    array $quest,
    string $assistantMessage
): array {
    $meta = eduCoachGuideChoiceMeta($blueprint, $quest, $assistantMessage);
    if ($meta !== null) {
        return array_merge($response, $meta);
    }

    $line = eduCoachGuideNarrativePromptOneLine($assistantMessage);
    if ($line !== '') {
        $response['narrative_prompt'] = $line;
    }

    return $response;
}
