import type { EduQuestListItem } from '../services/eduApi'
import { filterApprovedQuestsForHome } from './eduHomeBoardSections'

const STORAGE_KEY = 'edu_quest_combo_v1'

type ComboStore = {
  date: string
  count: number
}

/** KST 기준 오늘 날짜 (YYYY-MM-DD) */
export function eduComboTodayKey(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Seoul',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())
  const y = parts.find((p) => p.type === 'year')?.value ?? '1970'
  const m = parts.find((p) => p.type === 'month')?.value ?? '01'
  const d = parts.find((p) => p.type === 'day')?.value ?? '01'
  return `${y}-${m}-${d}`
}

function readStore(): ComboStore {
  const today = eduComboTodayKey()
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return { date: today, count: 0 }
    const parsed = JSON.parse(raw) as ComboStore
    if (parsed.date !== today) return { date: today, count: 0 }
    return { date: today, count: Math.max(0, Number(parsed.count) || 0) }
  } catch {
    return { date: today, count: 0 }
  }
}

function writeStore(store: ComboStore): void {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(store))
}

/** 오늘 완주 1회 기록 — 게이지/XP와 무관 (표시용만) */
export function recordTodayQuestCompletion(): number {
  const store = readStore()
  store.count += 1
  writeStore(store)
  return store.count
}

export function getTodayComboCount(): number {
  return readStore().count
}

/**
 * 방금 안 푼 approved 1개 — 다른 shelf/카테고리/프레임 우선 (질림 방지)
 */
export function pickNextQuestRecommendation(
  quests: EduQuestListItem[],
  currentQuestId: string,
  options?: {
    shelf?: string | null
    category?: string | null
    questFrame?: string | null
  },
): EduQuestListItem | null {
  const approved = filterApprovedQuestsForHome(quests).filter(
    (q) => q.quest_id && q.quest_id !== currentQuestId,
  )
  if (approved.length === 0) return null

  const diverse = approved.filter((q) => {
    if (options?.shelf && q.shelf === options.shelf) return false
    if (options?.category && q.category === options.category) return false
    if (options?.questFrame && q.quest_frame === options.questFrame) return false
    return true
  })

  return (diverse.length > 0 ? diverse : approved)[0] ?? null
}

export function eduComboDisplayLabel(count: number): string | null {
  if (count >= 2) return `🔥 오늘 ${count}연속 따지기!`
  return null
}
