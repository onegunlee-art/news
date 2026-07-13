import { Link } from 'react-router-dom'
import type { EduThoughtBoardSlot } from '../../../services/eduApi'
import type { EssayArtifact } from '../EssayRevealCard'
import { eduPc } from '../../../constants/eduPcRedesignTheme'
import EduQuestComboContinue from '../EduQuestComboContinue'
import EduQuestCompletionCelebration from '../EduQuestCompletionCelebration'
import type { EduCoachLevelInfo } from '../../../constants/eduCoachLevel'
import type { EduLevelUpPayload, EduQuest, EduTierProgress, EduXpBreakdownLine } from '../../../services/eduApi'

type Props = {
  essay: EssayArtifact
  board: EduThoughtBoardSlot[]
  quest: EduQuest | null
  onChange: (essay: EssayArtifact) => void
  onPersist: () => void
  saveStatus: 'idle' | 'saving' | 'saved' | 'error'
  xpGained: number
  xpBreakdown: EduXpBreakdownLine[]
  levelUp: EduLevelUpPayload | null
  tier: EduTierProgress | null
  coachLevel: EduCoachLevelInfo
  todayComboCount: number
}

export default function EduPcComposeScreen({
  essay,
  board,
  quest,
  onChange,
  onPersist,
  saveStatus,
  xpGained,
  xpBreakdown,
  levelUp,
  tier,
  coachLevel,
  todayComboCount,
}: Props) {
  return (
    <div
      className="flex flex-col flex-1 min-h-0 overflow-hidden"
      style={{ fontFamily: eduPc.fontBody }}
    >
      <EduQuestCompletionCelebration
        xpGained={xpGained}
        xpBreakdown={xpBreakdown}
        levelUp={levelUp}
        streakDays={tier?.streak_days ?? 0}
        coachLevel={coachLevel}
        tier={tier}
        active
      />
      <div className="flex flex-1 min-h-0 overflow-hidden">
        <div
          className="flex-1 min-w-0 flex flex-col border-r p-5 overflow-hidden"
          style={{ borderColor: eduPc.border }}
        >
          <h2
            className="text-lg font-bold mb-3 shrink-0"
            style={{ fontFamily: eduPc.fontHeadline, color: eduPc.ink }}
          >
            나만의 글
          </h2>
          <textarea
            value={essay.full_text ?? ''}
            onChange={e => onChange({ ...essay, full_text: e.target.value })}
            className="flex-1 min-h-0 w-full resize-none rounded-[13px] px-4 py-3 text-sm leading-relaxed focus:outline-none focus:ring-2"
            style={{
              backgroundColor: eduPc.cardBg,
              border: `1px solid ${eduPc.border}`,
              color: eduPc.ink,
              fontFamily: eduPc.fontHeadline,
            }}
            aria-label="완성 글 편집"
          />
          <div className="shrink-0 flex items-center gap-3 mt-4">
            <Link
              to="/edu"
              className="px-5 py-2.5 rounded-[11px] text-sm font-bold border no-underline transition-colors hover:border-[#E85D2C]"
              style={{ borderColor: eduPc.border, color: eduPc.inkMuted }}
            >
              처음부터
            </Link>
            <button
              type="button"
              onClick={onPersist}
              className="px-5 py-2.5 rounded-[11px] text-sm font-bold text-white"
              style={{ backgroundColor: eduPc.orange }}
            >
              글 제출하기
            </button>
            <span className="text-xs" style={{ color: eduPc.inkDim }}>
              {saveStatus === 'saving' && '저장 중…'}
              {saveStatus === 'saved' && '✓ 저장됨'}
              {saveStatus === 'error' && '저장 실패'}
            </span>
          </div>
        </div>
        <aside
          className="shrink-0 overflow-y-auto p-4 space-y-3"
          style={{ width: eduPc.boardWidth, borderColor: eduPc.border }}
        >
          <p className="text-sm font-bold" style={{ color: eduPc.ink, fontFamily: eduPc.fontHeadline }}>
            이 글의 출처 — 내 생각판
          </p>
          <p className="text-xs leading-relaxed" style={{ color: eduPc.inkMuted }}>
            AI가 대신 쓴 글이 아닙니다. 대화 속에서 직접 채운 생각이 글의 뼈대입니다.
          </p>
          {board.map(slot => (
            <div
              key={slot.layer_id}
              className="rounded-[11px] p-3"
              style={{
                border: slot.filled ? `1px solid ${eduPc.orange}` : `1px dashed ${eduPc.borderDashed}`,
                background: slot.filled ? eduPc.cardFilledGradient : eduPc.cardBg,
              }}
            >
              <p className="text-[11px] font-bold mb-1" style={{ color: eduPc.orange }}>
                {slot.index}. {slot.label}
              </p>
              <p className="text-xs leading-relaxed" style={{ color: slot.filled ? eduPc.ink : eduPc.inkDim }}>
                {slot.filled ? slot.text : '—'}
              </p>
            </div>
          ))}
        </aside>
      </div>
      {quest?.quest_id && (
        <div className="shrink-0 px-5 py-3 border-t" style={{ borderColor: eduPc.border }}>
          <EduQuestComboContinue
            currentQuestId={quest.quest_id}
            diversity={{ questFrame: quest.quest_frame ?? null }}
            comboCount={todayComboCount}
            uiMode="cards"
          />
        </div>
      )}
    </div>
  )
}
