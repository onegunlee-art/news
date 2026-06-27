import type { EduChatResponse } from '../services/eduApi'

/** chat 응답·blueprint 기준 compose API 호출 필요 여부 (코치 FSM 무관) */
export function shouldTriggerEduCompose(
  res: Pick<EduChatResponse, 'should_compose' | 'phase' | 'blueprint'>
): boolean {
  return Boolean(
    res.should_compose ||
      res.blueprint?.ready_for_compose ||
      (res.phase === 'compose' && res.blueprint?.reflection_confirmed)
  )
}
