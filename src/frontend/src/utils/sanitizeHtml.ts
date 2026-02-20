/**
 * 편집 툴에서 사용하는 태그만 허용 (볼드, 하이라이트)
 * 허용: <b>, <strong>, <mark>, <span style="background|font-weight">, <br />
 * contenteditable / 브라우저 / DB 다양한 형식 지원
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
  /<\/span>/gi,
  /<br\s*\/?>/gi,
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
 * HTML 엔티티 복원 (DB에 이스케이프 저장된 볼드/하이라이트 태그 정상화)
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
 */
function normalizeBlockTags(s: string): string {
  s = s.replace(/<\/div>/gi, '<br/>')
  s = s.replace(/<div[^>]*>/gi, '')
  s = s.replace(/<\/p>/gi, '<br/>')
  s = s.replace(/<p[^>]*>/gi, '')
  s = s.replace(/(<br\s*\/?>){3,}/gi, '<br/><br/>')
  return s
}

/**
 * 텍스트를 HTML로 렌더링할 때 사용
 * 순서: 줄바꿈→br, 엔티티 복원, 블록태그 정규화, sanitize
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
 * div/p → br, 연속 br 정리, 앞뒤 br 제거
 */
export function normalizeEditorHtml(html: string): string {
  if (!html || typeof html !== 'string') return ''
  let s = html
  s = normalizeBlockTags(s)
  s = s.replace(/^(<br\s*\/?>)+/gi, '')
  s = s.replace(/(<br\s*\/?>)+$/gi, '')
  return s.trim()
}
