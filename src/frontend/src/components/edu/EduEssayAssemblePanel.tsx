import { useCallback, useEffect, useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduThoughtBoardSlot } from '../../services/eduApi'
import {
  assembleDraftFromBoard,
  piecesFromThoughtBoard,
  type EssayAssemblePiece,
} from '../../utils/eduEssayAssemble'

type Props = {
  board: EduThoughtBoardSlot[]
  questTitle?: string | null
  onComplete: () => void
  reducedMotion?: boolean
}

type Phase = 'scatter' | 'assemble' | 'typing' | 'done'

const SCATTER_OFFSETS = [
  { x: -48, y: -32, rotate: -4 },
  { x: 52, y: -28, rotate: 3 },
  { x: -40, y: 36, rotate: 2 },
  { x: 44, y: 40, rotate: -3 },
  { x: -56, y: 8, rotate: -2 },
  { x: 50, y: -8, rotate: 4 },
]

function usePrefersReducedMotion(): boolean {
  const [reduced, setReduced] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  )

  useEffect(() => {
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)')
    const onChange = () => setReduced(mq.matches)
    mq.addEventListener('change', onChange)
    return () => mq.removeEventListener('change', onChange)
  }, [])

  return reduced
}

function PieceChip({
  piece,
  phase,
  reducedMotion,
  scatterIndex,
}: {
  piece: EssayAssemblePiece
  phase: Phase
  reducedMotion: boolean
  scatterIndex: number
}) {
  const offset = SCATTER_OFFSETS[scatterIndex % SCATTER_OFFSETS.length]
  const assembled = phase === 'assemble' || phase === 'typing' || phase === 'done'

  return (
    <motion.div
      layout={!reducedMotion}
      initial={
        reducedMotion
          ? { opacity: 0 }
          : { opacity: 0, x: offset.x, y: offset.y, rotate: offset.rotate, scale: 0.92 }
      }
      animate={
        reducedMotion
          ? { opacity: assembled ? 1 : 0.85 }
          : assembled
            ? { opacity: 1, x: 0, y: 0, rotate: 0, scale: 1 }
            : { opacity: 1, x: offset.x, y: offset.y, rotate: offset.rotate, scale: 1 }
      }
      transition={{ duration: reducedMotion ? 0.2 : 0.55, ease: 'easeOut' }}
      className="rounded-xl border-2 px-3 py-2.5 w-full max-w-[17rem]"
      style={{
        borderColor: eduGame.primary,
        backgroundColor: eduGame.primaryLight,
      }}
    >
      <p className="text-xs font-bold mb-1 truncate" style={{ color: eduGame.primary }}>
        {piece.index}. {piece.label}
      </p>
      {assembled && piece.connector ? (
        <p className="text-xs font-semibold mb-0.5" style={{ color: eduGame.muted }}>
          {piece.connector.trim()}
        </p>
      ) : null}
      <p
        className={`text-sm leading-snug line-clamp-2 ${eduGameClasses.textKo}`}
        style={{ color: eduGame.ink }}
      >
        {piece.displayText}
      </p>
    </motion.div>
  )
}

function TypingDraft({ text, reducedMotion, onDone }: { text: string; reducedMotion: boolean; onDone: () => void }) {
  const [visible, setVisible] = useState(reducedMotion ? text.length : 0)

  useEffect(() => {
    if (reducedMotion) {
      setVisible(text.length)
      const t = window.setTimeout(onDone, 400)
      return () => window.clearTimeout(t)
    }

    if (visible >= text.length) {
      const t = window.setTimeout(onDone, 600)
      return () => window.clearTimeout(t)
    }

    const step = text.length > 180 ? 3 : text.length > 100 ? 2 : 1
    const delay = text.length > 180 ? 18 : 28
    const t = window.setTimeout(() => setVisible(v => Math.min(v + step, text.length)), delay)
    return () => window.clearTimeout(t)
  }, [text, visible, reducedMotion, onDone])

  const shown = text.slice(0, visible)

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: reducedMotion ? 0.2 : 0.35 }}
      className="rounded-2xl border-2 px-4 py-4 max-h-[40vh] overflow-y-auto"
      style={{
        borderColor: eduGame.border,
        backgroundColor: eduGame.bg,
        fontFamily: eduGame.fontLogo,
      }}
    >
      <p className={`text-base leading-relaxed whitespace-pre-wrap ${eduGameClasses.textKo}`} style={{ color: eduGame.ink }}>
        {shown}
        {!reducedMotion && visible < text.length ? (
          <span className="inline-block w-0.5 h-4 ml-0.5 align-middle animate-pulse" style={{ backgroundColor: eduGame.primary }} />
        ) : null}
      </p>
    </motion.div>
  )
}

export default function EduEssayAssemblePanel({ board, questTitle, onComplete, reducedMotion: reducedProp }: Props) {
  const systemReduced = usePrefersReducedMotion()
  const reducedMotion = reducedProp ?? systemReduced
  const pieces = useMemo(() => piecesFromThoughtBoard(board), [board])
  const draft = useMemo(() => assembleDraftFromBoard(board), [board])
  const title = (questTitle ?? '').trim() || '오늘의 탐구'

  const [phase, setPhase] = useState<Phase>(reducedMotion ? 'typing' : 'scatter')

  const advanceToAssemble = useCallback(() => setPhase('assemble'), [])
  const advanceToTyping = useCallback(() => setPhase('typing'), [])
  const advanceToDone = useCallback(() => {
    setPhase('done')
    onComplete()
  }, [onComplete])

  useEffect(() => {
    if (reducedMotion) return
    if (phase !== 'scatter') return
    const t = window.setTimeout(advanceToAssemble, 900)
    return () => window.clearTimeout(t)
  }, [phase, reducedMotion, advanceToAssemble])

  useEffect(() => {
    if (reducedMotion) return
    if (phase !== 'assemble') return
    const t = window.setTimeout(advanceToTyping, 700)
    return () => window.clearTimeout(t)
  }, [phase, reducedMotion, advanceToTyping])

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col overflow-hidden"
      style={{
        backgroundColor: '#0d0d0d',
        paddingTop: 'env(safe-area-inset-top, 0px)',
        paddingBottom: 'env(safe-area-inset-bottom, 0px)',
      }}
      role="dialog"
      aria-label="글 조립"
      aria-live="polite"
    >
      <div className="flex-1 min-h-0 flex flex-col max-w-2xl mx-auto w-full px-4 py-6">
        <header className="shrink-0 text-center mb-6">
          <p
            className="text-sm font-semibold tracking-wide mb-1"
            style={{ color: eduGame.primary, fontFamily: eduGame.fontBody }}
          >
            글로 엮기
          </p>
          <h1
            className="text-xl font-bold truncate px-2"
            style={{ color: '#f5f5f5', fontFamily: eduGame.fontLogo }}
          >
            {title}
          </h1>
          <p className="text-xs mt-2" style={{ color: '#888' }}>
            {phase === 'scatter'
              ? '흩어진 생각을 모으는 중…'
              : phase === 'assemble'
                ? '조각을 이어 붙이는 중…'
                : phase === 'typing'
                  ? '초안을 다듬는 중…'
                  : '글을 쓰러 갑니다…'}
          </p>
        </header>

        <div className="flex-1 min-h-0 flex flex-col items-center justify-center gap-4 overflow-y-auto">
          <AnimatePresence mode="wait">
            {phase === 'scatter' || phase === 'assemble' ? (
              <motion.div
                key="pieces"
                className="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-md place-items-center"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
              >
                {pieces.map((piece, i) => (
                  <PieceChip
                    key={piece.layerId}
                    piece={piece}
                    phase={phase}
                    reducedMotion={reducedMotion}
                    scatterIndex={i}
                  />
                ))}
              </motion.div>
            ) : (
              <motion.div key="draft" className="w-full">
                <TypingDraft text={draft} reducedMotion={reducedMotion} onDone={advanceToDone} />
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  )
}
