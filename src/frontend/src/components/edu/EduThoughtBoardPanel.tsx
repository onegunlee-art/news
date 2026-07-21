import { useEffect, useRef } from 'react'
import { motion } from 'framer-motion'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduThoughtBoardSlot } from '../../services/eduApi'

type Props = {
  board: EduThoughtBoardSlot[]
  pulseLayer: string | null
  /** 칩 탭 등 — 해당 slot 하이라이트·스크롤 */
  focusLayer?: string | null
  collapsed: boolean
  onToggle: () => void
  filledCount: number
  /** 모바일: 가로 스크롤·한 줄 요약 (630 v2) */
  compact?: boolean
}

export default function EduThoughtBoardPanel({
  board,
  pulseLayer,
  focusLayer = null,
  collapsed,
  onToggle,
  filledCount,
  compact = false,
}: Props) {
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (collapsed || !focusLayer) return
    const root = scrollRef.current
    if (!root) return
    const slotEl = root.querySelector<HTMLElement>(`[data-board-layer="${focusLayer}"]`)
    slotEl?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
  }, [focusLayer, collapsed, board])

  const slots = (
    <>
      {board.map(slot => {
        const pulsing = pulseLayer === slot.layer_id || focusLayer === slot.layer_id
        return (
          <motion.div
            key={slot.layer_id}
            data-board-layer={slot.layer_id}
            animate={
              pulsing
                ? {
                    scale: [1, 1.02, 1],
                    boxShadow: [
                      '0 0 0 0 rgba(216,90,48,0)',
                      '0 0 0 4px rgba(216,90,48,0.25)',
                      '0 0 0 0 rgba(216,90,48,0)',
                    ],
                  }
                : {}
            }
            transition={{ duration: 0.9, repeat: pulsing ? 2 : 0 }}
            className={
              compact
                ? 'w-[42vw] max-w-[9.5rem] shrink-0 rounded-xl border-2 p-2 min-h-[3.75rem]'
                : 'rounded-xl border-2 p-2.5 min-h-[4.5rem]'
            }
            style={{
              borderColor:
                focusLayer === slot.layer_id
                  ? eduGame.primary
                  : slot.filled
                    ? eduGame.primary
                    : eduGame.border,
              backgroundColor: slot.filled ? eduGame.primaryLight : eduGame.surface,
            }}
          >
            <p className="text-xs font-bold mb-0.5 truncate" style={{ color: eduGame.primary }}>
              {slot.index}. {slot.label}
            </p>
            {slot.filled ? (
              <p className={`leading-snug ${compact ? 'text-xs line-clamp-2' : 'text-sm'}`} style={{ color: eduGame.ink }}>
                {slot.text}
              </p>
            ) : (
              <p className="text-xs" style={{ color: eduGame.muted }}>
                {compact ? '—' : '대화하며 채워져요'}
              </p>
            )}
          </motion.div>
        )
      })}
    </>
  )

  return (
    <section
      className="shrink-0 border-b"
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      aria-label="생각판"
    >
      <button
        type="button"
        onClick={onToggle}
        className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left ${eduGameClasses.textKo}`}
        aria-expanded={!collapsed}
      >
        <span className="text-sm font-bold" style={{ color: eduGame.ink }}>
          {compact ? `생각판 ${filledCount}/6` : '내 생각판'}
        </span>
        <span className="flex items-center gap-1.5 text-xs font-semibold tabular-nums" style={{ color: eduGame.primary }}>
          {!compact && <span>{filledCount}/6</span>}
          <span aria-hidden>{collapsed ? '▼' : '▲'}</span>
        </span>
      </button>

      {!collapsed &&
        (compact ? (
          <div ref={scrollRef} className="overflow-x-auto px-3 pb-2" style={{ maxHeight: '28vh' }}>
            <div className="flex gap-2 pb-1">{slots}</div>
          </div>
        ) : (
          <div ref={scrollRef} className="grid grid-cols-1 gap-2 px-3 pb-3 sm:grid-cols-2">
            {slots}
          </div>
        ))}
    </section>
  )
}
