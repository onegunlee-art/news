export interface MenuTab {
  key: string
  label: string
}

const LEGACY_POPULAR_LABELS = new Set(['인기', 'TOP20', 'top20', 'Top20', 'Top 20', 'top 20'])

/** DB/설정에 남아 있는 popular(인기) 탭을 archive(과거 특집)로 정규화 */
export function normalizeMenuTabs(tabs: MenuTab[]): MenuTab[] {
  return tabs.map((tab) => {
    if (tab.key !== 'popular') return tab
    const trimmed = tab.label.trim()
    const label = LEGACY_POPULAR_LABELS.has(trimmed) ? '과거 특집' : tab.label
    return { key: 'archive', label }
  })
}
