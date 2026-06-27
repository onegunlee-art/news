import { useEffect, useState } from 'react'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import { eduCoachLevelByNumber } from '../../constants/eduCoachLevel'
import type { EduTierProgress, EduXpBreakdownLine } from '../../services/eduApi'
import EduCoachLevelBadge from './EduCoachLevelBadge'

function useCountUp(target: number, durationMs = 1200, active = true) {
  const [value, setValue] = useState(0)

  useEffect(() => {
    if (!active) return
    if (target <= 0) {
      setValue(0)
      return
    }
    setValue(0)
    const start = performance.now()
    let raf = 0
    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / durationMs)
      setValue(Math.round(target * progress))
      if (progress < 1) raf = requestAnimationFrame(tick)
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [target, durationMs, active])

  return value
}

type Props = {
  xpGained: number
  xpBreakdown?: EduXpBreakdownLine[]
  gateHit?: boolean
  gateLabelKo?: string | null
  streakDays: number
  coachLevel?: EduCoachLevelInfo | null
  levelDebugSwitch?: boolean
  onCoachLevelChange?: (level: number) => void | Promise<void>
  tier?: EduTierProgress | null
  active?: boolean
}

/** B-2 완주 — 스트릭 + 코치 뱃지 + 질 기반 XP·게이지 */
export default function EduQuestCompletionCelebration({
  xpGained,
  xpBreakdown = [],
  gateHit,
  gateLabelKo,
  streakDays,
  coachLevel,
  levelDebugSwitch = false,
  onCoachLevelChange,
  tier,
  active = true,
}: Props) {
  const xpDisplay = useCountUp(xpGained, 1200, active)
  const level = coachLevel ?? eduCoachLevelByNumber(1)
  const gaugePct = tier?.coach_gauge_progress_pct ?? tier?.progress_pct ?? 0
  const gaugeXp = tier?.coach_gauge_xp ?? tier?.xp_current ?? 0
  const gaugeTarget = tier?.coach_gauge_target ?? tier?.xp_next_tier ?? 100
  const gaugeFull = tier?.coach_gauge_full === true
  const nextLabel = tier?.next_coach_label_ko

  return (
    <section
      className={`rounded-2xl border-2 p-5 ${eduGameClasses.textKo} edu-game-complete-in`}
      style={{ borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }}
      aria-label="탐구 완료"
    >
      <p
        className="font-bold text-center mb-3"
        style={{ color: eduGame.primaryDark, fontSize: '1.375rem', lineHeight: 1.3 }}
      >
        탐구 완료!
      </p>

      <div className="flex justify-center mb-4">
        <EduCoachLevelBadge
          coachLevel={level}
          size="lg"
          debugSwitchEnabled={levelDebugSwitch}
          onSelectLevel={onCoachLevelChange}
        />
      </div>

      <div className="flex flex-col items-center gap-0.5 mb-5">
        <span className="edu-game-streak-pop text-5xl leading-none select-none" aria-hidden>
          🔥
        </span>
        <p
          className="font-bold tabular-nums leading-none mt-1"
          style={{ fontSize: '2.25rem', color: eduGame.primary }}
        >
          {streakDays}
        </p>
        <p className="font-bold" style={{ fontSize: eduGame.fontSize.body, color: eduGame.primaryDark }}>
          {streakDays > 1 ? `연속 탐구 ${streakDays}일` : streakDays === 1 ? '연속 탐구 1일' : '오늘의 탐구를 마쳤어!'}
        </p>
        <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
          꾸준히 따지면 불꽃이 커져
        </p>
      </div>

      {xpGained > 0 && (
        <div
          className="edu-game-xp-in text-center py-4 px-3 rounded-xl border-2"
          style={{ backgroundColor: eduGame.bg, borderColor: eduGame.primaryLight }}
        >
          <p style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>이번 탐구 XP</p>
          <p
            className="font-bold tabular-nums mt-1"
            style={{ fontSize: '1.75rem', color: eduGame.primary, lineHeight: 1.2 }}
          >
            +{xpDisplay}
          </p>
          {gateLabelKo && (
            <p
              className="mt-1 font-bold"
              style={{
                fontSize: eduGame.fontSize.caption,
                color: gateHit ? eduGame.primary : eduGame.muted,
              }}
            >
              {gateHit ? `${gateLabelKo} ✓ — 게이지 빨리 참` : `${gateLabelKo} — 다음엔 더 깊게!`}
            </p>
          )}
          {xpBreakdown.length > 1 && (
            <ul className="mt-2 space-y-0.5 text-left max-w-xs mx-auto">
              {xpBreakdown.map((line) => (
                <li
                  key={`${line.label}-${line.xp}`}
                  className="flex justify-between tabular-nums"
                  style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}
                >
                  <span>{line.label}</span>
                  <span>+{line.xp}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {tier && level.coach_level < 5 && tier.xp_next_tier != null && (
        <div className="mt-4 pt-3 border-t-2" style={{ borderColor: eduGame.bg }}>
          <div
            className="flex justify-between mb-1.5"
            style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}
          >
            <span>{level.label_ko} 게이지</span>
            <span>
              {gaugePct}% · {gaugeXp}/{gaugeTarget}
              {nextLabel ? ` → ${nextLabel}` : ''}
            </span>
          </div>
          <div className="h-2.5 rounded-full overflow-hidden" style={{ backgroundColor: eduGame.bg }}>
            <div
              className="h-full rounded-full transition-all duration-700"
              style={{ width: `${gaugePct}%`, backgroundColor: eduGame.primary }}
            />
          </div>
          {gaugeFull && (
            <p
              className="mt-2 text-center font-bold"
              style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primary }}
            >
              곧 레벨업!
            </p>
          )}
        </div>
      )}
    </section>
  )
}
