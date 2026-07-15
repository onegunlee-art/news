import { motion } from 'framer-motion'
import type { EduThoughtBoardSlot } from '../../services/eduApi'
import { eduPc, eduPcClasses } from '../../constants/eduPcRedesignTheme'

const LAYER_CIRCLES = ['①', '②', '③', '④', '⑤', '⑥']

type Props = {
  board: EduThoughtBoardSlot[]
  pulseLayer: string | null
  filledCount: number
}

function layerCircle(index: number): string {
  return LAYER_CIRCLES[index - 1] ?? String(index)
}

/** PC 우측 — 생각판 상시 노출 */
export default function EduPcThoughtBoardColumn({ board, pulseLayer, filledCount }: Props) {
  return (
    <aside
      className="shrink-0 flex flex-col border-l overflow-y-auto"
      style={{
        width: eduPc.columnBoard,
        borderColor: eduPc.border,
        backgroundColor: eduPc.surface,
      }}
      aria-label="생각판"
    >
      <header
        className="shrink-0 px-4 py-3 border-b"
        style={{ borderColor: eduPc.border }}
      >
        <p className="text-sm font-bold">
          생각판{' '}
          <span style={{ color: eduPc.primary }}>
            {filledCount}/6
          </span>
        </p>
      </header>
      <div className="flex-1 p-3 space-y-2.5">
        {board.map(slot => {
          const pulsing = pulseLayer === slot.layer_id
          return (
            <motion.div
              key={slot.layer_id}
              initial={slot.filled && pulsing ? { opacity: 0, y: 8 } : false}
              animate={
                pulsing
                  ? {
                      scale: [1, 1.02, 1],
                      boxShadow: [
                        '0 0 0 0 rgba(232,93,44,0)',
                        '0 0 0 4px rgba(232,93,44,0.2)',
                        '0 0 0 0 rgba(232,93,44,0)',
                      ],
                    }
                  : { opacity: 1, y: 0 }
              }
              transition={{ duration: 0.5 }}
              className="rounded-xl px-3 py-2.5 min-h-[4.25rem]"
              style={{
                borderWidth: 2,
                borderStyle: slot.filled ? 'solid' : 'dashed',
                borderColor: slot.filled ? eduPc.primary : eduPc.borderStrong,
                background: slot.filled
                  ? 'linear-gradient(135deg, rgba(232,93,44,0.14) 0%, rgba(232,93,44,0.04) 100%)'
                  : 'transparent',
              }}
            >
              <p className="text-xs font-bold mb-1" style={{ color: eduPc.primary }}>
                {layerCircle(slot.index)} {slot.label}
              </p>
              {slot.filled && slot.text.trim() ? (
                <p className={`text-sm leading-snug ${eduPcClasses.textKo}`} style={{ color: eduPc.text }}>
                  {slot.text}
                </p>
              ) : (
                <p className="text-xs" style={{ color: eduPc.textDim }}>
                  대화하며 채워집니다
                </p>
              )}
            </motion.div>
          )
        })}
      </div>
    </aside>
  )
}
