/**
 * 편집 툴에서 사용하는 태그만 허용 (볼드, 하이라이트)
 * 허용: <b>, <strong>, <mark>, <span style="background|font-weight">, <br />
 * 브라우저가 생성하는 다양한 형식 지원 (style 속성 포함 등)
 */
const ALLOWED_PATTERNS = [
  /<b(\s[^>]*)?\/?>/gi,
  /<\/b>/gi,
  /<strong(\s[^>]*)?\/?>/gi,
  /<\/strong>/gi,
  /<mark(\s[^>]*)?\/?>/gi,
  /<\/mark>/gi,
  /<span\s+style="[^"]*background[^"]*"[^>]*\/?>/gi,
  /<span\s+style="[^"]*font-weight[^"]*"[^>]*\/?>/gi,
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
 * 텍스트를 HTML로 렌더링할 때 줄바꿈을 <br />로 변환한 뒤 sanitize
 */
export function formatContentHtml(text: string | null | undefined): string {
  if (text == null || text === '') return ''
  return sanitizeHtml(String(text).replace(/\n/g, '<br/>'))
}
