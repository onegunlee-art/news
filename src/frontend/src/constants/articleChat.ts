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
 * 고정 칩 4개 — 모든 기사에 동일하게 노출.
 */
export const FIXED_CHIPS: ChatChip[] = [
  {
    id: 'content_summary_5',
    category: 'understanding',
    label: '이 콘텐츠의 핵심을 5줄로 정리해줘',
    priority: 1,
  },
  {
    id: 'scenario_forecast',
    category: 'scenario',
    label: '앞으로 어떻게 전개될 가능성이 높아?',
    priority: 2,
  },
  {
    id: 'why_important',
    category: 'understanding',
    label: '이게 왜 중요한 사건이야?',
    priority: 3,
  },
  {
    id: 'impact_korea',
    category: 'structure',
    label: '우리나라에 어떤 영향이 있을까?',
    priority: 4,
  },
]

/**
 * 칩 노출 규칙 (API 폴백용)
 */
export const CHIP_DISPLAY = {
  maxVisible: 4,
  fixedCount: 4,
  dynamicCount: 0,
} as const

/**
 * 답변 블록마다 표시하는 면책 문구 (config disclaimer_footer 와 동일)
 */
export const DISCLAIMER_FOOTER =
  '이 답변은 AI가 the gist. 콘텐츠들을 바탕으로 생성한 것이며, 사실과 다를 수 있습니다.'

export const SCENARIO_DISCLAIMER =
  '이 시나리오는 현재 공개된 정보에 기반한 가능성 분석이며, 실제 결과와 다를 수 있습니다.'

/**
 * 세션 제한 (표시용 폴백; 실제 한도는 서버 config)
 */
export const CHAT_LIMITS = {
  maxQuestionsPerSession: 4,
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
 */
export const CATEGORY_ROLES: Record<ChipCategory, string> = {
  understanding: '진입',
  structure: '이해',
  intention: '몰입',
  risk: '긴장',
  scenario: '확장',
}
