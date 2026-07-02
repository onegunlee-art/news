import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { eduQuestFlowPath } from '../../constants/eduNarrativeBridge'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { eduApi, type EduQuestListItem } from '../../services/eduApi'
import {
  eduComboDisplayLabel,
  pickNextQuestRecommendation,
} from '../../utils/eduQuestCombo'
import EduQuestCoverHero from './EduQuestCoverHero'

type Props = {
  currentQuestId: string
  diversity?: {
    shelf?: string | null
    category?: string | null
    questFrame?: string | null
  }
  comboCount: number
  /** cards | chat — 다음 퀘스트 UI 모드 유지 */
  uiMode?: 'cards' | 'chat'
}

/**
 * 완주 후 콤보 — "한 편 더?" + 추천 1개 + 홈/종료 (게이지 보너스 없음)
 */
export default function EduQuestComboContinue({
  currentQuestId,
  diversity,
  comboCount,
  uiMode = 'cards',
}: Props) {
  const [nextQuest, setNextQuest] = useState<EduQuestListItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [starting, setStarting] = useState(false)
  const [dismissed, setDismissed] = useState(false)
  const [error, setError] = useState('')

  const loadNext = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.listQuests({ limit: 50, frame: 'all' })
      const pick = pickNextQuestRecommendation(res.quests, currentQuestId, diversity)
      setNextQuest(pick)
    } catch (e) {
      setError(e instanceof Error ? e.message : '추천 불러오기 실패')
      setNextQuest(null)
    } finally {
      setLoading(false)
    }
  }, [currentQuestId, diversity])

  useEffect(() => {
    void loadNext()
  }, [loadNext])

  const handleStartNext = async () => {
    if (!nextQuest?.quest_id) return
    setStarting(true)
    setError('')
    try {
      await eduApi.startSession(nextQuest.quest_id)
      window.location.href = eduQuestFlowPath({
        questId: nextQuest.quest_id,
        coachMode: nextQuest.coach_mode,
        questCode: nextQuest.quest_code,
        ui: uiMode,
      })
    } catch (e) {
      setError(e instanceof Error ? e.message : '시작 실패')
      setStarting(false)
    }
  }

  if (dismissed) {
    return (
      <p className="text-center py-2" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
        오늘도 잘 따졌어요. 내일 또 만나요!
      </p>
    )
  }

  const comboLabel = eduComboDisplayLabel(comboCount)

  return (
    <section
      className={`rounded-2xl border-2 p-4 space-y-4 ${eduGameClasses.textKo}`}
      style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
      aria-label="한 편 더 따지기"
    >
      {comboLabel && (
        <p
          className="font-bold text-center"
          style={{ fontSize: eduGame.fontSize.body, color: eduGame.primary }}
        >
          {comboLabel}
        </p>
      )}

      <div className="text-center space-y-1">
        <p className="font-bold" style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}>
          🔥 한 편 더 따질래?
        </p>
        <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
          권유일 뿐이에요 — 안 해도 괜찮아요
        </p>
      </div>

      {loading ? (
        <p className="text-center py-4" style={{ color: eduGame.muted, fontSize: eduGame.fontSize.caption }}>
          다음 글 고르는 중…
        </p>
      ) : nextQuest ? (
        <div
          className="rounded-xl border-2 overflow-hidden"
          style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
        >
          <EduQuestCoverHero
            coverImageUrl={nextQuest.cover_image_url}
            questTitle={nextQuest.quest_title}
            hookShort={nextQuest.hook_short}
            timeAnchor={nextQuest.time_anchor}
            variant="card"
            topicLabel="다음 추천"
          />
          <div className="p-3 space-y-1">
            <p className="font-bold leading-snug" style={{ fontSize: eduGame.fontSize.label, color: eduGame.ink }}>
              {nextQuest.quest_title}
            </p>
            {(nextQuest.hook_short || nextQuest.lens_label) && (
              <p
                className="line-clamp-2"
                style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}
              >
                {nextQuest.hook_short?.trim() || nextQuest.lens_label}
              </p>
            )}
          </div>
        </div>
      ) : (
        <p
          className="text-center py-2"
          style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}
        >
          지금은 다른 열린 글이 없어요. 홈에서 골라보세요.
        </p>
      )}

      <div className="space-y-2">
        {nextQuest && (
          <button
            type="button"
            onClick={() => void handleStartNext()}
            disabled={starting}
            className={`w-full py-3.5 ${eduGameClasses.btnPrimary} touch-manipulation`}
            style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
          >
            {starting ? '시작하는 중…' : '이어서 시작하기'}
          </button>
        )}
        <Link
          to="/edu"
          className={`block w-full py-3.5 text-center rounded-xl font-bold border-2 touch-manipulation ${eduGameClasses.textKo}`}
          style={{ borderColor: eduGame.primary, color: eduGame.primary, fontSize: eduGame.fontSize.button }}
        >
          홈에서 고르기
        </Link>
        <button
          type="button"
          onClick={() => setDismissed(true)}
          className={`w-full py-2.5 text-center underline touch-manipulation ${eduGameClasses.textKo}`}
          style={{ color: eduGame.muted, fontSize: eduGame.fontSize.label }}
        >
          오늘은 여기까지
        </button>
      </div>

      {error && (
        <p className="text-sm text-center" style={{ color: '#b71c1c' }}>
          {error}
        </p>
      )}
    </section>
  )
}
