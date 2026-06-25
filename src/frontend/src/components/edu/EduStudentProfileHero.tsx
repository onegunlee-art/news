import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduStudent, EduTierProgress } from '../../services/eduApi'

const TIER_LEVEL: Record<string, number> = {
  observer: 1,
  iron: 2,
  bronze: 3,
  silver: 4,
  gold: 5,
  platinum: 6,
  gist_challenger: 7,
}

const TIER_MEDAL: Record<string, { bg: string; ring: string }> = {
  observer: { bg: '#e8e8e8', ring: '#999999' },
  iron: { bg: '#8b9aab', ring: '#5c6b7a' },
  bronze: { bg: '#cd7f32', ring: '#9a5f24' },
  silver: { bg: '#c8cdd4', ring: '#8a9199' },
  gold: { bg: '#f5c542', ring: '#c9971a' },
  platinum: { bg: '#7ec8ff', ring: '#3d9ad4' },
  gist_challenger: { bg: '#f05123', ring: '#d9451c' },
}

type Props = {
  student: EduStudent | null
  tier: EduTierProgress
  completedCount: number
  topicsCount: number
}

/** 개인 페이지 — 스트릭 주인공, XP·티어는 보조 (eduGame 오렌지/화이트) */
export default function EduStudentProfileHero({
  student,
  tier,
  completedCount,
  topicsCount,
}: Props) {
  const medal = TIER_MEDAL[tier.tier_id] ?? TIER_MEDAL.observer
  const level = TIER_LEVEL[tier.tier_id] ?? 1
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
            Lv.{level} · {tier.tier_label_ko || tier.tier_label_en}
          </p>
        </div>
        <div
          className="w-12 h-12 rounded-full border-[3px] flex items-center justify-center shrink-0 shadow-sm"
          style={{ backgroundColor: medal.bg, borderColor: medal.ring }}
          aria-label={`${tier.tier_label_ko} 티어`}
        >
          <span className="text-lg font-bold text-white drop-shadow-sm">
            {tier.tier_label_en.charAt(0).toUpperCase()}
          </span>
        </div>
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

      {/* XP — 스트릭보다 작게 */}
      <section
        className="rounded-2xl border-2 px-4 py-4"
        style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      >
        <div className="flex items-center justify-between gap-2 mb-2">
          <span className="font-bold" style={{ fontSize: eduGame.fontSize.label, color: eduGame.ink }}>
            {tier.tier_label_en}
            {tier.tier_label_ko ? ` · ${tier.tier_label_ko}` : ''}
          </span>
          {tier.xp_next_tier != null && tier.next_tier_label_en && (
            <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
              → {tier.next_tier_label_en}
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
            {tier.xp_current.toLocaleString()} XP · 최고 등급
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
