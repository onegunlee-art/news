/** gistudy PC narrative v2 — 다크 3분할 UI 토큰 (모바일 eduGameTheme과 격리) */
export const eduPc = {
  bg: '#070707',
  orange: '#E85D2C',
  orangeDim: 'rgba(232, 93, 44, 0.15)',
  orangeGlow: 'rgba(232, 93, 44, 0.35)',
  ink: '#f5f5f5',
  inkMuted: 'rgba(255, 255, 255, 0.55)',
  inkDim: 'rgba(255, 255, 255, 0.35)',
  border: 'rgba(255, 255, 255, 0.08)',
  borderSubtle: 'rgba(255, 255, 255, 0.06)',
  borderDashed: 'rgba(255, 255, 255, 0.12)',
  cardBg: 'rgba(255, 255, 255, 0.03)',
  cardFilledGradient: 'linear-gradient(135deg, rgba(232,93,44,0.12) 0%, rgba(255,255,255,0.04) 100%)',
  gridLine: 'rgba(255, 255, 255, 0.018)',
  topGlow: 'radial-gradient(ellipse 80% 50% at 50% -20%, rgba(232,93,44,0.18) 0%, transparent 70%)',
  fontHeadline: "'Noto Serif KR', Georgia, serif",
  fontBody: "'Noto Sans KR', sans-serif",
  fontLogo: "'Noto Serif KR', Georgia, serif",
  radiusCard: '13px',
  radiusButton: '11px',
  journeyWidth: 210,
  boardWidth: 320,
} as const

export const eduPcClasses = {
  textKo: 'break-keep [overflow-wrap:break-word]',
  shell:
    'edu-pc-shell relative flex flex-col h-dvh overflow-hidden text-[#f5f5f5]',
  gridBg:
    'pointer-events-none absolute inset-0 opacity-100 [background-size:48px_48px] [background-image:linear-gradient(rgba(255,255,255,0.018)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.018)_1px,transparent_1px)]',
  topGlow: 'pointer-events-none absolute inset-x-0 top-0 h-48',
} as const
