export const CARD_STRUCTURE_SLOTS = ['배경', '입장', '갈등', '반론', '결론'] as const

/** 축 통과 순간 — 행동 격려만 (채팅형 탐구 바와 동일 톤) */
const AXIS_PASS_NUDGES = ['한 갈래 따졌어!', '한 갈래 더 따졌어!', '갈등 정리됐어!'] as const

/** 구조 블록 통과 — 행동만, 평가·숫자 없음 */
const STRUCTURE_PHASE_NUDGES: Record<number, string> = {
  0: '배경 잡았어!',
  1: '입장 정했어!',
  2: '갈등 따졌어!',
  3: '다른 시각 들었어!',
  4: '한 줄 정리했어!',
}

export function resolveCardStructureBarState(
  phase: string,
  guideAxisIndex: number
): { completed: number; current: number } {
  void guideAxisIndex
  switch (phase) {
    case 'stance':
      return { completed: 0, current: 0 }
    case 'evidence':
      return { completed: 1, current: 1 }
    case 'reasoning':
      return { completed: 1, current: 2 }
    case 'guide_axis':
      return { completed: 2, current: 2 }
    case 'guide_conclusion':
      return { completed: 3, current: 4 }
    case 'hammer':
      return { completed: 4, current: 3 }
    case 'reflection':
      return { completed: 5, current: -1 }
    default:
      return { completed: 0, current: 0 }
  }
}

export function structureNudgeForAxisPass(filledAxisIndex: number): string {
  return AXIS_PASS_NUDGES[Math.min(filledAxisIndex, AXIS_PASS_NUDGES.length - 1)]
}

export function structureNudgeForPhaseSlot(slotIndex: number): string {
  return STRUCTURE_PHASE_NUDGES[slotIndex] ?? '한 단계 더!'
}

export function completedSlotOnPhaseExit(prevPhase: string): number | null {
  switch (prevPhase) {
    case 'stance':
      return 0
    case 'evidence':
      return 1
    case 'guide_axis':
      return 2
    case 'guide_conclusion':
      return 4
    case 'hammer':
      return 3
    default:
      return null
  }
}
