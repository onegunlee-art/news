export type CoachMessageSegment =
  | { type: 'text'; value: string }
  | { type: 'snippet'; value: string; display: string }

const SNIPPET_RE = /\{\{snippet\|(\w+)\}\}\n([\s\S]*?)\n\{\{\/snippet\}\}/g

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

  if (segments.length === 0) {
    segments.push({ type: 'text', value: content })
  }

  return segments
}
