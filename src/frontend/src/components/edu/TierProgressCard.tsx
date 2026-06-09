import type { EduTierProgress } from '../../services/eduApi'

interface Props {
  tier: EduTierProgress
  onStartQuest?: () => void
  loading?: boolean
}

export default function TierProgressCard({ tier, onStartQuest, loading }: Props) {
  const initial = tier.tier_label_en.charAt(0).toUpperCase()
  const ko = tier.tier_label_ko ? ` (${tier.tier_label_ko})` : ''

  return (
    <div className="border border-[#1a1a1a] rounded-lg p-4 bg-white text-[#1a1a1a]">
      <div className="flex items-center gap-3 mb-3">
        <div className="w-10 h-10 rounded-full border-2 border-[#1a1a1a] flex items-center justify-center font-bold text-lg">
          {initial}
        </div>
        <div>
          <div className="font-semibold">
            {tier.tier_label_en}
            {ko}
          </div>
          {tier.status === 'dormant' && (
            <div className="text-xs text-[#666]">상태: Dormant</div>
          )}
          {tier.xp_next_tier != null ? (
            <div className="text-xs text-[#666]">
              {tier.xp_current.toLocaleString()} / {tier.xp_next_tier.toLocaleString()} XP
            </div>
          ) : (
            <div className="text-xs text-[#666]">{tier.xp_current.toLocaleString()} XP · 최고 등급</div>
          )}
        </div>
        <div className="ml-auto text-sm border border-[#1a1a1a] px-2 py-1 rounded">
          {tier.streak_days}일 연속
        </div>
      </div>

      {tier.xp_next_tier != null && (
        <div className="mb-3">
          <div className="flex justify-between text-xs text-[#666] mb-1">
            <span>{tier.tier_label_en}</span>
            <span>{tier.next_tier_label_en}</span>
          </div>
          <div className="h-2 bg-[#f0f0f0] rounded overflow-hidden">
            <div
              className="h-full bg-[#1a1a1a] transition-all"
              style={{ width: `${tier.progress_pct}%` }}
            />
          </div>
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
