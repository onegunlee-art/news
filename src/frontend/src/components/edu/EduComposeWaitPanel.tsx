import { useEffect, useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduThoughtBoardSlot } from '../../services/eduApi'
import { piecesFromThoughtBoard } from '../../utils/eduEssayAssemble'

type Props = {
  board: EduThoughtBoardSlot[]
  questTitle?: string | null
  turnCount: number
  layout?: 'mobile' | 'wide'
}

type Act = 'scatter' | 'gather' | 'reflect'

const SCATTER_OFFSETS = [
  { x: -56, y: -36, rotate: -5 },
  { x: 58, y: -30, rotate: 4 },
  { x: -46, y: 38, rotate: 3 },
  { x: 50, y: 42, rotate: -4 },
  { x: -62, y: 6, rotate: -2 },
  { x: 54, y: -6, rotate: 5 },
]

const STATUS_LINES = [
  '네 생각을 읽는 중…',
  '논증 구조를 세우는 중…',
  '문장을 다듬는 중…',
] as const

const LAYER_CIRCLES = ['①', '②', '③', '④', '⑤', '⑥']

const SCATTER_MS = 1500
const GATHER_MS = 1500

function layerCircle(index: number): string {
  return LAYER_CIRCLES[index - 1] ?? String(index)
}

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

function reflectionSlots(board: EduThoughtBoardSlot[]) {
  const byIndex = (n: number) => board.find(s => s.index === n)
  return { first: byIndex(1), last: byIndex(6) }
}

type ReflectionCardProps = {
  first: EduThoughtBoardSlot | undefined
  last: EduThoughtBoardSlot | undefined
  turnCount: number
  statusLine: string
  wide: boolean
}

/** 3막 회고 — 유일한 bordered 박스 */
function ReflectionCard({ first, last, turnCount, statusLine, wide }: ReflectionCardProps) {
  return (
    <div
      className={`w-full rounded-2xl border-2 px-4 py-4 space-y-3 ${wide ? 'max-w-lg' : 'max-w-md'}`}
      style={{
        borderColor: eduGame.primary,
        backgroundColor: 'rgba(216, 90, 48, 0.08)',
      }}
    >
      <p className="text-sm font-bold" style={{ color: '#f5f5f5' }}>
        📌 처음:{' '}
        <span style={{ color: eduGame.primary }}>
          {first ? `${layerCircle(first.index)} ${first.label}` : '① 입장'}
        </span>
      </p>
      {first?.filled && first.text.trim() ? (
        <p className={`text-sm pl-1 ${eduGameClasses.textKo}`} style={{ color: '#ccc' }}>
          {first.text.trim()}
        </p>
      ) : null}
      <p className="text-sm font-bold pt-1" style={{ color: '#f5f5f5' }}>
        지금:{' '}
        <span style={{ color: eduGame.primary }}>
          {last ? `${layerCircle(last.index)} ${last.label}` : '⑥ 종합'}
        </span>
      </p>
      {last?.filled && last.text.trim() ? (
        <p className={`text-sm pl-1 ${eduGameClasses.textKo}`} style={{ color: '#ccc' }}>
          {last.text.trim()}
        </p>
      ) : null}
      <p className="text-xs pt-1" style={{ color: '#888' }}>
        {turnCount > 0 ? `${turnCount}턴 동안 생각을 여섯 번 세웠어` : '여섯 겹 생각을 글로 엮는 중'}
      </p>
      <p
        className="text-center text-sm font-semibold pt-2 animate-pulse"
        style={{ color: eduGame.primary, fontFamily: eduGame.fontBody }}
      >
        ✦ {statusLine}
      </p>
    </div>
  )
}

type PieceGridProps = {
  act: 'scatter' | 'gather'
  pieces: ReturnType<typeof piecesFromThoughtBoard>
  wide: boolean
  reducedMotion: boolean
}

/** 1~2막 — 생각 카드만 (회고 박스 없음) */
function PieceGrid({ act, pieces, wide, reducedMotion }: PieceGridProps) {
  const gathered = act === 'gather'

  return (
    <div
      className={`grid gap-3 w-full place-items-center ${
        wide ? 'grid-cols-3 max-w-2xl' : 'grid-cols-1 sm:grid-cols-2 max-w-md'
      }`}
    >
      {pieces.map((piece, i) => {
        const offset = SCATTER_OFFSETS[i % SCATTER_OFFSETS.length]
        return (
          <motion.div
            key={piece.layerId}
            initial={
              reducedMotion
                ? { opacity: 0 }
                : { opacity: 0, x: offset.x, y: offset.y, rotate: offset.rotate, scale: 0.9 }
            }
            animate={
              gathered
                ? { opacity: 0, x: 0, y: 0, rotate: 0, scale: 0.15 }
                : {
                    opacity: 1,
                    x: offset.x,
                    y: offset.y,
                    rotate: offset.rotate,
                    scale: 1,
                  }
            }
            transition={{ duration: reducedMotion ? 0.2 : gathered ? 1.2 : 0.5, ease: 'easeOut' }}
            className="rounded-xl border-2 px-3 py-2.5 w-full max-w-[17rem]"
            style={{
              borderColor: eduGame.primary,
              backgroundColor: eduGame.primaryLight,
            }}
          >
            <p className="text-xs font-bold mb-1 truncate" style={{ color: eduGame.primary }}>
              {piece.index}. {piece.label}
            </p>
            <p
              className={`text-sm leading-snug line-clamp-2 ${eduGameClasses.textKo}`}
              style={{ color: eduGame.ink }}
            >
              {piece.displayText}
            </p>
          </motion.div>
        )
      })}
    </div>
  )
}

/** compose 대기 — scatter → gather(페이드아웃) → reflect(박스 1개). 순수 view. */
export default function EduComposeWaitPanel({
  board,
  questTitle,
  turnCount,
  layout = 'mobile',
}: Props) {
  const reducedMotion = usePrefersReducedMotion()
  const pieces = useMemo(() => piecesFromThoughtBoard(board), [board])
  const title = (questTitle ?? '').trim() || '오늘의 탐구'
  const { first, last } = reflectionSlots(board)

  const [act, setAct] = useState<Act>(reducedMotion ? 'reflect' : 'scatter')
  const [statusIdx, setStatusIdx] = useState(0)

  useEffect(() => {
    if (reducedMotion) return
    const t1 = window.setTimeout(() => setAct('gather'), SCATTER_MS)
    const t2 = window.setTimeout(() => setAct('reflect'), SCATTER_MS + GATHER_MS)
    return () => {
      window.clearTimeout(t1)
      window.clearTimeout(t2)
    }
  }, [reducedMotion])

  useEffect(() => {
    if (act !== 'reflect') return
    const t = window.setInterval(() => {
      setStatusIdx(i => (i + 1) % STATUS_LINES.length)
    }, 2500)
    return () => window.clearInterval(t)
  }, [act])

  const wide = layout === 'wide'
  const shellBg = wide ? '#070707' : '#0d0d0d'

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col overflow-hidden"
      style={{
        backgroundColor: shellBg,
        paddingTop: 'env(safe-area-inset-top, 0px)',
        paddingBottom: 'env(safe-area-inset-bottom, 0px)',
      }}
      role="dialog"
      aria-label="글을 만드는 중"
      aria-live="polite"
    >
      <div
        className={`flex-1 min-h-0 flex flex-col mx-auto w-full px-4 py-6 ${wide ? 'max-w-4xl' : 'max-w-2xl'}`}
      >
        <header className="shrink-0 text-center mb-5">
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
        </header>

        <div className="flex-1 min-h-0 flex items-center justify-center overflow-y-auto">
          <AnimatePresence mode="wait">
            {act === 'reflect' ? (
              <motion.div
                key="reflect"
                className="w-full flex justify-center px-1"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0 }}
                transition={{ duration: reducedMotion ? 0.15 : 0.4, ease: 'easeOut' }}
              >
                <ReflectionCard
                  first={first}
                  last={last}
                  turnCount={turnCount}
                  statusLine={STATUS_LINES[statusIdx]}
                  wide={wide}
                />
              </motion.div>
            ) : (
              <motion.div
                key={act}
                className="w-full flex justify-center"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: reducedMotion ? 0.1 : 0.35 }}
              >
                <PieceGrid act={act} pieces={pieces} wide={wide} reducedMotion={reducedMotion} />
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  )
}
