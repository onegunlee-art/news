export type ChipCategory =
  | 'understanding'
  | 'structure'
  | 'intention'
  | 'risk'
  | 'scenario'

export interface ChatChip {
  id: string
  category: ChipCategory
  label: string
  priority?: number
  isDynamic?: boolean
}

/**
 * 고정 칩 5개 — 모든 기사에 동일하게 노출.
 * 순서: 쉬운(이해) → 흥미(구조/의도) → 깊은(리스크/시나리오)
 */
export const FIXED_CHIPS: ChatChip[] = [
  {
    id: 'understand_summary',
    category: 'understanding',
    label: '이 뉴스 핵심만 5줄로 정리해줘',
    priority: 1,
  },
  {
    id: 'structure_benefit',
    category: 'structure',
    label: '누가 실제로 이득을 보는 구조야?',
    priority: 2,
  },
  {
    id: 'intention_hidden',
    category: 'intention',
    label: '이 결정 뒤에 숨은 의도는 뭐야?',
    priority: 3,
  },
  {
    id: 'risk_worst',
    category: 'risk',
    label: '이 상황에서 가장 위험한 시나리오는?',
    priority: 4,
  },
  {
    id: 'scenario_forecast',
    category: 'scenario',
    label: '앞으로 어떻게 전개될 가능성이 높아?',
    priority: 5,
  },
]

/**
 * 교체용 풀 — 고정 칩 대신 기사 유형에 따라 교체할 수 있다.
 */
export const EXTRA_CHIP_POOL: ChatChip[] = [
  { id: 'understand_why',       category: 'understanding', label: '이게 왜 중요한 사건이야?' },
  { id: 'understand_oneline',   category: 'understanding', label: '지금 상황을 한 문장으로 설명하면?' },
  { id: 'structure_loser',      category: 'structure',     label: '이 사건으로 손해 보는 쪽은 누구야?' },
  { id: 'structure_balance',    category: 'structure',     label: '힘의 균형이 어떻게 바뀌고 있어?' },
  { id: 'intention_choice',     category: 'intention',     label: '이 사람은 왜 이런 선택을 했을까?' },
  { id: 'intention_alternative', category: 'intention',    label: '다른 선택지는 없었을까?' },
  { id: 'risk_failure',         category: 'risk',          label: '어디서 문제가 터질 가능성이 커?' },
  { id: 'scenario_best_worst',  category: 'scenario',      label: '최악·최선 시나리오를 나눠서 설명해줘' },
]

/**
 * 칩 노출 규칙
 */
export const CHIP_DISPLAY = {
  maxVisible: 7,
  fixedCount: 5,
  dynamicCount: 2,
} as const

/**
 * 챗 하단 고정 면책 문구
 */
export const DISCLAIMER_FOOTER =
  '이 답변은 AI가 기사 내용을 기반으로 생성한 것이며, 사실과 다를 수 있습니다.'

export const SCENARIO_DISCLAIMER =
  '이 시나리오는 현재 공개된 정보에 기반한 가능성 분석이며, 실제 결과와 다를 수 있습니다.'

/**
 * 챗 진입 문구 (기사 하단)
 */
export const CHAT_INTRO_COPY = '이 뉴스, 더 깊이 이해해보세요'

/**
 * 세션 제한
 */
export const CHAT_LIMITS = {
  maxQuestionsPerSession: 3,
  maxInputChars: 500,
} as const

/**
 * 카테고리 한글 라벨 (UI 뱃지 등)
 */
export const CATEGORY_LABELS: Record<ChipCategory, string> = {
  understanding: '이해',
  structure: '구조',
  intention: '의도',
  risk: '리스크',
  scenario: '미래',
}

/**
 * 카테고리별 역할 설명 (내부 참조용)
 * understanding → 진입 / structure → 이해 / intention → 몰입 / risk → 긴장 / scenario → 확장
 */
export const CATEGORY_ROLES: Record<ChipCategory, string> = {
  understanding: '진입',
  structure: '이해',
  intention: '몰입',
  risk: '긴장',
  scenario: '확장',
}
