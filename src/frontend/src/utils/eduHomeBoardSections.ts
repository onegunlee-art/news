import type { EduQuestListItem } from '../services/eduApi'

export type EduHomeBoardSections = {
  myLevelRecommended: EduQuestListItem[]
  byLevel: Record<1 | 2 | 3 | 4 | 5, EduQuestListItem[]>
  newQuests: EduQuestListItem[]
}

const MY_LEVEL_MAX = 5
const NEW_SECTION_MAX = 7
const NEW_DAYS_MS = 7 * 24 * 60 * 60 * 1000

/** 홈 보드 — approved만 (draft·선언문 2차 안전망) */
export function filterApprovedQuestsForHome(quests: EduQuestListItem[]): EduQuestListItem[] {
  return quests.filter((q) => !q.status || q.status === 'approved')
}

function isRecentQuest(q: EduQuestListItem, nowMs: number): boolean {
  if (!q.live_at) return false
  const t = new Date(q.live_at).getTime()
  return !Number.isNaN(t) && nowMs - t <= NEW_DAYS_MS
}

/** coach_level 매칭 추천 + 레벨별 + 최근 7일 새 글 */
export function partitionHomeBoard(
  quests: EduQuestListItem[],
  coachLevel: number,
): EduHomeBoardSections {
  const approved = filterApprovedQuestsForHome(quests)
  const level = Math.min(5, Math.max(1, coachLevel)) as 1 | 2 | 3 | 4 | 5
  const nowMs = Date.now()

  const myLevelRecommended = approved
    .filter((q) => q.recommended_for_you || q.difficulty_level === level)
    .slice(0, MY_LEVEL_MAX)

  const usedIds = new Set(myLevelRecommended.map((q) => q.quest_id))
  const rest = approved.filter((q) => !usedIds.has(q.quest_id))

  const newQuests = rest.filter((q) => isRecentQuest(q, nowMs)).slice(0, NEW_SECTION_MAX)
  newQuests.forEach((q) => usedIds.add(q.quest_id))

  const byLevel: Record<1 | 2 | 3 | 4 | 5, EduQuestListItem[]> = {
    1: [],
    2: [],
    3: [],
    4: [],
    5: [],
  }

  for (const q of rest) {
    if (usedIds.has(q.quest_id)) continue
    const dl = q.difficulty_level
    if (dl != null && dl >= 1 && dl <= 5) {
      byLevel[dl as 1 | 2 | 3 | 4 | 5].push(q)
    } else {
      byLevel[3].push(q)
    }
  }

  return { myLevelRecommended, byLevel, newQuests }
}

/** @deprecated slice-based partition — tests only */
export function partitionHomeBoardLegacy(quests: EduQuestListItem[]): {
  recommended: EduQuestListItem[]
  newQuests: EduQuestListItem[]
  allRest: EduQuestListItem[]
} {
  const approved = filterApprovedQuestsForHome(quests)
  const recommended = approved.slice(0, 3)
  const recIds = new Set(recommended.map((q) => q.quest_id))
  const rest = approved.filter((q) => !recIds.has(q.quest_id))
  const newQuests = rest.slice(0, NEW_SECTION_MAX)
  const newIds = new Set(newQuests.map((q) => q.quest_id))
  const allRest = rest.filter((q) => !newIds.has(q.quest_id))
  return { recommended, newQuests, allRest }
}

export const EDU_HOME_LEVEL_SECTION_LABELS: Record<
  number,
  { title: string; subtitle?: string }
> = {
  1: { title: 'L1 · 관찰자', subtitle: '시작하기 좋은 글 · 친숙한 주제' },
  2: { title: 'L2 · 질문자', subtitle: '익숙한 이슈부터' },
  3: { title: 'L3 · 논객', subtitle: '여러 관점 연결' },
  4: { title: 'L4 · 분석가', subtitle: '낯선 맥락·전문 주제' },
  5: { title: 'L5 · 칼럼니스트', subtitle: '깊은 추론·복합 구조' },
}
