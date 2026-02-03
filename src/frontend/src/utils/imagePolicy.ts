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
 * Picsum placeholder URL 반환. 기사마다 고유 시드 사용으로 중복 이미지 방지.
 */
export function getPlaceholderImageUrl(
  article: ArticleForImage,
  width: number = 400,
  height: number = 250
): string {
  const seed = getArticleImageSeed(article)
  return `https://picsum.photos/seed/${seed}/${width}/${height}`
}
