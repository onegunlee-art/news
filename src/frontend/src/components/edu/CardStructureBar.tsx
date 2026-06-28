import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { CARD_STRUCTURE_SLOTS, resolveCardStructureBarState } from './cardStructureBarState'
import StructureBarFillingOverlay from './StructureBarFillingOverlay'

type Props = {
  phase: string
  guideAxisIndex: number
  pulse: boolean
  pulseSlot: number | null
  nudgeText: string
  compact?: boolean
  waiting?: boolean
}

/** 카드형 5블록 — 배경→입장→갈등→반론→결론 (채팅형 1+3 애니메이션 동일 클래스) */
export default function CardStructureBar({
  phase,
  guideAxisIndex,
  pulse,
  pulseSlot,
  nudgeText,
  compact = false,
  waiting = false,
}: Props) {
  const { completed, current } = resolveCardStructureBarState(phase, guideAxisIndex)
  const total = CARD_STRUCTURE_SLOTS.length
  const stepNum =
    current >= 0 ? Math.min(completed + 1, total) : Math.min(Math.max(completed, 0), total)

  return (
    <div
      className={`shrink-0 border-b px-4 ${compact ? 'py-1.5' : 'py-2.5'}`}
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
    >
      <div className="max-w-2xl mx-auto w-full">
        {!compact && (
          <div className="mb-1.5 flex items-center justify-between gap-2">
            <span
              className="font-bold"
              style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}
            >
              글 구조
            </span>
            <span
              className="font-bold tabular-nums"
              style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primary }}
              aria-live="polite"
            >
              {stepNum}/{total}
            </span>
          </div>
        )}
        <div className={`relative flex ${compact ? 'gap-1' : 'gap-1.5'}`} role="list" aria-label="글 구조 진행">
          {CARD_STRUCTURE_SLOTS.map((label, i) => {
            const isDone = i < completed
            const isCurrent = !isDone && i === current && current >= 0
            const isPending = !isDone && !isCurrent
            const isFilling = waiting && isCurrent
            const justFilled = pulse && pulseSlot === i

            const slotStyle: { borderColor: string; backgroundColor: string } = isDone
              ? { borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }
              : isCurrent
                ? { borderColor: eduGame.primary, backgroundColor: eduGame.bg }
                : { borderColor: eduGame.border, backgroundColor: isPending ? eduGame.bg : eduGame.surface }

            let slotClass = ''
            if (isCurrent || isFilling) slotClass = eduGameClasses.animAxisCurrent
            if (justFilled) slotClass = `${slotClass} ${eduGameClasses.animAxisPop}`.trim()

            return (
              <div
                key={label}
                role="listitem"
                aria-current={isCurrent ? 'step' : undefined}
                aria-label={
                  isFilling
                    ? `${label} 채우는 중`
                    : isDone
                      ? `${label} 완료`
                      : isCurrent
                        ? `${label} 진행 중`
                        : `${label} 대기`
                }
                className={`relative flex-1 min-w-0 flex flex-col items-center gap-0.5 rounded-lg border-2 text-center transition-colors duration-300 ${compact ? 'py-1 px-0.5' : 'py-2 px-0.5'} ${slotClass}`}
                style={slotStyle}
              >
                {isFilling && <StructureBarFillingOverlay />}
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
                  className={`relative z-[1] font-bold leading-none ${justFilled ? eduGameClasses.animAxisCheckPop : ''}`}
                  style={{
                    color: isDone ? eduGame.primary : isCurrent ? eduGame.primary : eduGame.muted,
                    fontSize: compact ? '0.75rem' : eduGame.fontSize.label,
                  }}
                  aria-hidden
                >
                  {isDone ? '✓' : isFilling ? '▮' : isCurrent ? '●' : '·'}
                </span>
                <span
                  className="relative z-[1] block truncate font-bold w-full"
                  style={{
                    color: isDone ? eduGame.primaryDark : isCurrent || isFilling ? eduGame.primary : eduGame.muted,
                    fontSize: eduGame.fontSize.caption,
                  }}
                >
                  {isFilling ? '채우는 중' : isCurrent ? '여기' : label}
                </span>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
