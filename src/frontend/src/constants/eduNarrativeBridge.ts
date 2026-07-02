/** 630 narrative_bridge_v1 — 코치 UI 라우팅 헬퍼 */
import type { EduQuest } from '../services/eduApi'

export const EDU_NARRATIVE_BRIDGE_QUEST_CODE = 'Q-AUTO-NUKE-630'
export const EDU_NARRATIVE_BRIDGE_MODE = 'narrative_bridge_v1'

export function questUsesNarrativeBridge(quest: Pick<EduQuest, 'quest_code' | 'coach_mode'> | null | undefined): boolean {
  if (!quest) return false
  return quest.quest_code === EDU_NARRATIVE_BRIDGE_QUEST_CODE && quest.coach_mode === EDU_NARRATIVE_BRIDGE_MODE
}

export const NARRATIVE_BRIDGE_STEP_COUNT = 6

export function narrativeBridgeStepLabel(step: number): string {
  return `STEP ${Math.min(NARRATIVE_BRIDGE_STEP_COUNT - 1, Math.max(0, step))}`
}
