import { useCallback, useEffect, useMemo, useState } from 'react'
import { motion } from 'framer-motion'
import { EDU_BRAND } from '../../constants/eduBrand'
import EssayReadEdit from './EssayReadEdit'
import type { EssayArtifact } from './EssayRevealCard'
import {
  buildRevealSteps,
  getEssayRevealMode,
  markEssayRevealSeen,
  prefersReducedMotion,
  revealTiming,
  type RevealStep,
} from './essayReveal'

interface EssayRevealWrapperProps {
  essay: EssayArtifact
  onChange: (essay: EssayArtifact) => void
  disabled?: boolean
  authorName?: string | null
  /** compose 직후 true — 재방문·새로고침은 false */
  playReveal: boolean
  onRevealComplete?: () => void
}

const fadeUp = {
  hidden: { opacity: 0, y: 10 },
  visible: (duration: number) => ({
    opacity: 1,
    y: 0,
    transition: { duration, ease: [0.25, 0.1, 0.25, 1] },
  }),
}

function RevealStepView({ step }: { step: RevealStep }) {
  switch (step.kind) {
    case 'congrats':
      return (
        <p className="text-sm font-medium mb-2" style={{ color: EDU_BRAND.accent }}>
          글이 완성됐어!
        </p>
      )
    case 'title':
      return (
        <h2 className="text-2xl sm:text-3xl font-bold leading-tight tracking-tight">
          {step.text}
        </h2>
      )
    case 'subtitle':
      return (
        <p className="text-base leading-relaxed" style={{ color: EDU_BRAND.muted }}>
          {step.text}
        </p>
      )
    case 'section-heading':
      return (
        <h3
          className="text-sm font-bold tracking-wide mt-2"
          style={{
            color: EDU_BRAND.accent,
            borderLeft: `3px solid ${EDU_BRAND.accent}`,
            paddingLeft: '0.75rem',
          }}
        >
          {step.text}
        </h3>
      )
    case 'conclusion-heading':
      return (
        <h3
          className="text-base font-bold pt-4 mt-2"
          style={{ borderTop: `1px solid ${EDU_BRAND.border}` }}
        >
          {step.text}
        </h3>
      )
    case 'paragraph':
      return <p className="text-base leading-[1.75] text-[#333]">{step.text}</p>
    case 'full-text':
      return (
        <p className="text-base leading-[1.75] text-[#333] whitespace-pre-wrap">{step.text}</p>
      )
    case 'hero':
      return (
        <blockquote
          className="text-lg leading-snug italic py-4 px-5 rounded-xl mt-2"
          style={{
            color: EDU_BRAND.ink,
            backgroundColor: EDU_BRAND.accentBg,
            borderLeft: `4px solid ${EDU_BRAND.accent}`,
          }}
        >
          {step.text}
        </blockquote>
      )
    case 'byline':
      return (
        <footer
          className="text-right text-sm pt-4 mt-4"
          style={{ color: EDU_BRAND.muted, borderTop: `1px solid ${EDU_BRAND.border}` }}
        >
          by {step.text}
        </footer>
      )
    default:
      return null
  }
}

export default function EssayRevealWrapper({
  essay,
  onChange,
  disabled,
  authorName,
  playReveal,
  onRevealComplete,
}: EssayRevealWrapperProps) {
  const mode = useMemo(() => (playReveal ? getEssayRevealMode() : 'off'), [playReveal])
  const skipMotion = prefersReducedMotion()

  const steps = useMemo(
    () => (playReveal && mode !== 'off' && !skipMotion ? buildRevealSteps(essay, authorName, mode) : []),
    [playReveal, mode, skipMotion, essay, authorName]
  )

  const { staggerSec, durationSec } = revealTiming(steps.length, mode)
  const [done, setDone] = useState(() => !playReveal || mode === 'off' || skipMotion || steps.length === 0)

  const finish = useCallback(() => {
    if (done) return
    setDone(true)
    if (playReveal) markEssayRevealSeen()
    onRevealComplete?.()
  }, [done, playReveal, onRevealComplete])

  useEffect(() => {
    if (done || steps.length === 0) return
    const totalMs = (steps.length - 1) * staggerSec * 1000 + durationSec * 1000 + 120
    const timer = window.setTimeout(finish, totalMs)
    return () => window.clearTimeout(timer)
  }, [done, steps.length, staggerSec, durationSec, finish])

  if (done) {
    return (
      <EssayReadEdit
        essay={essay}
        onChange={onChange}
        disabled={disabled}
        authorName={authorName}
      />
    )
  }

  return (
    <div
      className="relative select-none"
      role="button"
      tabIndex={0}
      onClick={finish}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          finish()
        }
      }}
      aria-label="탭하면 글 전체 보기"
    >
      <p
        className="text-[10px] text-center mb-4 animate-pulse"
        style={{ color: EDU_BRAND.muted }}
      >
        탭하면 바로 보기
      </p>

      <motion.article
        className="space-y-4 pointer-events-none"
        initial="hidden"
        animate="visible"
        variants={{
          hidden: {},
          visible: {
            transition: { staggerChildren: staggerSec, delayChildren: 0.05 },
          },
        }}
      >
        {steps.map((step, i) => (
          <motion.div
            key={`${step.kind}-${i}`}
            custom={durationSec}
            variants={fadeUp}
          >
            <RevealStepView step={step} />
          </motion.div>
        ))}
      </motion.article>
    </div>
  )
}
