import type { EduQuestListItem } from '../services/eduApi'

export type EduHomeBoardSections = {
  recommended: EduQuestListItem[]
  newQuests: EduQuestListItem[]
  allRest: EduQuestListItem[]
}

const RECOMMENDED_COUNT = 3
const NEW_SECTION_MAX = 7

/** 홈 보드 — approved만 (draft·선언문 2차 안전망) */
export function filterApprovedQuestsForHome(quests: EduQuestListItem[]): EduQuestListItem[] {
  return quests.filter((q) => !q.status || q.status === 'approved')
}

/** approved 목록(최신순) → 추천 3 / 새글 / 나머지 */
export function partitionHomeBoard(quests: EduQuestListItem[]): EduHomeBoardSections {
  const approved = filterApprovedQuestsForHome(quests)
  const recommended = approved.slice(0, RECOMMENDED_COUNT)
  const recIds = new Set(recommended.map((q) => q.quest_id))
  const rest = approved.filter((q) => !recIds.has(q.quest_id))
  const newQuests = rest.slice(0, NEW_SECTION_MAX)
  const newIds = new Set(newQuests.map((q) => q.quest_id))
  const allRest = rest.filter((q) => !newIds.has(q.quest_id))
  return { recommended, newQuests, allRest }
}
