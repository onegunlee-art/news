export type CoachMessageSegment =
  | { type: 'text'; value: string }
  | { type: 'snippet'; value: string; display: string }

const SNIPPET_RE = /\{\{snippet\|(\w+)\}\}\s*([\s\S]*?)\s*\{\{\/snippet\}\}/g

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
