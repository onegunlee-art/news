import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import type { EduStudent, EduTierProgress } from '../../services/eduApi'
import EduCoachLevelBadge from './EduCoachLevelBadge'

type Props = {
  student: EduStudent | null
  tier: EduTierProgress
  coachLevel: EduCoachLevelInfo
  levelDebugSwitch?: boolean
  onCoachLevelChange?: (level: number) => void | Promise<void>
  completedCount: number
  topicsCount: number
}

/** 개인 페이지 — 스트릭 주인공, 코치 레벨 뱃지 + XP 보조 (eduGame 오렌지/화이트) */
export default function EduStudentProfileHero({
  student,
  tier,
  coachLevel,
  levelDebugSwitch = false,
  onCoachLevelChange,
  completedCount,
  topicsCount,
}: Props) {
  const streak = tier.streak_days
  const streakLabel =
    streak > 1 ? `연속 탐구 ${streak}일` : streak === 1 ? '연속 탐구 1일' : '오늘 탐구하면 불꽃이 켜져요'

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

      {/* 스트릭 — 화면 주인공 */}
      <section
        className="rounded-2xl border-2 px-4 py-7 text-center shadow-sm"
        style={{ borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }}
        aria-label="연속 탐구 스트릭"
      >
        <span className="edu-game-streak-live text-7xl leading-none select-none block" aria-hidden>
          🔥
        </span>
        <p
          className="font-bold tabular-nums leading-none mt-2"
          style={{ fontSize: '3.5rem', color: eduGame.primary }}
        >
          {streak}
        </p>
        <p className="font-bold mt-1" style={{ fontSize: '1.125rem', color: eduGame.primaryDark }}>
          {streakLabel}
        </p>
        <p className="mt-1" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
          꾸준함이 최대 보상
        </p>
      </section>

      {/* XP — B-2 전까지 숫자·바만 (메인 레벨 아님) */}
      <section
        className="rounded-2xl border-2 px-4 py-4"
        style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      >
        <div className="flex items-center justify-between gap-2 mb-2">
          <span className="font-bold" style={{ fontSize: eduGame.fontSize.label, color: eduGame.ink }}>
            탐구 XP
          </span>
          {tier.xp_next_tier != null && (
            <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
              다음 구간까지
            </span>
          )}
        </div>
        {tier.xp_next_tier != null ? (
          <>
            <div className="h-3 rounded-full overflow-hidden" style={{ backgroundColor: eduGame.surface }}>
              <div
                className="h-full rounded-full transition-all duration-700"
                style={{ width: `${tier.progress_pct}%`, backgroundColor: eduGame.primary }}
              />
            </div>
            <p className="mt-2 text-center tabular-nums" style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>
              {tier.xp_current.toLocaleString()} / {tier.xp_next_tier.toLocaleString()} XP
            </p>
          </>
        ) : (
          <p className="text-center tabular-nums" style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>
            {tier.xp_current.toLocaleString()} XP
          </p>
        )}
      </section>

      {/* 통계 */}
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
