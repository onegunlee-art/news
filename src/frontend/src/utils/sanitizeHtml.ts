/**
 * 편집 툴에서 사용하는 태그만 허용
 * 볼드, 하이라이트, 리스트, 정렬, 글자크기, 표
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
  /<span\s+style="[^"]*text-decoration[^"]*"[^>]*\/?>/gi,
  /<span\s+style='[^']*text-decoration[^']*'[^>]*\/?>/gi,
  /<\/span>/gi,
  /<i(\s[^>]*)?\/?>/gi,
  /<\/i>/gi,
  /<em(\s[^>]*)?\/?>/gi,
  /<\/em>/gi,
  /<u(\s[^>]*)?\/?>/gi,
  /<\/u>/gi,
  /<s(\s[^>]*)?\/?>/gi,
  /<\/s>/gi,
  /<strike(\s[^>]*)?\/?>/gi,
  /<\/strike>/gi,
  /<img\s+[^>]*\/?>/gi,
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
  // 표(table) 태그
  /<table(\s[^>]*)?>/gi,
  /<\/table>/gi,
  /<thead(\s[^>]*)?>/gi,
  /<\/thead>/gi,
  /<tbody(\s[^>]*)?>/gi,
  /<\/tbody>/gi,
  /<tr(\s[^>]*)?>/gi,
  /<\/tr>/gi,
  /<td(\s[^>]*)?>/gi,
  /<\/td>/gi,
  /<th(\s[^>]*)?>/gi,
  /<\/th>/gi,
]

/**
 * 붙여넣기용 HTML 정제 (표 포함)
 * - script, style, iframe 제거
 * - 이벤트 핸들러 속성 제거
 * - sanitizeHtml로 허용 태그만 유지
 */
export function sanitizePastedHtml(html: string): string {
  if (!html || typeof html !== 'string') return ''
  let s = html
  s = s.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
  s = s.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '')
  s = s.replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
  s = s.replace(/\s+on\w+\s*=\s*["'][^"']*["']/gi, '')
  s = s.replace(/\s+on\w+\s*=\s*[^\s>]+/gi, '')
  return sanitizeHtml(s)
}

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
  s = s.replace(/<[^>]*>/g, '')
  // eslint-disable-next-line no-control-regex
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
  s = s.replace(/<\/(?:div|p|section|article|header|footer|aside|main|nav|figure|figcaption|blockquote|h[1-6])>/gi, '<br/>')
  s = s.replace(/<(?:div|p|section|article|header|footer|aside|main|nav|figure|figcaption|blockquote|h[1-6])(?:\s[^>]*)?>/gi, '')
  s = s.replace(/(<br\s*\/?>){3,}/gi, '<br/><br/>')
  // eslint-disable-next-line no-control-regex
  s = s.replace(/\x01A(\d+)\x01/g, (_, i) => alignBlocks[Number(i)] ?? '')
  return s
}

/**
 * 텍스트를 HTML로 렌더링할 때 사용
 */
/**
 * 스마트 따옴표(curly quotes)를 Noto Sans KR과 일관된 직선 따옴표로 치환
 */
function normalizeQuotes(s: string): string {
  return s
    .replace(/[\u201C\u201D\u201E\u201F\u2033\u2036]/g, '"')
    .replace(/[\u2018\u2019\u201A\u201B\u2032\u2035]/g, "'")
    .replace(/[\u00AB\u00BB]/g, '"')
}

export function formatContentHtml(text: string | null | undefined): string {
  if (text == null || text === '') return ''
  let s = String(text).replace(/\n/g, '<br/>')
  s = unescapeHtmlEntities(s)
  s = normalizeQuotes(s)
  s = normalizeBlockTags(s)
  return sanitizeHtml(s)
}

/**
 * HTML 태그를 모두 제거하고 순수 텍스트만 반환 (목록 페이지 미리보기용)
 */
export function stripHtml(text: string | null | undefined): string {
  if (text == null || text === '') return ''
  let s = String(text)
  s = unescapeHtmlEntities(s)
  s = normalizeQuotes(s)
  s = s.replace(/<br\s*\/?>/gi, ' ')
  s = s.replace(/<\/(?:p|div|li|h[1-6])>/gi, ' ')
  s = s.replace(/<[^>]*>/g, '')
  s = s.replace(/&nbsp;/gi, ' ')
  s = s.replace(/\s{2,}/g, ' ')
  return s.trim()
}

/**
 * 편집기에 표시할 때 \n을 <br/>로 변환 (줄바꿈이 원문과 동일하게 표시되도록)
 */
export function ensureBrForEditor(html: string | null | undefined): string {
  if (html == null || html === '') return ''
  return String(html).replace(/\n/g, '<br/>')
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

/** 원문 AI 분석(content_summary)에 섞일 수 있는 메타/브랜드 문구 제거 후 표시용 텍스트 반환 */
export function stripAnalysisMetaPhrases(text: string | null | undefined): string {
  if (text == null || text === '') return ''
  let s = String(text)
  // 블록 단위 제거: RAG/참고자료 섹션 헤더 및 유사도 라인
  s = s.replace(/\n?---\s*RAG Context[^\n]*\n[\s\S]*/g, '')
  s = s.replace(/\n?##\s*과거 분석 참고자료\n?/g, '\n')
  s = s.replace(/\n?##\s*참조 프레임워크[^\n]*\n(?:아래 프레임워크를[^\n]*\n)?/g, '\n')
  s = s.replace(/\n?##\s*편집자 크리틱[^\n]*\n?/g, '\n')
  s = s.replace(/\n-\s*\[유사도\s*[\d.]+\][^\n]*/g, '')
  // 문구 단위 제거
  s = s.replace(/\s*The Gist's Critique\.?\s*:?/gi, ' ')
  s = s.replace(/\s*지스터\s*(관점의\s*)?시사점\.?\s*:?/g, ' ')
  s = s.replace(/\s*\[기사 일부만 분석됨\]\s*/g, ' ')
  s = s.replace(/\s*참고자료\s*:?/g, ' ')
  s = s.replace(/\s*참고자료를\s*제대로\s*반영하지\s*못했습니다\.?\s*/g, ' ')
  s = s.replace(/\s*참고글을\s*제대로\s*못했다\.?\s*/g, ' ')
  s = s.replace(/\s*참조 프레임워크[^\n.]*\.?/g, ' ')
  s = s.replace(/\s{2,}/g, ' ')
  return s.trim()
}
