/**
 * 편집 툴에서 사용하는 태그만 허용
 * 볼드, 하이라이트, 리스트, 정렬, 글자크기
 */
const ALLOWED_PATTERNS = [
  /<b(\s[^>]*)?\/?>/gi,
  /<\/b>/gi,
  /<strong(\s[^>]*)?\/?>/gi,
  /<\/strong>/gi,
  /<mark(\s[^>]*)?\/?>/gi,
  /<\/mark>/gi,
  /<span\s+style="[^"]*background[^"]*"[^>]*\/?>/gi,
  /<span\s+style='[^']*background[^']*'[^>]*\/?>/gi,
  /<span\s+style="[^"]*font-weight[^"]*"[^>]*\/?>/gi,
  /<span\s+style='[^']*font-weight[^']*'[^>]*\/?>/gi,
  /<span\s+style="[^"]*font-size[^"]*"[^>]*\/?>/gi,
  /<span\s+style='[^']*font-size[^']*'[^>]*\/?>/gi,
  /<\/span>/gi,
  /<br\s*\/?>/gi,
  /<ul(\s[^>]*)?\/?>/gi,
  /<\/ul>/gi,
  /<ol(\s[^>]*)?\/?>/gi,
  /<\/ol>/gi,
  /<li(\s[^>]*)?\/?>/gi,
  /<\/li>/gi,
  /<div\s+style="[^"]*text-align:\s*(?:left|center|right|justify)[^"]*"[^>]*\/?>/gi,
  /<font\s+size="[^"]*"[^>]*\/?>/gi,
  /<\/font>/gi,
]

export function sanitizeHtml(html: string): string {
  if (!html || typeof html !== 'string') return ''
  const stored: string[] = []
  let s = html
  for (const re of ALLOWED_PATTERNS) {
    s = s.replace(re, (match) => {
      stored.push(match)
      return `\x00${stored.length - 1}\x00`
    })
  }
  s = s.replace(/</g, '&lt;').replace(/>/g, '&gt;')
  s = s.replace(/\x00(\d+)\x00/g, (_, i) => stored[Number(i)] ?? '')
  return s
}

/**
 * HTML 엔티티 복원 (DB에 이스케이프 저장된 태그 정상화)
 */
function unescapeHtmlEntities(s: string): string {
  return s
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(parseInt(n, 10)))
    .replace(/&#x([0-9a-f]+);/gi, (_, n) => String.fromCharCode(parseInt(n, 16)))
}

/**
 * contenteditable이 생성한 블록 태그(div, p)를 br로 정규화
 * text-align div, ul/ol/li는 보존
 */
function normalizeBlockTags(s: string): string {
  const alignBlocks: string[] = []
  s = s.replace(
    /<div\s+style="[^"]*text-align:\s*(left|center|right|justify)[^"]*"[^>]*>((?:(?!<div|<\/div).)*)<\/div>/gi,
    (_, align, body) => {
      const idx = alignBlocks.length
      alignBlocks.push(`<div style="text-align:${align}">${body}</div>`)
      return `\x01A${idx}\x01`
    }
  )
  s = s.replace(/<\/div>/gi, '<br/>')
  s = s.replace(/<div[^>]*>/gi, '')
  s = s.replace(/<\/p>/gi, '<br/>')
  s = s.replace(/<p[^>]*>/gi, '')
  s = s.replace(/(<br\s*\/?>){3,}/gi, '<br/><br/>')
  s = s.replace(/\x01A(\d+)\x01/g, (_, i) => alignBlocks[Number(i)] ?? '')
  return s
}

/**
 * 텍스트를 HTML로 렌더링할 때 사용
 */
export function formatContentHtml(text: string | null | undefined): string {
  if (text == null || text === '') return ''
  let s = String(text).replace(/\n/g, '<br/>')
  s = unescapeHtmlEntities(s)
  s = normalizeBlockTags(s)
  return sanitizeHtml(s)
}

/**
 * RichTextEditor HTML → DB 저장 전 정규화
 */
export function normalizeEditorHtml(html: string): string {
  if (!html || typeof html !== 'string') return ''
  let s = html
  s = normalizeBlockTags(s)
  s = s.replace(/^(<br\s*\/?>)+/gi, '')
  s = s.replace(/(<br\s*\/?>)+$/gi, '')
  return s.trim()
}
