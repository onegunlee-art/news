import type { EduThoughtBoardSlot } from '../../services/eduApi'
import { eduPc, eduPcClasses } from '../../constants/eduPcRedesignTheme'

const LAYER_CIRCLES = ['①', '②', '③', '④', '⑤', '⑥']

type Props = {
  board: EduThoughtBoardSlot[]
  pulseLayer: string | null
  narrativeV2Node: string
}

function layerCircle(index: number): string {
  return LAYER_CIRCLES[index - 1] ?? String(index)
}

function currentLayerIndex(board: EduThoughtBoardSlot[]): number {
  const firstOpen = board.find(s => !s.filled)
  if (firstOpen) return firstOpen.index
  return board.length > 0 ? board[board.length - 1].index : 1
}

/** PC 좌측 — 탐구 여정 타임라인 */
export default function EduPcJourneyTimeline({ board, pulseLayer, narrativeV2Node }: Props) {
  const activeIndex = currentLayerIndex(board)

  return (
    <aside
      className="shrink-0 flex flex-col border-r px-4 py-5 overflow-y-auto"
      style={{
        width: eduPc.columnJourney,
        borderColor: eduPc.border,
        backgroundColor: eduPc.surface,
      }}
      aria-label="탐구 여정"
    >
      <p className="text-xs font-bold mb-4 tracking-wide" style={{ color: eduPc.textMuted }}>
        탐구 여정
      </p>
      <ol className="space-y-0">
        {board.map((slot, i) => {
          const isDone = slot.filled
          const isCurrent = !isDone && slot.index === activeIndex
          const isPulsing = pulseLayer === slot.layer_id
          const isFuture = !isDone && !isCurrent

          return (
            <li key={slot.layer_id} className="relative flex gap-3 pb-5 last:pb-0">
              {i < board.length - 1 ? (
                <span
                  className="absolute left-[11px] top-6 bottom-0 w-px"
                  style={{
                    backgroundColor: isDone ? eduPc.primary : eduPc.borderStrong,
                  }}
                  aria-hidden
                />
              ) : null}
              <span
                className={`relative z-10 shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center text-[10px] font-bold ${
                  isPulsing || isCurrent ? 'animate-pulse' : ''
                }`}
                style={{
                  borderColor: isDone || isCurrent ? eduPc.primary : eduPc.borderStrong,
                  backgroundColor: isDone ? eduPc.primary : isCurrent ? 'rgba(232,93,44,0.2)' : 'transparent',
                  color: isDone || isCurrent ? eduPc.text : eduPc.textDim,
                }}
              >
                {layerCircle(slot.index)}
              </span>
              <div className="min-w-0 pt-0.5">
                <p
                  className={`text-sm font-bold leading-tight ${eduPcClasses.textKo}`}
                  style={{
                    color: isFuture ? eduPc.textDim : eduPc.text,
                  }}
                >
                  {slot.label}
                </p>
                {isDone && slot.text.trim() ? (
                  <p className="text-xs mt-1 line-clamp-2" style={{ color: eduPc.textMuted }}>
                    {slot.text.trim()}
                  </p>
                ) : null}
              </div>
            </li>
          )
        })}
      </ol>
      {narrativeV2Node ? (
        <p className="mt-4 text-[10px] font-mono truncate" style={{ color: eduPc.textDim }} title={narrativeV2Node}>
          node: {narrativeV2Node}
        </p>
      ) : null}
    </aside>
  )
}
