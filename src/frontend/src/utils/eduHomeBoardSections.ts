import type { EduQuestListItem } from '../services/eduApi'

export type EduHomeBoardSections = {
  recommended: EduQuestListItem[]
  newQuests: EduQuestListItem[]
  allRest: EduQuestListItem[]
}

const RECOMMENDED_COUNT = 3
const NEW_SECTION_MAX = 7

/** approved 목록(최신순) → 추천 3 / 새글 / 나머지 */
export function partitionHomeBoard(quests: EduQuestListItem[]): EduHomeBoardSections {
  const recommended = quests.slice(0, RECOMMENDED_COUNT)
  const recIds = new Set(recommended.map((q) => q.quest_id))
  const rest = quests.filter((q) => !recIds.has(q.quest_id))
  const newQuests = rest.slice(0, NEW_SECTION_MAX)
  const newIds = new Set(newQuests.map((q) => q.quest_id))
  const allRest = rest.filter((q) => !newIds.has(q.quest_id))
  return { recommended, newQuests, allRest }
}
