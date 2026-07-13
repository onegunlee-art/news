import { useEffect, useMemo, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduThoughtBoardSlot } from '../../services/eduApi'
import { piecesFromThoughtBoard } from '../../utils/eduEssayAssemble'

type Props = {
  board: EduThoughtBoardSlot[]
  questTitle?: string | null
  turnCount: number
  /** 모바일 세로 / PC 넓은 레이아웃 (Phase B) */
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
  const first = byIndex(1)
  const last = byIndex(6)
  return { first, last }
}

type ReflectionCardProps = {
  first: EduThoughtBoardSlot | undefined
  last: EduThoughtBoardSlot | undefined
  turnCount: number
  statusLine: string
  wide: boolean
}

function ReflectionCard({ first, last, turnCount, statusLine, wide }: ReflectionCardProps) {
  return (
    <div
      className={`w-full rounded-2xl border-2 px-4 py-4 space-y-3 ${wide ? 'max-w-lg' : 'max-w-md'}`}
      style={{
        borderColor: eduGame.primary,
        backgroundColor: 'rgba(216, 90, 48, 0.08)',
        boxShadow: '0 0 28px rgba(216, 90, 48, 0.28)',
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

/** compose 대기 — 조각 흩어짐 → 모임 → 회고 카드. 상태 전환 책임 없음(순수 view). */
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
    const t1 = window.setTimeout(() => setAct('gather'), 1000)
    const t2 = window.setTimeout(() => setAct('reflect'), 2500)
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
  const showPieces = act === 'scatter' || act === 'gather'
  const gathered = act === 'gather'

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col overflow-hidden"
      style={{
        backgroundColor: '#0d0d0d',
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

        <div className="flex-1 min-h-0 flex flex-col items-center justify-center gap-5 overflow-y-auto">
          <div
            className={`relative w-full flex items-center justify-center ${wide ? 'min-h-[300px]' : 'min-h-[240px]'}`}
          >
            <AnimatePresence>
              {showPieces ? (
                <motion.div
                  key="pieces"
                  className="absolute inset-0 flex items-center justify-center"
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0, scale: 0.92 }}
                  transition={{ duration: reducedMotion ? 0.15 : 0.45 }}
                >
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
                          layout={!reducedMotion}
                          initial={
                            reducedMotion
                              ? { opacity: 0 }
                              : { opacity: 0, x: offset.x, y: offset.y, rotate: offset.rotate, scale: 0.9 }
                          }
                          animate={
                            gathered
                              ? { opacity: 0, x: 0, y: 0, rotate: 0, scale: 0.3 }
                              : {
                                  opacity: 1,
                                  x: offset.x,
                                  y: offset.y,
                                  rotate: offset.rotate,
                                  scale: 1,
                                }
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
                </motion.div>
              ) : null}
            </AnimatePresence>

            <AnimatePresence>
              {act === 'reflect' ? (
                <motion.div
                  key="reflect"
                  className="w-full flex items-center justify-center px-1"
                  initial={{ opacity: 0, y: 16, scale: 0.96 }}
                  animate={{ opacity: 1, y: 0, scale: 1 }}
                  transition={{ duration: reducedMotion ? 0.2 : 0.5, ease: 'easeOut' }}
                >
                  <ReflectionCard
                    first={first}
                    last={last}
                    turnCount={turnCount}
                    statusLine={STATUS_LINES[statusIdx]}
                    wide={wide}
                  />
                </motion.div>
              ) : null}
            </AnimatePresence>
          </div>
        </div>
      </div>
    </div>
  )
}
