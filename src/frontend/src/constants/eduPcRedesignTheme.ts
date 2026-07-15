import type { CSSProperties } from 'react'

/**
 * GIST EDU — PC 3분할 전용 디자인 토큰 (≥640px)
 * eduGameTheme.ts(모바일 밝은 배경)와 격리. 수정 금지 대상 아님.
 */
export const eduPc = {
  bg: '#070707',
  primary: '#E85D2C',
  primaryGlow: 'rgba(232, 93, 44, 0.35)',
  text: '#f5f5f5',
  textMuted: 'rgba(255,255,255,0.55)',
  textDim: 'rgba(255,255,255,0.38)',
  border: 'rgba(255,255,255,0.06)',
  borderStrong: 'rgba(255,255,255,0.09)',
  surface: 'rgba(255,255,255,0.03)',
  surfaceHover: 'rgba(255,255,255,0.06)',
  gridLine: 'rgba(255,255,255,0.04)',
  gridSize: 48,
  columnJourney: 210,
  columnBoard: 320,
  fontSerif: "'Noto Serif KR', Georgia, 'Times New Roman', serif",
  fontSans: "'Noto Sans KR', sans-serif",
  coachSize: '19px',
  scanBar: 'linear-gradient(90deg, transparent, #E85D2C, transparent)',
} as const

export const eduPcClasses = {
  textKo: 'break-keep [overflow-wrap:anywhere]',
  shell:
    'min-h-dvh flex flex-col overflow-hidden relative',
  gridBg:
    'pointer-events-none absolute inset-0 opacity-100',
  topGlow:
    'pointer-events-none absolute inset-x-0 top-0 h-48',
} as const

export function eduPcShellStyle(): CSSProperties {
  return {
    backgroundColor: eduPc.bg,
    color: eduPc.text,
    fontFamily: eduPc.fontSans,
    backgroundImage: `
      linear-gradient(${eduPc.gridLine} 1px, transparent 1px),
      linear-gradient(90deg, ${eduPc.gridLine} 1px, transparent 1px)
    `,
    backgroundSize: `${eduPc.gridSize}px ${eduPc.gridSize}px`,
  }
}

export function eduPcTopGlowStyle(): CSSProperties {
  return {
    background: `radial-gradient(ellipse 80% 60% at 50% -10%, ${eduPc.primaryGlow}, transparent 70%)`,
  }
}
