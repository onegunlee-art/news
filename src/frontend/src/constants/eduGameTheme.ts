/**
 * GIST EDU — 게임화 UI 토큰 (eduGame.*)
 * 흑백 EDU_BRAND / GIST_EDU_DESIGN_SYSTEM과 격리. 코치·완주 화면 등 탐구 UX 전용.
 */
export const eduGame = {
  /** Primary accent — 탐구 체크, CTA, 학생 말풍선 */
  primary: '#f05123',
  primaryDark: '#d9451c',
  primaryLight: '#fef3ef',
  primaryRing: 'rgba(240, 81, 35, 0.35)',

  bg: '#ffffff',
  ink: '#1a1a1a',
  muted: '#666666',
  border: '#e8e8e8',
  surface: '#f5f5f5',

  /** 말풍선 */
  bubbleCoach: '#ffffff',
  bubbleCoachBorder: '#f05123',
  bubbleStudent: '#f05123',

  /** 둥근 UI */
  radiusButton: '0.75rem', /* rounded-xl */
  radiusBubble: '1.25rem',
  radiusCard: '0.75rem',

  fontBody: "'Noto Sans KR', sans-serif",
} as const

export const eduGameClasses = {
  btnPrimary:
    'rounded-xl font-bold text-white shadow-sm active:scale-[0.98] transition-transform disabled:opacity-40 disabled:active:scale-100',
  input:
    'rounded-xl border-2 px-4 py-3 text-sm focus:outline-none focus:ring-2 transition-shadow',
  coachBubble:
    'rounded-2xl rounded-bl-md border-2 shadow-sm',
  studentBubble:
    'rounded-2xl rounded-br-md text-white shadow-sm',
} as const
