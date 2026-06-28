import EduCoachLevelBadge from './EduCoachLevelBadge'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import type { EduStudent, EduTierProgress } from '../../services/eduApi'

type Props = {
  student: EduStudent | null
  tier: EduTierProgress
  coachLevel: EduCoachLevelInfo
}

/** 홈 보드 상단 — 뱃지·게이지·스트릭 (프로필 히어로보다 컴팩트) */
export default function EduHomeStatusBanner({ student, tier, coachLevel }: Props) {
  const streak = tier.streak_days
  const gaugePct = tier.coach_gauge_progress_pct ?? tier.progress_pct
  const atMaxLevel = coachLevel.coach_level >= 5

  return (
    <section
      className={`rounded-2xl border-2 px-4 py-4 space-y-3 ${eduGameClasses.textKo}`}
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      aria-label="내 탐구 현황"
    >
      <div className="flex items-center gap-3">
        <EduCoachLevelBadge coachLevel={coachLevel} size="sm" className="shrink-0" />
        <div className="min-w-0 flex-1">
          <p className="font-bold truncate" style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}>
            {student?.display_name ? `${student.display_name}님` : '탐구하는 사상가'}
          </p>
          <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
            {coachLevel.label_ko}
          </p>
        </div>
        <div className="shrink-0 text-center" aria-label={`연속 탐구 ${streak}일`}>
          <span className="text-2xl leading-none block" aria-hidden>
            🔥
          </span>
          <p className="font-bold tabular-nums leading-none mt-0.5" style={{ color: eduGame.primary }}>
            {streak}
          </p>
          <p style={{ fontSize: '0.65rem', color: eduGame.muted }}>일 연속</p>
        </div>
      </div>

      {!atMaxLevel ? (
        <div>
          <div className="flex items-center justify-between gap-2 mb-1.5">
            <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
              탐구 XP
            </span>
            <span
              className="font-bold tabular-nums"
              style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primary }}
            >
              {gaugePct}%
            </span>
          </div>
          <div className="h-2.5 rounded-full overflow-hidden" style={{ backgroundColor: eduGame.surface }}>
            <div
              className="h-full rounded-full transition-all duration-700"
              style={{ width: `${gaugePct}%`, backgroundColor: eduGame.primary }}
            />
          </div>
        </div>
      ) : (
        <p
          className="text-center font-bold"
          style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primary }}
        >
          최고 레벨 · 칼럼니스트
        </p>
      )}
    </section>
  )
}
