export type CoachHighlightSegment =
  | { type: 'plain'; value: string }
  | { type: 'highlight'; value: string }

/** @deprecated use CoachHighlightSegment */
export type CoachBoldSegment = CoachHighlightSegment

const SNIPPET_RE = /\{\{snippet\|(\w+)\}\}\s*([\s\S]*?)\s*\{\{\/snippet\}\}/g
const BOLD_RE = /\*\*([^*]+)\*\*/g
const QUOTE_RE = /"([^"]+)"/g
const QUOTE_MAX_LEN = 24

/** 스마트 따옴표 → ASCII (AdminPage와 동일) */
export function normalizeCoachQuotes(text: string): string {
  return text
    .replace(/[\u201C\u201D\u201E\u201F\u2033\u2036]/g, '"')
    .replace(/[\u2018\u2019\u201A\u201B\u2032\u2035]/g, "'")
}

type HighlightMarker = { index: number; length: number; value: string }

function findHighlightMarkers(text: string): HighlightMarker[] {
  const markers: HighlightMarker[] = []
  let match: RegExpExecArray | null

  BOLD_RE.lastIndex = 0
  while ((match = BOLD_RE.exec(text)) !== null) {
    markers.push({
      index: match.index,
      length: match[0].length,
      value: match[1] ?? '',
    })
  }

  QUOTE_RE.lastIndex = 0
  while ((match = QUOTE_RE.exec(text)) !== null) {
    const value = match[1] ?? ''
    if (value.length <= QUOTE_MAX_LEN) {
      markers.push({
        index: match.index,
        length: match[0].length,
        value,
      })
    }
  }

  markers.sort((a, b) => a.index - b.index)

  const filtered: HighlightMarker[] = []
  let end = 0
  for (const marker of markers) {
    if (marker.index >= end) {
      filtered.push(marker)
      end = marker.index + marker.length
    }
  }
  return filtered
}

/** 코치 **강조** · "핵심어" → 렌더용 세그먼트 (기존 마커만 변환) */
export function parseCoachHighlightSegments(text: string): CoachHighlightSegment[] {
  const normalized = normalizeCoachQuotes(text)
  const markers = findHighlightMarkers(normalized)
  const segments: CoachHighlightSegment[] = []
  let lastIndex = 0

  for (const marker of markers) {
    if (marker.index > lastIndex) {
      segments.push({ type: 'plain', value: normalized.slice(lastIndex, marker.index) })
    }
    segments.push({ type: 'highlight', value: marker.value })
    lastIndex = marker.index + marker.length
  }

  const tail = normalized.slice(lastIndex)
  if (tail !== '') {
    segments.push({ type: 'plain', value: tail })
  }

  if (segments.length === 0) {
    segments.push({ type: 'plain', value: normalized })
  }

  return segments
}

/** @deprecated use parseCoachHighlightSegments */
export function parseCoachBoldSegments(text: string): CoachBoldSegment[] {
  return parseCoachHighlightSegments(text)
}

/** 미리보기·한 줄 라벨 — ** · " 제거 */
export function stripCoachHighlightMarkers(text: string): string {
  const normalized = normalizeCoachQuotes(text)
  return normalized
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/"([^"]+)"/g, '$1')
}

/** @deprecated use stripCoachHighlightMarkers */
export function stripCoachBoldMarkers(text: string): string {
  return stripCoachHighlightMarkers(text)
}

/** 타입라이터 중 미완성 ** · " 마커 숨김 */
export function stripIncompleteCoachMarkers(text: string): string {
  let result = text

  const openBold = result.lastIndexOf('**')
  if (openBold !== -1) {
    const tail = result.slice(openBold + 2)
    if (!tail.includes('**')) {
      result = result.slice(0, openBold)
    }
  }

  const normalized = normalizeCoachQuotes(result)
  const quoteCount = (normalized.match(/"/g) ?? []).length
  if (quoteCount % 2 === 1) {
    const openQuote = normalized.lastIndexOf('"')
    if (openQuote !== -1) {
      result = result.slice(0, openQuote)
    }
  }

  return result
}

/** @deprecated use stripIncompleteCoachMarkers */
export function stripIncompleteCoachBold(text: string): string {
  return stripIncompleteCoachMarkers(text)
}

export function coachMessageHasSnippet(content: string): boolean {
  return content.includes('{{snippet|')
}

export type CoachMessageSegment =
  | { type: 'text'; value: string }
  | { type: 'snippet'; value: string; display: string }

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
  const plain = stripCoachHighlightMarkers(text)
    .replace(/\{\{snippet[\s\S]*?\{\{\/snippet\}\}/g, '')
    .replace(/\s+/g, ' ')
    .trim()
  if (plain === '') return ''
  const firstLine = plain.split('\n').map((l) => l.trim()).find(Boolean) ?? plain
  if (firstLine.length <= maxLen) return firstLine
  return `${firstLine.slice(0, maxLen - 1)}…`
}
