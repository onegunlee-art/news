import { motion } from 'framer-motion'
import type { EduThoughtBoardSlot } from '../../../services/eduApi'
import { eduPc } from '../../../constants/eduPcRedesignTheme'

type Props = {
  board: EduThoughtBoardSlot[]
  pulseLayer: string | null
  filledCount: number
}

export default function EduPcThoughtBoardSidebar({ board, pulseLayer, filledCount }: Props) {
  return (
    <aside
      className="shrink-0 flex flex-col border-l overflow-hidden"
      style={{
        width: eduPc.boardWidth,
        borderColor: eduPc.border,
        fontFamily: eduPc.fontBody,
        backgroundColor: 'rgba(255,255,255,0.02)',
      }}
      aria-label="생각판"
    >
      <div
        className="shrink-0 px-4 py-3 border-b"
        style={{ borderColor: eduPc.borderSubtle }}
      >
        <p className="text-sm font-bold" style={{ color: eduPc.ink }}>
          생각판{' '}
          <span style={{ color: eduPc.orange }}>{filledCount}/6</span>
        </p>
      </div>
      <div className="flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-2.5">
        {board.map(slot => {
          const pulsing = pulseLayer === slot.layer_id
          const filled = slot.filled
          return (
            <motion.div
              key={slot.layer_id}
              initial={filled ? { opacity: 0, x: 12 } : false}
              animate={
                pulsing
                  ? {
                      opacity: 1,
                      x: 0,
                      boxShadow: [
                        '0 0 0 0 rgba(232,93,44,0)',
                        '0 0 12px 2px rgba(232,93,44,0.35)',
                        '0 0 0 0 rgba(232,93,44,0)',
                      ],
                    }
                  : { opacity: 1, x: 0 }
              }
              transition={{ duration: 0.45, ease: 'easeOut' }}
              className="rounded-[13px] p-3 min-h-[4.5rem]"
              style={{
                border: filled
                  ? `1px solid ${eduPc.orange}`
                  : `1px dashed ${eduPc.borderDashed}`,
                background: filled ? eduPc.cardFilledGradient : eduPc.cardBg,
              }}
            >
              <p
                className="text-[11px] font-bold mb-1"
                style={{ color: filled ? eduPc.orange : eduPc.inkDim }}
              >
                {slot.index}. {slot.label}
              </p>
              {filled ? (
                <p className="text-xs leading-relaxed line-clamp-4" style={{ color: eduPc.ink }}>
                  {slot.text}
                </p>
              ) : (
                <p className="text-xs" style={{ color: eduPc.inkDim }}>
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
