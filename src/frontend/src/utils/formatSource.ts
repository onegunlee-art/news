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

/**
 * 화면/TTS 공통 매체 설명 문장 생성
 * "이 글은 {date}자, {source}에 게재된 "{original_title}" 기사를 The Gist가 AI를 통해 분석/정리한 것 입니다."
 */
export function buildEditorialLine(params: {
  dateStr: string
  sourceDisplay: string
  originalTitle: string
}): string {
  const { dateStr, sourceDisplay, originalTitle } = params
  const datePart = dateStr ? `${dateStr}자, ` : ''
  return `이 글은 ${datePart}${sourceDisplay}에 게재된 "${originalTitle}" 기사를 The Gist가 AI를 통해 분석/정리한 것 입니다.`
}
