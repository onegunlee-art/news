import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

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

type Props = {
  phase: string
  guideAxisIndex: number
  pulse: boolean
  pulseSlot: number | null
  nudgeText: string
  compact?: boolean
}

/** 카드형 5블록 — 배경→입장→갈등→반론→결론 (채팅형 1+3 애니메이션 동일 클래스) */
export default function CardStructureBar({
  phase,
  guideAxisIndex,
  pulse,
  pulseSlot,
  nudgeText,
  compact = false,
}: Props) {
  const { completed, current } = resolveCardStructureBarState(phase, guideAxisIndex)

  return (
    <div
      className={`shrink-0 border-b px-4 ${compact ? 'py-1' : 'py-2'}`}
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
    >
      <div className={`${compact ? '' : ''} max-w-2xl mx-auto w-full`}>
        <div className="relative flex gap-1.5" role="list" aria-label="글 구조 진행">
          {CARD_STRUCTURE_SLOTS.map((label, i) => {
            const isDone = i < completed
            const isCurrent = !isDone && i === current && current >= 0
            const isPending = !isDone && !isCurrent
            const justFilled = pulse && pulseSlot === i

            const slotStyle: { borderColor: string; backgroundColor: string } = isDone
              ? { borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }
              : isCurrent
                ? { borderColor: eduGame.primary, backgroundColor: eduGame.bg }
                : { borderColor: eduGame.border, backgroundColor: isPending ? eduGame.bg : eduGame.surface }

            let slotClass = ''
            if (isCurrent) slotClass = eduGameClasses.animAxisCurrent
            if (justFilled) slotClass = `${slotClass} ${eduGameClasses.animAxisPop}`.trim()

            return (
              <div
                key={label}
                role="listitem"
                aria-current={isCurrent ? 'step' : undefined}
                aria-label={
                  isDone
                    ? `${label} 완료`
                    : isCurrent
                      ? `${label} 진행 중`
                      : `${label} 대기`
                }
                className={`relative flex-1 min-w-0 flex flex-col items-center gap-0.5 rounded-lg border-2 text-center transition-colors duration-300 ${compact ? 'py-1 px-0.5' : 'py-2 px-0.5'} ${slotClass}`}
                style={slotStyle}
              >
                {justFilled && nudgeText && (
                  <span
                    className={`absolute -top-8 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap px-2 py-0.5 rounded-full font-bold shadow-sm ${eduGameClasses.animExploreNudge}`}
                    style={{
                      backgroundColor: eduGame.primary,
                      color: eduGame.bg,
                      fontSize: eduGame.fontSize.caption,
                    }}
                    aria-live="polite"
                  >
                    {nudgeText}
                  </span>
                )}
                <span
                  className={`font-bold leading-none ${justFilled ? eduGameClasses.animAxisCheckPop : ''}`}
                  style={{
                    color: isDone ? eduGame.primary : isCurrent ? eduGame.primary : eduGame.muted,
                    fontSize: compact ? '0.75rem' : eduGame.fontSize.label,
                  }}
                  aria-hidden
                >
                  {isDone ? '✓' : isCurrent ? '●' : '·'}
                </span>
                <span
                  className="block truncate font-bold w-full"
                  style={{
                    color: isDone ? eduGame.primaryDark : isCurrent ? eduGame.primary : eduGame.muted,
                    fontSize: eduGame.fontSize.caption,
                  }}
                >
                  {isCurrent ? '여기' : label}
                </span>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
