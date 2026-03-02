/**
 * 화면에 표시할 출처명 정규화
 * e.g. "Foreign Affairs Magazine" → "Foreign Affairs" (맨 뒤 " Magazine" 제거)
 */
export function formatSourceDisplayName(source: string | null | undefined): string {
  if (source == null || typeof source !== 'string') return ''
  const trimmed = source.trim()
  if (!trimmed) return ''
  const suffix = ' magazine'
  if (trimmed.toLowerCase().endsWith(suffix)) {
    return trimmed.slice(0, -suffix.length).trim()
  }
  return trimmed
}

/** 매체 설명 기본 포맷 (날짜 제외) */
const EDITORIAL_LINE_FORMAT = '이 글은 {source}에 게재된 {title} 글의 시각을 참고하였습니다.'

/**
 * 화면/TTS 공통 매체 설명 문장 생성
 * "이 글은 {source}에 게재된 {originalTitle} 글의 시각을 참고하였습니다."
 */
export function buildEditorialLine(params: {
  dateStr?: string
  sourceDisplay: string
  originalTitle: string
}): string {
  const { sourceDisplay, originalTitle } = params
  return EDITORIAL_LINE_FORMAT.replace('{source}', sourceDisplay).replace('{title}', originalTitle)
}

/**
 * 매체 설명 한 줄에서 매체(source)와 원문 제목(title) 추출
 * 포맷: "이 글은 {source}에 게재된 {title} 글의 시각을 참고하였습니다."
 */
export function parseEditorialLine(line: string): { source: string; title: string } | null {
  const trimmed = line.trim()
  const match = trimmed.match(/^이 글은 (.+?)에 게재된 (.+?) 글의 시각을 참고하였습니다\.?$/)
  if (match) {
    return { source: match[1].trim(), title: match[2].trim() }
  }
  return null
}
