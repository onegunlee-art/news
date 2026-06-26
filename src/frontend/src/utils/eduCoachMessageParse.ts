export type CoachMessageSegment =
  | { type: 'text'; value: string }
  | { type: 'snippet'; value: string; display: string }

export type CoachBoldSegment =
  | { type: 'plain'; value: string }
  | { type: 'bold'; value: string }

const SNIPPET_RE = /\{\{snippet\|(\w+)\}\}\s*([\s\S]*?)\s*\{\{\/snippet\}\}/g
const BOLD_RE = /\*\*([^*]+)\*\*/g

/** 코치 **강조** → 렌더용 세그먼트 (기존 **만 변환, 새 강조 생성 없음) */
export function parseCoachBoldSegments(text: string): CoachBoldSegment[] {
  const segments: CoachBoldSegment[] = []
  let lastIndex = 0
  BOLD_RE.lastIndex = 0
  let match: RegExpExecArray | null

  while ((match = BOLD_RE.exec(text)) !== null) {
    if (match.index > lastIndex) {
      segments.push({ type: 'plain', value: text.slice(lastIndex, match.index) })
    }
    segments.push({ type: 'bold', value: match[1] ?? '' })
    lastIndex = match.index + match[0].length
  }

  const tail = text.slice(lastIndex)
  if (tail !== '') {
    segments.push({ type: 'plain', value: tail })
  }

  if (segments.length === 0) {
    segments.push({ type: 'plain', value: text })
  }

  return segments
}

/** 미리보기·한 줄 라벨 — ** 제거 */
export function stripCoachBoldMarkers(text: string): string {
  return text.replace(/\*\*([^*]+)\*\*/g, '$1')
}

/** 타입라이터 중 미완성 ** 마커 숨김 */
export function stripIncompleteCoachBold(text: string): string {
  const open = text.lastIndexOf('**')
  if (open === -1) return text
  const tail = text.slice(open + 2)
  if (!tail.includes('**')) {
    return text.slice(0, open)
  }
  return text
}

export function coachMessageHasSnippet(content: string): boolean {
  return content.includes('{{snippet|')
}

/** Parse axis-guide coach messages with optional article snippet blocks. */
export function parseCoachAssistantMessage(content: string): CoachMessageSegment[] {
  const segments: CoachMessageSegment[] = []
  let lastIndex = 0
  SNIPPET_RE.lastIndex = 0
  let match: RegExpExecArray | null

  while ((match = SNIPPET_RE.exec(content)) !== null) {
    const before = content.slice(lastIndex, match.index)
    if (before.trim() !== '') {
      segments.push({ type: 'text', value: before.trimEnd() })
    }
    segments.push({
      type: 'snippet',
      display: match[1] ?? 'summary',
      value: match[2]?.trim() ?? '',
    })
    lastIndex = match.index + match[0].length
  }

  const tail = content.slice(lastIndex)
  if (tail.trim() !== '') {
    segments.push({ type: 'text', value: tail.trimStart() })
  }

  if (segments.length === 0 || (segments.length === 1 && segments[0]?.type === 'text' && content.includes('{{snippet|'))) {
    const fallback = content.match(/\{\{snippet\|(\w+)\}\}\s*([\s\S]*?)\s*\{\{\/snippet\}\}/)
    if (fallback) {
      const before = content.slice(0, fallback.index ?? 0).trim()
      const after = content.slice((fallback.index ?? 0) + fallback[0].length).trim()
      segments.length = 0
      if (before) segments.push({ type: 'text', value: before })
      segments.push({ type: 'snippet', display: fallback[1] ?? 'summary', value: (fallback[2] ?? '').trim() })
      if (after) segments.push({ type: 'text', value: after })
    }
  }

  if (segments.length === 0) {
    segments.push({ type: 'text', value: content })
  }

  return segments
}

/** 긴 코치 발화 — 논점별 문단 분리 (카드·채팅 공용) */
export function splitCoachParagraphs(text: string): string[] {
  const normalized = text.replace(/\r\n/g, '\n').trim()
  if (normalized === '') return []

  if (normalized.includes('\n\n')) {
    return normalized
      .split(/\n{2,}/)
      .map((p) => p.trim())
      .filter(Boolean)
  }

  const withBreaks = normalized.replace(
    /([.!?…]["'”’)]*)\s+(?=(?:다만|하지만|그래서|그런데|한편|반면|즉|또|실제로)\b)/gu,
    '$1\n\n'
  )

  return withBreaks
    .split(/\n{2,}/)
    .map((p) => p.trim())
    .filter(Boolean)
}

/** 서술형 카드 — 입력 위 한 줄 고정 라벨 (전체 질문은 대화 기록에) */
export function narrativePromptOneLine(text: string, maxLen = 46): string {
  const plain = stripCoachBoldMarkers(text)
    .replace(/\{\{snippet[\s\S]*?\{\{\/snippet\}\}/g, '')
    .replace(/\s+/g, ' ')
    .trim()
  if (plain === '') return ''
  const firstLine = plain.split('\n').map((l) => l.trim()).find(Boolean) ?? plain
  if (firstLine.length <= maxLen) return firstLine
  return `${firstLine.slice(0, maxLen - 1)}…`
}
