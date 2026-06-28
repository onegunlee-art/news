import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import { eduCoachLevelByNumber } from '../../constants/eduCoachLevel'
import type { EduTierProgress } from '../../services/eduApi'

interface Props {
  tier: EduTierProgress
  coachLevel?: EduCoachLevelInfo | null
  onStartQuest?: () => void
  loading?: boolean
}

/** B-2 — 홈 compact: 코치 레벨 게이지 + 스트릭 (7단 메달 UI 제거) */
export default function TierProgressCard({ tier, coachLevel, onStartQuest, loading }: Props) {
  const currentLevelNum =
    coachLevel?.coach_level ??
    (tier.next_coach_level != null ? tier.next_coach_level - 1 : 5)
  const level = coachLevel ?? eduCoachLevelByNumber(currentLevelNum)

  const gaugePct = tier.coach_gauge_progress_pct ?? tier.progress_pct
  const gaugeXp = tier.coach_gauge_xp ?? tier.xp_current
  const gaugeTarget = tier.coach_gauge_target ?? tier.xp_next_tier ?? 100
  const nextLabel = tier.next_coach_label_ko
  const gateLabel = tier.coach_gauge_gate_ko
  const gaugeFull = tier.coach_gauge_full === true
  const atMaxLevel = level.coach_level >= 5

  return (
    <div className="border border-[#1a1a1a] rounded-lg p-4 bg-white text-[#1a1a1a]">
      <div className="flex items-center gap-3 mb-3">
        <div className="w-10 h-10 rounded-full border-2 border-[#E8521C] flex items-center justify-center font-bold text-sm shrink-0">
          L{level.coach_level}
        </div>
        <div className="min-w-0 flex-1">
          <div className="font-semibold">{level.label_ko}</div>
          {!atMaxLevel ? (
            <div className="text-xs text-[#666]">
              {gaugePct}% · {gaugeXp}/{gaugeTarget} XP
              {nextLabel ? ` → ${nextLabel}` : ''}
            </div>
          ) : (
            <div className="text-xs text-[#666]">최고 레벨 · 칼럼니스트</div>
          )}
        </div>
        <div className="ml-auto text-sm border border-[#1a1a1a] px-2 py-1 rounded shrink-0">
          🔥 {tier.streak_days}일
        </div>
      </div>

      {!atMaxLevel && (
        <div className="mb-3">
          <div className="h-2 bg-[#f0f0f0] rounded overflow-hidden">
            <div
              className="h-full bg-[#E8521C] transition-all duration-700"
              style={{ width: `${gaugePct}%` }}
            />
          </div>
          {gateLabel && (
            <p className="text-xs text-[#666] mt-1.5">질 관문: {gateLabel}</p>
          )}
          {gaugeFull && (
            <p className="text-xs font-bold text-[#E8521C] mt-1">곧 레벨업!</p>
          )}
        </div>
      )}

      {tier.show_quest_cta && onStartQuest && (
        <button
          type="button"
          onClick={onStartQuest}
          disabled={loading}
          className="w-full py-3 bg-[#1a1a1a] text-white font-medium rounded disabled:opacity-50"
        >
          {loading ? '불러오는 중…' : '오늘의 퀘스트 → 시작'}
        </button>
      )}
    </div>
  )
}
