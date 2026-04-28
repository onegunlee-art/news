/**
 * 기사 이미지 정책
 *
 * - 같은 기사는 항상 같은 이미지(일관성)
 * - 서로 다른 기사는 서로 다른 이미지(중복 없음)
 * - DB id가 있으면 id 사용, 없으면 제목+설명+날짜+카테고리+url+source 해시로 고유 시드 생성
 *   (url·source 포함으로 가자/FT 등 출처·링크가 다른 기사는 절대 같은 사진 사용 안 함)
 */

export interface ArticleForImage {
  id?: number | null
  title: string
  description?: string | null
  published_at?: string | null
  category?: string | null
  /** 기사 URL - 출처/링크가 다르면 다른 시드 보장 */
  url?: string | null
  /** 출처명 (예: FT, Reuters) - 같은 제목이라도 출처가 다르면 다른 시드 */
  source?: string | null
}

/** 문자열에서 32비트 정수 해시 (djb2) */
function hashString(str: string): number {
  let h = 5381
  for (let i = 0; i < str.length; i++) {
    h = (h * 33 + str.charCodeAt(i)) >>> 0
  }
  return h
}

/**
 * 기사별 고유 이미지 시드.
 * id 있으면 id 사용. 없으면 (제목+설명+날짜+카테고리+url+source) 해시로 1~2^31-1 시드 생성.
 */
export function getArticleImageSeed(article: ArticleForImage): number {
  if (article.id != null && article.id > 0) {
    return article.id
  }
  const combined =
    (article.title || '') +
    '|' +
    (article.description || '') +
    '|' +
    (article.published_at || '') +
    '|' +
    (article.category || '') +
    '|' +
    (article.url || '') +
    '|' +
    (article.source || '')
  const hash = hashString(combined)
  return (hash >>> 0) % 2147483647 + 1
}

/**
 * 자체 호스팅 placeholder. 시드 기반 HSL 그라데이션 SVG 를 data URL 로 반환.
 * - 외부(picsum) 의존 제거: RTT 0, 0KB 추가 다운로드, 디코딩 비용 무시할 만함.
 * - 기사마다 고유 색 (id/hash 시드 → HSL).
 * - format 파라미터는 시그니처 호환을 위해 받지만 SVG 로 통일하여 무시.
 */
export function getPlaceholderImageUrl(
  article: ArticleForImage,
  width: number = 400,
  height: number = 250,
  _format: 'webp' | 'jpg' = 'webp'
): string {
  const seed = getArticleImageSeed(article)
  const hue = seed % 360
  const sat = 25 + (seed % 30)
  const light = 78 + (seed % 12)
  const hue2 = (hue + 30) % 360
  const sat2 = Math.min(60, sat + 8)
  const light2 = Math.max(60, light - 8)
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid slice">` +
    `<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">` +
    `<stop offset="0" stop-color="hsl(${hue},${sat}%,${light}%)"/>` +
    `<stop offset="1" stop-color="hsl(${hue2},${sat2}%,${light2}%)"/>` +
    `</linearGradient></defs>` +
    `<rect width="${width}" height="${height}" fill="url(#g)"/>` +
    `</svg>`
  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`
}
