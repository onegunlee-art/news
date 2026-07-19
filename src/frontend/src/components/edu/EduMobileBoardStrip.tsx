import { useLayoutEffect, useMemo, useRef } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import type { EduThoughtBoardSlot } from '../../services/eduApi'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { summarizeBoardChipText } from '../../utils/eduBoardChipSummary'
import { QUEST_FLOW_PAGE_MAX } from './questFlowNarrativeV2Shared'

const CIRCLED = ['①', '②', '③', '④', '⑤', '⑥'] as const
const POP_IN = { scale: 0.8, opacity: 0 }
const POP_IN_TRANSITION = { duration: 0.4, ease: 'easeOut' as const }

type Props = {
  board: EduThoughtBoardSlot[]
  hidden?: boolean
  onChipTap?: (layerId: string) => void
}

function circledIndex(index: number): string {
  return CIRCLED[index - 1] ?? `${index}`
}

export default function EduMobileBoardStrip({ board, hidden = false, onChipTap }: Props) {
  const sorted = useMemo(() => [...board].sort((a, b) => a.index - b.index), [board])
  const filled = useMemo(
    () => sorted.filter(slot => slot.filled && slot.text.trim() !== ''),
    [sorted]
  )
  const current = useMemo(() => sorted.find(slot => !slot.filled) ?? null, [sorted])
  const filledIds = useMemo(() => filled.map(slot => slot.layer_id), [filled])

  /** null = 첫 hydrate(복원) — popIn 없음. view only, 상태 게이트 0 */
  const prevFilledRef = useRef<string[] | null>(null)
  const popInIdsRef = useRef<Set<string>>(new Set())

  const newcomers =
    prevFilledRef.current === null
      ? []
      : filledIds.filter(id => !prevFilledRef.current!.includes(id))

  newcomers.forEach(id => popInIdsRef.current.add(id))

  useLayoutEffect(() => {
    prevFilledRef.current = filledIds
  }, [filledIds])

  if (hidden) return null

  return (
    <section
      className={`shrink-0 border-b ${QUEST_FLOW_PAGE_MAX} mx-auto w-full`}
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      aria-label="생각 누적"
    >
      <div className="px-3 py-2">
        {sorted.length === 0 ? (
          <p className="text-xs" style={{ color: eduGame.muted }}>
            대화하며 생각이 하나씩 쌓여요…
          </p>
        ) : (
          <div className="flex flex-wrap gap-1.5">
            {filled.map(slot => (
              <motion.button
                key={slot.layer_id}
                type="button"
                layout
                initial={popInIdsRef.current.has(slot.layer_id) ? POP_IN : false}
                animate={{ scale: 1, opacity: 1 }}
                transition={POP_IN_TRANSITION}
                onAnimationComplete={() => {
                  popInIdsRef.current.delete(slot.layer_id)
                }}
                onClick={() => onChipTap?.(slot.layer_id)}
                className={`inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-1 text-xs font-semibold ${eduGameClasses.textKo}`}
                style={{
                  borderColor: eduGame.primary,
                  color: eduGame.ink,
                  backgroundColor: eduGame.primaryLight,
                }}
              >
                <span style={{ color: eduGame.primary }}>{circledIndex(slot.index)}</span>
                <span className="truncate" style={{ maxWidth: '7.5rem' }}>
                  {summarizeBoardChipText(slot.text, slot.label)}
                </span>
              </motion.button>
            ))}
            <AnimatePresence mode="popLayout">
              {current ? (
                <motion.button
                  key={current.layer_id}
                  type="button"
                  layout
                  initial={{ scale: 0.95, opacity: 0.5 }}
                  animate={{ scale: 1, opacity: 1 }}
                  exit={{ scale: 0.95, opacity: 0 }}
                  transition={{ duration: 0.25, ease: 'easeOut' }}
                  onClick={() => onChipTap?.(current.layer_id)}
                  className={`inline-flex max-w-full items-center gap-1 rounded-full border border-dashed px-2 py-1 text-xs font-semibold ${eduGameClasses.textKo}`}
                  style={{
                    borderColor: eduGame.muted,
                    color: eduGame.muted,
                    backgroundColor: eduGame.bg,
                  }}
                >
                  <span>{circledIndex(current.index)}</span>
                  <span className="truncate" style={{ maxWidth: '8.5rem' }}>
                    {current.label} 작성 중
                  </span>
                </motion.button>
              ) : null}
            </AnimatePresence>
          </div>
        )}
      </div>
    </section>
  )
}
