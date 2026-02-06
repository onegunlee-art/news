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
