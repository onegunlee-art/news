/**
 * 기사 상세 경로. tabLabel은 메뉴 탭 표시 문자열(예: 최신, 외교) — URL에 유지해 새로고침 후에도 prev/next 맥락 복구.
 */
export function newsDetailPath(newsId: number, tabLabel?: string | null): string {
  const base = `/news/${newsId}`
  if (!tabLabel) return base
  return `${base}?tab=${encodeURIComponent(tabLabel)}`
}
