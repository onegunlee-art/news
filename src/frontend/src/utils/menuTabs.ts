export interface MenuTab {
  key: string
  label: string
}

const LEGACY_POPULAR_LABELS = new Set(['인기', 'TOP20', 'top20', 'Top20', 'Top 20', 'top 20', 'TOP 20'])

function isLegacyPopularLabel(label: string): boolean {
  const trimmed = label.trim()
  if (LEGACY_POPULAR_LABELS.has(trimmed)) return true
  return /^top\s*20$/i.test(trimmed)
}

/** DB/설정에 남아 있는 popular·archive 탭을 과거 특집으로 정규화 */
export function normalizeMenuTabs(tabs: MenuTab[]): MenuTab[] {
  return tabs.map((tab) => {
    if (tab.key !== 'popular' && tab.key !== 'archive') return tab
    if (tab.key === 'archive' && !isLegacyPopularLabel(tab.label)) {
      return tab
    }
    return { key: 'archive', label: '과거 특집' }
  })
}
