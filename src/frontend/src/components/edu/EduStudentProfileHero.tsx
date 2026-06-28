import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import type { EduStudent, EduTierProgress } from '../../services/eduApi'
import EduCoachLevelBadge from './EduCoachLevelBadge'
import EduGamingStreakFlame from './EduGamingStreakFlame'

type Props = {
  student: EduStudent | null
  tier: EduTierProgress
  coachLevel: EduCoachLevelInfo
  /** profile=프로필 전체, homeBoard=홈 상단 컴팩트 */
  layout?: 'profile' | 'homeBoard'
  levelDebugSwitch?: boolean
  onCoachLevelChange?: (level: number) => void | Promise<void>
  completedCount?: number
  topicsCount?: number
}

/** B-2 — 스트릭 + 코치 레벨 뱃지 + 진척 게이지 (프로필·홈 보드 공용) */
export default function EduStudentProfileHero({
  student,
  tier,
  coachLevel,
  layout = 'profile',
  levelDebugSwitch = false,
  onCoachLevelChange,
  completedCount = 0,
  topicsCount = 0,
}: Props) {
  const streak = tier.streak_days
  const streakLabel =
    streak > 1 ? `연속 탐구 ${streak}일` : streak === 1 ? '연속 탐구 1일' : '오늘 탐구하면 불꽃이 켜져요'

  const gaugePct = tier.coach_gauge_progress_pct ?? tier.progress_pct
  const gaugeXp = tier.coach_gauge_xp ?? tier.xp_current
  const gaugeTarget = tier.coach_gauge_target ?? tier.xp_next_tier ?? 100
  const nextLabel = tier.next_coach_label_ko
  const gateLabel = tier.coach_gauge_gate_ko
  const gaugeFull = tier.coach_gauge_full === true
  const atMaxLevel = coachLevel.coach_level >= 5

  if (layout === 'homeBoard') {
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
          <div className="shrink-0 text-center" aria-label={streakLabel}>
            <EduGamingStreakFlame streakDays={streak} variant="compact" showCount />
          </div>
        </div>

        {!atMaxLevel ? (
          <div aria-label="코치 레벨 진척 게이지">
            <div className="flex items-center justify-between gap-2 mb-1.5">
              <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                {coachLevel.label_ko} · 탐구 XP
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

  return (
    <div className={`space-y-4 ${eduGameClasses.textKo}`}>
      <div className="flex items-center gap-3">
        {student?.profile_image ? (
          <img
            src={student.profile_image}
            alt=""
            className="w-14 h-14 rounded-full object-cover border-2 shrink-0"
            style={{ borderColor: eduGame.border }}
          />
        ) : (
          <div
            className="w-14 h-14 rounded-full flex items-center justify-center text-xl font-bold shrink-0 border-2"
            style={{ borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight, color: eduGame.primary }}
          >
            {(student?.display_name || '?').slice(0, 1)}
          </div>
        )}
        <div className="min-w-0 flex-1">
          <h1 className="font-bold truncate" style={{ fontSize: '1.25rem', color: eduGame.ink }}>
            {student?.display_name || '학생'}
          </h1>
          <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
            탐구하는 사상가
          </p>
        </div>
        <EduCoachLevelBadge
          coachLevel={coachLevel}
          size="md"
          debugSwitchEnabled={levelDebugSwitch}
          onSelectLevel={onCoachLevelChange}
          className="shrink-0"
        />
      </div>

      <section
        className="rounded-2xl border-2 px-4 py-6 text-center shadow-sm"
        style={{ borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }}
        aria-label="연속 탐구 스트릭"
      >
        <div className="flex justify-center mb-2">
          <EduGamingStreakFlame streakDays={streak} variant="hero" showCount />
        </div>
        <p className="font-bold mt-1" style={{ fontSize: '1.125rem', color: eduGame.primaryDark }}>
          {streakLabel}
        </p>
        <p className="mt-1" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
          꾸준함이 최대 보상
        </p>
      </section>

      <section
        className="rounded-2xl border-2 px-4 py-4"
        style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
        aria-label="코치 레벨 진척 게이지"
      >
        <div className="flex items-center justify-between gap-2 mb-2">
          <span className="font-bold" style={{ fontSize: eduGame.fontSize.label, color: eduGame.ink }}>
            {coachLevel.label_ko} · 탐구 XP
          </span>
          {!atMaxLevel && nextLabel && (
            <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
              다음 {nextLabel}
            </span>
          )}
        </div>
        {!atMaxLevel ? (
          <>
            <div className="h-3 rounded-full overflow-hidden" style={{ backgroundColor: eduGame.surface }}>
              <div
                className="h-full rounded-full transition-all duration-700"
                style={{ width: `${gaugePct}%`, backgroundColor: eduGame.primary }}
              />
            </div>
            <p className="mt-2 text-center tabular-nums" style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>
              {gaugePct}% · {gaugeXp} / {gaugeTarget} XP
            </p>
            {gateLabel && (
              <p className="mt-1 text-center" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                질 관문: {gateLabel}
              </p>
            )}
            {gaugeFull && (
              <p
                className="mt-2 text-center font-bold edu-game-xp-in"
                style={{ fontSize: eduGame.fontSize.label, color: eduGame.primary }}
              >
                곧 레벨업! (다음 단계에서 올라가요)
              </p>
            )}
          </>
        ) : (
          <p className="text-center font-bold" style={{ fontSize: eduGame.fontSize.label, color: eduGame.primary }}>
            최고 레벨 · 칼럼니스트
          </p>
        )}
      </section>

      <div className="grid grid-cols-2 gap-3">
        <div
          className="rounded-xl border-2 px-3 py-3 text-center"
          style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
        >
          <p className="font-bold tabular-nums" style={{ fontSize: '1.5rem', color: eduGame.primary }}>
            {completedCount}
          </p>
          <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>완주한 퀘스트</p>
        </div>
        <div
          className="rounded-xl border-2 px-3 py-3 text-center"
          style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
        >
          <p className="font-bold tabular-nums" style={{ fontSize: '1.5rem', color: eduGame.primary }}>
            {topicsCount}
          </p>
          <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>따진 주제</p>
        </div>
      </div>
    </div>
  )
}
