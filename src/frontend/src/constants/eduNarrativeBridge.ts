/** 630 narrative bridge — v1/v2 라우팅 */
import type { EduQuest, EduThoughtBoardSlot } from '../services/eduApi'

export const EDU_NARRATIVE_BRIDGE_QUEST_CODE = 'Q-AUTO-NUKE-630'
export const EDU_NARRATIVE_BRIDGE_V1 = 'narrative_bridge_v1'
export const EDU_NARRATIVE_BRIDGE_V2 = 'narrative_bridge_v2'

export function questUsesNarrativeBridgeV1(
  quest: Pick<EduQuest, 'quest_code' | 'coach_mode'> | null | undefined
): boolean {
  if (!quest) return false
  return quest.quest_code === EDU_NARRATIVE_BRIDGE_QUEST_CODE && quest.coach_mode === EDU_NARRATIVE_BRIDGE_V1
}

export function questUsesNarrativeBridgeV2(
  quest: Pick<EduQuest, 'quest_code' | 'coach_mode'> | null | undefined
): boolean {
  if (!quest) return false
  return quest.quest_code === EDU_NARRATIVE_BRIDGE_QUEST_CODE && quest.coach_mode === EDU_NARRATIVE_BRIDGE_V2
}

/** @deprecated use questUsesNarrativeBridgeV1 */
export function questUsesNarrativeBridge(
  quest: Pick<EduQuest, 'quest_code' | 'coach_mode'> | null | undefined
): boolean {
  return questUsesNarrativeBridgeV1(quest)
}

export const NARRATIVE_V2_LAYER_COUNT = 6
export const NARRATIVE_BRIDGE_STEP_COUNT = 5

export function resolveNarrativeSurface(
  quest: Pick<EduQuest, 'quest_code' | 'coach_mode'> | null | undefined
): 'v2' | 'v1' | 'default' {
  if (questUsesNarrativeBridgeV2(quest)) return 'v2'
  if (questUsesNarrativeBridgeV1(quest)) return 'v1'
  return 'default'
}

export function filledThoughtBoardCount(board: EduThoughtBoardSlot[]): number {
  return board.filter(s => s.filled).length
}
