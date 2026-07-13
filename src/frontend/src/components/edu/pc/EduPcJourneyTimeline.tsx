import { motion } from 'framer-motion'
import type { EduThoughtBoardSlot } from '../../../services/eduApi'
import { eduPc } from '../../../constants/eduPcRedesignTheme'
import {
  journeyLayerStatus,
  layerCircle,
  resolveCurrentJourneyLayerId,
} from '../questFlowNarrativeV2Shared'

type Props = {
  board: EduThoughtBoardSlot[]
  pulseLayer: string | null
}

export default function EduPcJourneyTimeline({ board, pulseLayer }: Props) {
  const currentId = resolveCurrentJourneyLayerId(board, pulseLayer)

  return (
    <aside
      className="shrink-0 flex flex-col border-r overflow-y-auto py-5 px-3"
      style={{
        width: eduPc.journeyWidth,
        borderColor: eduPc.border,
        fontFamily: eduPc.fontBody,
      }}
      aria-label="탐구 여정"
    >
      <p
        className="text-xs font-bold mb-4 px-1 tracking-wide"
        style={{ color: eduPc.inkDim }}
      >
        탐구 여정
      </p>
      <ol className="flex flex-col gap-0 list-none m-0 p-0">
        {board.map((slot, i) => {
          const status = journeyLayerStatus(slot, currentId)
          const isLast = i === board.length - 1
          const nodeColor =
            status === 'done'
              ? eduPc.orange
              : status === 'current'
                ? eduPc.orange
                : 'rgba(255,255,255,0.2)'
          const textColor =
            status === 'done'
              ? eduPc.orange
              : status === 'current'
                ? eduPc.ink
                : eduPc.inkDim

          return (
            <li key={slot.layer_id} className="flex gap-2.5 relative">
              <div className="flex flex-col items-center shrink-0 w-5">
                <motion.span
                  className="flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold shrink-0"
                  style={{
                    backgroundColor: status === 'future' ? 'transparent' : eduPc.orangeDim,
                    border: `2px solid ${nodeColor}`,
                    color: textColor,
                  }}
                  animate={
                    status === 'current'
                      ? { boxShadow: ['0 0 0 0 rgba(232,93,44,0)', '0 0 0 6px rgba(232,93,44,0.25)', '0 0 0 0 rgba(232,93,44,0)'] }
                      : {}
                  }
                  transition={{ duration: 1.6, repeat: status === 'current' ? Infinity : 0 }}
                >
                  {layerCircle(slot.index)}
                </motion.span>
                {!isLast && (
                  <div
                    className="w-0.5 flex-1 min-h-[28px] my-0.5"
                    style={{
                      backgroundColor:
                        status === 'done' ? eduPc.orange : 'rgba(255,255,255,0.1)',
                    }}
                  />
                )}
              </div>
              <div className="pb-4 min-w-0 pt-0.5">
                <p
                  className="text-xs font-bold leading-tight"
                  style={{ color: textColor, fontFamily: eduPc.fontHeadline }}
                >
                  {slot.label}
                </p>
              </div>
            </li>
          )
        })}
      </ol>
    </aside>
  )
}
