import { motion } from 'framer-motion'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduThoughtBoardSlot } from '../../services/eduApi'

type Props = {
  board: EduThoughtBoardSlot[]
  pulseLayer: string | null
  collapsed: boolean
  onToggle: () => void
  filledCount: number
}

export default function EduThoughtBoardPanel({ board, pulseLayer, collapsed, onToggle, filledCount }: Props) {
  return (
    <section
      className="shrink-0 border-b"
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      aria-label="생각판"
    >
      <button
        type="button"
        onClick={onToggle}
        className={`flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left ${eduGameClasses.textKo}`}
        aria-expanded={!collapsed}
      >
        <span className="text-sm font-bold" style={{ color: eduGame.ink }}>
          내 생각판
        </span>
        <span className="text-xs font-semibold tabular-nums" style={{ color: eduGame.primary }}>
          {filledCount}/6
        </span>
      </button>

      {!collapsed && (
        <div className="grid grid-cols-1 gap-2 px-3 pb-3 sm:grid-cols-2">
          {board.map(slot => {
            const pulsing = pulseLayer === slot.layer_id
            return (
              <motion.div
                key={slot.layer_id}
                animate={pulsing ? { scale: [1, 1.02, 1], boxShadow: ['0 0 0 0 rgba(216,90,48,0)', '0 0 0 4px rgba(216,90,48,0.25)', '0 0 0 0 rgba(216,90,48,0)'] } : {}}
                transition={{ duration: 0.9, repeat: pulsing ? 2 : 0 }}
                className="rounded-xl border-2 p-2.5 min-h-[4.5rem]"
                style={{
                  borderColor: slot.filled ? eduGame.primary : eduGame.border,
                  backgroundColor: slot.filled ? eduGame.primaryLight : eduGame.surface,
                }}
              >
                <p className="text-xs font-bold mb-1" style={{ color: eduGame.primary }}>
                  {slot.index}. {slot.label}
                </p>
                {slot.filled ? (
                  <p className="text-sm leading-snug" style={{ color: eduGame.ink }}>
                    {slot.text}
                  </p>
                ) : (
                  <p className="text-xs" style={{ color: eduGame.muted }}>
                    대화하며 채워져요
                  </p>
                )}
              </motion.div>
            )
          })}
        </div>
      )}
    </section>
  )
}
