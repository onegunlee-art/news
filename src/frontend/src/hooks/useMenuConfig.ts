import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { siteSettingsApi } from '../services/api'

export interface MenuTab {
  key: string
  label: string
}

const DEFAULT_TABS: MenuTab[] = [
  { key: 'latest', label: '최신' },
  { key: 'diplomacy', label: '외교' },
  { key: 'economy', label: '경제' },
  { key: 'special', label: '특집' },
  { key: 'popular', label: '인기' },
]

const DEFAULT_SUBCATEGORIES: Record<string, string> = {
  politics_diplomacy: 'Politics/Diplomacy',
  economy_industry: 'Economy/Industry',
  society: 'Society',
  security_conflict: 'Security/Conflict',
  environment: 'Environment',
  science_technology: 'Science/Technology',
  culture: 'Culture',
  health_development: 'Health/Development',
}

function parseMenuTabs(raw: string | undefined): MenuTab[] {
  if (!raw || !raw.trim()) return DEFAULT_TABS
  try {
    const arr = JSON.parse(raw)
    if (Array.isArray(arr) && arr.length >= 5) {
      return arr.map((t: { key?: string; label?: string }) => ({
        key: String(t?.key ?? ''),
        label: String(t?.label ?? ''),
      }))
    }
  } catch {
    /* ignore */
  }
  return DEFAULT_TABS
}

function parseSubcategories(raw: string | undefined): Record<string, string> {
  if (!raw || !raw.trim()) return DEFAULT_SUBCATEGORIES
  try {
    const obj = JSON.parse(raw)
    if (obj && typeof obj === 'object') {
      return { ...DEFAULT_SUBCATEGORIES, ...obj }
    }
  } catch {
    /* ignore */
  }
  return DEFAULT_SUBCATEGORIES
}

/** 탭 label → API category (key). latest/popular → null */
export function tabLabelToCategory(tabs: MenuTab[]): Record<string, string | null> {
  const out: Record<string, string | null> = {}
  for (const t of tabs) {
    out[t.label] = t.key === 'latest' || t.key === 'popular' ? null : t.key
  }
  return out
}

/** API category key → 탭 표시 라벨 (상위 카테고리용) */
export function tabsToParentKeyToLabel(tabs: MenuTab[]): Record<string, string> {
  const out: Record<string, string> = {}
  for (const t of tabs) {
    out[t.key] = t.label
  }
  out.diplomacy = out.diplomacy ?? '외교'
  out.economy = out.economy ?? '경제'
  out.special = out.special ?? '특집'
  out.latest = out.latest ?? '최신'
  out.popular = out.popular ?? '인기'
  return out
}

/** 탭 표시 라벨 → API from_tab 값 */
export function tabsToFromTabToApi(tabs: MenuTab[]): Record<string, string> {
  const out: Record<string, string> = {}
  for (const t of tabs) {
    out[t.label] = t.key
  }
  return out
}

export interface MenuConfig {
  tabs: MenuTab[]
  tabLabels: string[]
  tabToCategory: Record<string, string | null>
  subCategoryToLabel: Record<string, string>
  parentKeyToLabel: Record<string, string>
  fromTabToApi: Record<string, string>
  specialBadgeText: string
}

export function useMenuConfig(): MenuConfig {
  const { data } = useQuery({
    queryKey: ['site', 'settings'],
    queryFn: async () => {
      const res = await siteSettingsApi.getSite()
      return res.data?.data
    },
    staleTime: 1000 * 60 * 5,
  })

  const menuTabs = data?.menu_tabs
  const menuSubcategories = data?.menu_subcategories
  const badgeText = data?.special_badge_text

  return useMemo(() => {
    const tabs = parseMenuTabs(menuTabs)
    const subCategoryToLabel = parseSubcategories(menuSubcategories)
    const tabLabels = tabs.map((t) => t.label)
    const tabToCategory = tabLabelToCategory(tabs)
    const parentKeyToLabel = tabsToParentKeyToLabel(tabs)
    const fromTabToApi = tabsToFromTabToApi(tabs)
    const specialBadgeText = badgeText || 'MSC'

    return {
      tabs,
      tabLabels,
      tabToCategory,
      subCategoryToLabel,
      parentKeyToLabel,
      fromTabToApi,
      specialBadgeText,
    }
  }, [menuTabs, menuSubcategories, badgeText])
}
