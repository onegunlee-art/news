/**
 * 편집 툴에서 사용하는 태그만 허용 (볼드, 하이라이트)
 * 허용: <b>, <strong>, <mark>, <span style="background|font-weight">, <br />
 * 브라우저/DB 다양한 형식 지원 (style 속성, 따옴표 종류 등)
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
 * HTML 엔티티 복원 (이스케이프된 볼드/하이라이트 태그 정상 표시)
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
 * 텍스트를 HTML로 렌더링할 때 줄바꿈을 <br />로 변환한 뒤 sanitize
 * DB에 이스케이프된 HTML(&lt;b&gt; 등)으로 저장된 경우 복원하여 정상 표시
 */
export function formatContentHtml(text: string | null | undefined): string {
  if (text == null || text === '') return ''
  let s = String(text).replace(/\n/g, '<br/>')
  s = unescapeHtmlEntities(s)
  return sanitizeHtml(s)
}
