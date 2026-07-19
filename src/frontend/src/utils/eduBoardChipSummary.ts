const LAYER_PREFIX_RE = /^[①②③④⑤⑥]\s*\S+:\s*/
const SENTENCE_SPLIT_RE = /(?<=[。!?？.])\s+/
const CLAUSE_SPLIT_RE = /[,，·—]|(?:\s+(?:되|지만|라도)\s+)/
const QUOTE_RE = /[''""]([^''""]+)[''""]/g
const GENERIC_TAIL_RE = /(?:한다|본다|있다|없다|인가|할까|될까|보기|골라)$/

export type ChipSummaryGrade = 'good' | 'weak' | 'fallback'

export function stripBoardChipPrefix(text: string): string {
  return text.replace(LAYER_PREFIX_RE, '').trim()
}

function extractQuotes(text: string): string[] {
  const out: string[] = []
  for (const match of text.matchAll(QUOTE_RE)) {
    const value = match[1]?.trim() ?? ''
    if (value) out.push(value)
  }
  return out
}

function tailWords(text: string, count: number): string {
  const words = text.trim().split(/\s+/).filter(Boolean)
  if (words.length === 0) return ''
  if (words.length <= count) return words.join(' ')
  return words.slice(-count).join(' ')
}

function capChipText(text: string, maxLen = 10): string {
  const trimmed = text.trim()
  if (trimmed.length <= maxLen) return trimmed
  return `${trimmed.slice(0, maxLen - 1)}…`
}

function lastSentence(body: string): string {
  const parts = body
    .split(SENTENCE_SPLIT_RE)
    .map(part => part.trim())
    .filter(Boolean)
  return parts.length > 0 ? parts[parts.length - 1] : body
}

function lastClause(sentence: string): string {
  const parts = sentence
    .split(CLAUSE_SPLIT_RE)
    .map(part => part.trim())
    .filter(Boolean)
  return parts.length > 0 ? parts[parts.length - 1] : sentence
}

/** 생각판 slot.text → 모바일 스트립 칩 요약 (max 10자) */
export function summarizeBoardChipText(text: string, labelFallback: string): string {
  const body = stripBoardChipPrefix(text.trim())
  if (!body) return labelFallback

  const focus = lastSentence(body)
  const quotesInBody = extractQuotes(body)
  const quotesInFocus = extractQuotes(focus)

  if (quotesInFocus.length === 0 && quotesInBody.length === 1) {
    const quoted = capChipText(quotesInBody[0])
    if (quoted.replace(/…$/, '').length >= 4) return quoted
  }

  const clause = lastClause(focus)
  let candidate = tailWords(clause, 3)
  if (candidate.replace(/…$/, '').length < 4) {
    candidate = tailWords(clause, 2)
  }
  if (candidate.replace(/…$/, '').length < 4) {
    candidate = clause
  }

  candidate = capChipText(candidate)
  if (candidate.replace(/…$/, '').length < 4) {
    return labelFallback
  }
  return candidate
}

/** 전수 검증용 — 요약 품질 등급 (UI 비표시) */
export function gradeBoardChipSummary(_original: string, summary: string, label: string): ChipSummaryGrade {
  if (summary === label) return 'fallback'
  if (summary.length < 4) return 'fallback'
  if (GENERIC_TAIL_RE.test(summary.replace(/…$/, ''))) return 'weak'
  if (/[?？]$/.test(summary.replace(/…$/, ''))) return 'weak'
  return 'good'
}
