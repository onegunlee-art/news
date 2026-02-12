/**
 * URL의 마지막 경로(슬러그)를 추출하여 영어 제목 형태로 변환
 * e.g. "trump-signs-executive-order-2024" → "Trump Signs Executive Order 2024"
 */
export function extractTitleFromUrl(url: string | null | undefined): string | null {
  if (url == null || typeof url !== 'string') return null
  const trimmed = url.trim()
  if (!trimmed) return null
  try {
    const parsed = new URL(trimmed.startsWith('http') ? trimmed : 'https://' + trimmed)
    const path = parsed.pathname || ''
    const segments = path.split('/').filter(Boolean)
    if (segments.length === 0) return null
    let slug = segments[segments.length - 1]
    // .html, .htm, .php 등 제거
    slug = slug.replace(/\.(html?|php|aspx?)$/i, '')
    if (!slug) return null
    // 하이픈을 공백으로, 단어 첫 글자 대문자
    const words = slug.split('-').filter(Boolean)
    if (words.length === 0) return null
    const title = words
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
      .join(' ')
    return title || null
  } catch {
    return null
  }
}
