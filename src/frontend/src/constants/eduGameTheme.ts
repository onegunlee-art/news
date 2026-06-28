/**
 * GIST EDU — 게임화 UI 토큰 (eduGame.*)
 * 흑백 EDU_BRAND / GIST_EDU_DESIGN_SYSTEM과 격리. 코치·완주 화면 등 탐구 UX 전용.
 */
export const eduGame = {
  /** Primary accent — 브랜드 오렌지 #D85A30 */
  primary: '#D85A30',
  primaryDark: '#B84A26',
  primaryLight: '#FEF3EF',
  primaryRing: 'rgba(216, 90, 48, 0.35)',

  bg: '#ffffff',
  ink: '#1a1a1a',
  muted: '#666666',
  border: '#e8e8e8',
  surface: '#f5f5f5',

  /** 말풍선 */
  bubbleCoach: '#ffffff',
  bubbleCoachBorder: '#D85A30',
  bubbleStudent: '#D85A30',

  /** 둥근 UI */
  radiusButton: '0.75rem', /* rounded-xl */
  radiusBubble: '1.25rem',
  radiusCard: '0.75rem',

  fontBody: "'Noto Sans KR', sans-serif",
  /** gistudy 로고 워드마크 */
  fontLogo: "'Playfair Display', Georgia, 'Times New Roman', serif",

  /** Duolingo-scale typography (14세+ 모바일) */
  fontSize: {
    caption: '0.8125rem', /* 13px — 탐구 바 보조, 메타 뱃지 */
    label: '0.875rem', /* 14px — 짧은 안내 */
    body: '1rem', /* 16px — 말풍선, 입력 */
    bodyLg: '1.0625rem', /* 17px — 기사 요약 본문 */
    button: '1.0625rem', /* 17px — footer CTA */
  },
  lineHeight: {
    body: 1.625,
    snippet: 1.7,
  },

  /** 한글 줄바꿈 — 어절 단위, 긴 토큰은 예외 분리 */
  textWrap: {
    wordBreak: 'keep-all',
    overflowWrap: 'break-word',
  },
} as const

export const eduGameClasses = {
  btnPrimary:
    'rounded-xl font-bold text-white shadow-sm active:scale-[0.98] transition-transform disabled:opacity-40 disabled:active:scale-100',
  input:
    'rounded-xl border-2 px-4 py-3 focus:outline-none focus:ring-2 transition-shadow edu-game-text-ko',
  coachBubble:
    'rounded-2xl rounded-bl-md border-2 shadow-sm edu-game-text-ko whitespace-pre-wrap',
  studentBubble:
    'rounded-2xl rounded-br-md text-white shadow-sm edu-game-text-ko whitespace-pre-wrap',
  textKo: 'edu-game-text-ko',
  textKoPre: 'edu-game-text-ko whitespace-pre-wrap',
  /** 성취 순간 애니메이션 (1-3) — prefers-reduced-motion 대응 */
  animAxisPop: 'edu-game-axis-pop',
  animAxisCheckPop: 'edu-game-axis-check-pop',
  animAxisCurrent: 'edu-game-axis-current',
  animAxisFilling: 'edu-game-axis-filling',
  animCoachIn: 'edu-game-coach-in',
  animExploreNudge: 'edu-game-explore-nudge',
  animExploreToast: 'edu-game-explore-toast',
  chatShell: 'edu-game-chat-shell',
  chatScroll: 'edu-game-chat-scroll',
  composeSheet: 'edu-game-compose-sheet',
  composeHandle: 'edu-game-compose-handle',
} as const
