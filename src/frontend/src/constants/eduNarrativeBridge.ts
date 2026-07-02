/** 630 narrative bridge — v1/v2 라우팅 */
import type { EduBlueprint, EduQuest, EduThoughtBoardSlot } from '../services/eduApi'

export const EDU_NARRATIVE_BRIDGE_QUEST_CODE = 'Q-AUTO-NUKE-630'
export const EDU_NARRATIVE_BRIDGE_V1 = 'narrative_bridge_v1'
export const EDU_NARRATIVE_BRIDGE_V2 = 'narrative_bridge_v2'

export type NarrativeSurface = 'v2' | 'v1' | 'default'

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
  return quest.coach_mode === EDU_NARRATIVE_BRIDGE_V2
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
): NarrativeSurface {
  if (questUsesNarrativeBridgeV2(quest)) return 'v2'
  if (questUsesNarrativeBridgeV1(quest)) return 'v1'
  return 'default'
}

/** URL·blueprint·quest 다중 신호 — 디바이스 무관 v2 라우팅 */
export function resolveQuestFlowSurface(input: {
  quest?: Pick<EduQuest, 'quest_code' | 'coach_mode'> | null
  blueprint?: Pick<EduBlueprint, 'phase' | 'narrative_version'> | null
  coachModeParam?: string | null
  questCodeParam?: string | null
}): NarrativeSurface {
  const coachParam = (input.coachModeParam ?? '').trim()
  if (coachParam === EDU_NARRATIVE_BRIDGE_V2) return 'v2'
  if (coachParam === EDU_NARRATIVE_BRIDGE_V1) return 'v1'

  const phase = (input.blueprint?.phase ?? '').trim()
  if (phase === 'narrative_bridge_v2') return 'v2'
  if (phase === 'narrative_bridge') return 'v1'

  const narrativeVersion = (input.blueprint?.narrative_version ?? '').trim()
  if (narrativeVersion === EDU_NARRATIVE_BRIDGE_V2) return 'v2'

  const fromQuest = resolveNarrativeSurface(input.quest)
  if (fromQuest !== 'default') return fromQuest

  const questCode = (input.quest?.quest_code ?? input.questCodeParam ?? '').trim()
  if (questCode === EDU_NARRATIVE_BRIDGE_QUEST_CODE) {
    const mode = (input.quest?.coach_mode ?? coachParam).trim()
    if (mode === '' || mode === EDU_NARRATIVE_BRIDGE_V2) return 'v2'
  }

  return 'default'
}

export function eduQuestFlowPath(opts: {
  questId?: string | null
  coachMode?: string | null
  questCode?: string | null
  ui?: 'cards' | 'chat'
}): string {
  const params = new URLSearchParams()
  if (opts.questId) params.set('quest_id', opts.questId)
  if (opts.coachMode) params.set('coach_mode', opts.coachMode)
  if (opts.questCode) params.set('quest_code', opts.questCode)
  if (opts.ui === 'chat') params.set('ui', 'chat')
  const qs = params.toString()
  return qs ? `/edu/quest?${qs}` : '/edu/quest'
}

export function filledThoughtBoardCount(board: EduThoughtBoardSlot[]): number {
  return board.filter(s => s.filled).length
}

/** 옛 axis_guide/card 세션이 v2 UI와 섞인 경우 */
export function narrativeV2SessionIsPolluted(
  blueprint: Pick<EduBlueprint, 'phase'> | null | undefined,
  dialogue: Array<{ role?: string; turn_id?: string | null; agent?: string | null }> | null | undefined,
  pollutedFlag?: boolean
): boolean {
  if (pollutedFlag) return true
  const turns = dialogue ?? []
  if (turns.length === 0) return false
  if ((blueprint?.phase ?? '') !== EDU_NARRATIVE_BRIDGE_V2) return true
  return turns.some(t => {
    const agent = (t.agent ?? '').trim()
    if (agent === 'narrative_v2') return false
    const tid = (t.turn_id ?? '').trim()
    if (tid === 'narrative_v2') return false
    if (tid !== '' && !/^t-\d+$/.test(tid)) return true
    return agent !== '' && agent !== 'narrative_v2'
  })
}
