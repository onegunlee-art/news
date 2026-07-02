import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import CoachMessageText from '../../components/edu/CoachMessageText'
import EduCoachWaitingPanel from '../../components/edu/EduCoachWaitingPanel'
import EduEssayCompletionPanel from '../../components/edu/EduEssayCompletionPanel'
import { type EssayArtifact } from '../../components/edu/EssayRevealCard'
import EduQuestCompletionCelebration from '../../components/edu/EduQuestCompletionCelebration'
import EduQuestComboContinue from '../../components/edu/EduQuestComboContinue'
import EduQuestHomeButton from '../../components/edu/EduQuestHomeButton'
import { NARRATIVE_BRIDGE_STEP_COUNT } from '../../constants/eduNarrativeBridge'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { eduCoachLevelByNumber, type EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import {
  eduApi,
  getEduDisplayName,
  type EduChatResponse,
  type EduDialogueTurn,
  type EduLevelUpPayload,
  type EduQuest,
  type EduTierProgress,
  type EduXpBreakdownLine,
} from '../../services/eduApi'
import { getTodayComboCount, recordTodayQuestCompletion } from '../../utils/eduQuestCombo'
import { shouldTriggerEduCompose } from '../../utils/eduComposeTrigger'
import { splitCoachParagraphs } from '../../utils/eduCoachMessageParse'

const PAGE_MAX = 'max-w-2xl'

type NarrativeChoice = { id: string; label: string }

function resolveNarrativeChoices(
  res: Pick<EduChatResponse, 'choice_question' | 'options' | 'narrative_choices'>
): NarrativeChoice[] {
  if (Array.isArray(res.narrative_choices) && res.narrative_choices.length > 0) {
    return res.narrative_choices
  }
  if (res.choice_question && Array.isArray(res.options) && res.options.length > 0) {
    return res.options.map((label, i) => ({ id: `opt_${i}`, label }))
  }
  return []
}

function NarrativeProgressBar({ step }: { step: number }) {
  const active = Math.min(NARRATIVE_BRIDGE_STEP_COUNT - 1, Math.max(0, step))
  return (
    <div className="flex gap-1.5 px-1" aria-label={`대화 진행 ${active + 1}/${NARRATIVE_BRIDGE_STEP_COUNT}`}>
      {Array.from({ length: NARRATIVE_BRIDGE_STEP_COUNT }, (_, i) => (
        <div
          key={i}
          className="h-1.5 flex-1 rounded-full transition-colors duration-300"
          style={{ backgroundColor: i <= active ? eduGame.primary : eduGame.border }}
        />
      ))}
    </div>
  )
}

export default function QuestFlowNarrativeBridge() {
  const [searchParams] = useSearchParams()
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [choices, setChoices] = useState<NarrativeChoice[]>([])
  const [narrativeStep, setNarrativeStep] = useState(0)
  const [progressPct, setProgressPct] = useState(0)
  const [phase, setPhase] = useState('narrative_bridge')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [composing, setComposing] = useState(false)
  const [completed, setCompleted] = useState(false)
  const [error, setError] = useState('')
  const [essay, setEssay] = useState<EssayArtifact | null>(null)
  const [xpGained, setXpGained] = useState(0)
  const [xpBreakdown, setXpBreakdown] = useState<EduXpBreakdownLine[]>([])
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [coachLevel, setCoachLevel] = useState<EduCoachLevelInfo>(() => eduCoachLevelByNumber(1))
  const [levelUp, setLevelUp] = useState<EduLevelUpPayload | null>(null)
  const [todayComboCount, setTodayComboCount] = useState(0)
  const [playEssayReveal, setPlayEssayReveal] = useState(false)
  const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const composeStartedRef = useRef(false)
  const initCalledRef = useRef(false)
  const comboRecordedRef = useRef(false)
  const scrollRef = useRef<HTMLDivElement>(null)

  const applyResponse = useCallback((res: EduChatResponse) => {
    if (res.phase) setPhase(res.phase)
    if (res.progress_pct != null) setProgressPct(res.progress_pct)
    if (res.narrative_step != null) setNarrativeStep(res.narrative_step)
    setChoices(resolveNarrativeChoices(res))
  }, [])

  const syncSessionState = useCallback(async (sid: string) => {
    const state = await eduApi.getSessionState(sid)
    setQuest(state.quest)
    setDialogue(state.dialogue ?? [])
    setProgressPct(state.progress_pct)
    setPhase(state.blueprint?.phase ?? 'narrative_bridge')
    setNarrativeStep(Number(state.blueprint?.narrative_step ?? state.narrative_step ?? 0))
    setChoices(resolveNarrativeChoices(state))
    return state
  }, [])

  const handleCompose = useCallback(async (sid: string) => {
    setComposing(true)
    setError('')
    try {
      const res = await eduApi.composeEssay(sid)
      setCompleted(true)
      if (!comboRecordedRef.current) {
        comboRecordedRef.current = true
        setTodayComboCount(recordTodayQuestCompletion())
      }
      setEssay({
        title: res.title,
        subtitle: res.subtitle,
        sections: res.sections,
        conclusion_heading: res.conclusion_heading,
        conclusion_paragraphs: res.conclusion_paragraphs,
        body_paragraphs: res.body_paragraphs,
        narration_mode: res.narration_mode,
        full_text: res.full_text ?? '',
        hero_sentence: res.hero_sentence ?? null,
        feedback: res.feedback ?? null,
      })
      setXpGained(res.xp_gained ?? 0)
      setXpBreakdown(res.xp_breakdown ?? [])
      if (res.tier) setTier(res.tier)
      if (res.coach_level) setCoachLevel(res.coach_level)
      if (res.level_up) setLevelUp(res.level_up)
      setProgressPct(100)
      setSaveStatus(res.saved ? 'saved' : 'idle')
      setPlayEssayReveal(true)
    } catch (e) {
      composeStartedRef.current = false
      setError(e instanceof Error ? e.message : '글 생성 실패')
    } finally {
      setComposing(false)
    }
  }, [])

  const handleChatResponse = useCallback(
    async (res: EduChatResponse, sid: string) => {
      applyResponse(res)
      if (shouldTriggerEduCompose(res)) {
        composeStartedRef.current = true
        await handleCompose(sid)
      }
      await syncSessionState(sid)
    },
    [applyResponse, handleCompose, syncSessionState]
  )

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      setLoading(true)
      setError('')
      try {
        let sid = ''
        if (questIdParam) {
          const started = await eduApi.startSession(questIdParam)
          sid = started.session_id
        } else {
          const today = await eduApi.todayQuest()
          if (!today.quest) {
            setError('오늘의 퀘스트가 없습니다.')
            return
          }
          const existing = today.active_session || today.existing_session
          sid = existing?.session_id ?? ''
          if (!sid) {
            const started = await eduApi.startSession(today.quest.quest_id)
            sid = started.session_id
          }
        }
        if (cancelled) return
        setSessionId(sid)

        const state = await syncSessionState(sid)
        if (state.stage === 'completed' && state.essay) {
          setCompleted(true)
          comboRecordedRef.current = true
          setTodayComboCount(getTodayComboCount())
          setEssay({
            title: state.essay.title,
            subtitle: state.essay.subtitle,
            sections: state.essay.sections,
            conclusion_heading: state.essay.conclusion_heading,
            conclusion_paragraphs: state.essay.conclusion_paragraphs,
            body_paragraphs: state.essay.body_paragraphs,
            narration_mode: state.essay.narration_mode,
            full_text: state.essay.full_text,
            hero_sentence: state.essay.hero_sentence,
            feedback: state.essay.feedback,
          })
          setProgressPct(100)
          return
        }

        if (!initCalledRef.current && (state.dialogue?.length ?? 0) === 0) {
          initCalledRef.current = true
          const res = await eduApi.sendChat(sid, { action: 'narrative_init' })
          if (!cancelled) await handleChatResponse(res, sid)
        }
      } catch (e) {
        if (!cancelled) setError(e instanceof Error ? e.message : '세션을 불러오지 못했어요')
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [questIdParam, syncSessionState, handleChatResponse])

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' })
  }, [dialogue, choices, composing])

  useEffect(() => {
    if (!sessionId || loading || completed || composing || composeStartedRef.current) return
    if (phase !== 'compose') return
    composeStartedRef.current = true
    void handleCompose(sessionId)
  }, [sessionId, loading, completed, composing, phase, handleCompose])

  const handleChoice = async (choice: NarrativeChoice) => {
    if (!sessionId || sending || completed || composing) return
    setSending(true)
    setError('')
    setChoices([])
    try {
      const res = await eduApi.sendChat(sessionId, {
        action: 'narrative_choice',
        choice_id: choice.id,
      })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '선택을 보내지 못했어요')
      await syncSessionState(sessionId)
    } finally {
      setSending(false)
    }
  }

  const displayName = getEduDisplayName()

  if (loading) {
    return <EduCoachWaitingPanel label="대화를 준비하고 있어요…" />
  }

  if (completed && essay) {
    return (
      <div className={`mx-auto min-h-dvh px-4 py-4 ${PAGE_MAX}`} style={{ fontFamily: eduGame.fontBody }}>
        <EduQuestCompletionCelebration
          xpGained={xpGained}
          xpBreakdown={xpBreakdown}
          levelUp={levelUp}
          streakDays={tier?.streak_days ?? 0}
          coachLevel={coachLevel}
          tier={tier}
          active={completed}
        />
        <EduEssayCompletionPanel
          essay={essay}
          structure={null}
          onChange={setEssay}
          authorName={displayName}
          playReveal={playEssayReveal}
          onRevealComplete={() => setPlayEssayReveal(false)}
          saveStatus={saveStatus}
        />
        {quest?.quest_id && (
          <EduQuestComboContinue
            currentQuestId={quest.quest_id}
            diversity={{ questFrame: quest.quest_frame ?? null }}
            comboCount={todayComboCount}
            uiMode="cards"
          />
        )}
        <Link
          to={`/edu/share/${sessionId}`}
          className={`mt-4 block w-full py-3.5 text-center ${eduGameClasses.btnPrimary}`}
          style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
        >
          공유 카드 만들기
        </Link>
      </div>
    )
  }

  return (
    <div className={`mx-auto flex min-h-dvh flex-col px-4 pb-6 pt-4 ${PAGE_MAX}`} style={{ fontFamily: eduGame.fontBody }}>
      <header className="mb-4 flex items-center justify-between gap-3">
        <EduQuestHomeButton />
        <div className="min-w-0 flex-1 text-center">
          <p className="truncate text-sm font-medium" style={{ color: eduGame.muted }}>
            {quest?.quest_title ?? '핵 억지'}
          </p>
          <p className="text-xs" style={{ color: eduGame.muted }}>
            {displayName} · 대화형 다리
          </p>
        </div>
        <span className="text-xs font-semibold tabular-nums" style={{ color: eduGame.primary }}>
          {progressPct}%
        </span>
      </header>

      <div className="mb-4">
        <NarrativeProgressBar step={narrativeStep} />
      </div>

      <div ref={scrollRef} className={`${eduGameClasses.chatScroll} flex-1 space-y-3 overflow-y-auto pb-4`}>
        {dialogue.map((turn, i) => {
          const isCoach = turn.role === 'assistant'
          return (
            <motion.div
              key={`t-${i}`}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              className={`flex ${isCoach ? 'justify-start' : 'justify-end'}`}
            >
              <div
                className={`max-w-[92%] px-4 py-3 text-base ${isCoach ? eduGameClasses.coachBubble : eduGameClasses.studentBubble}`}
                style={
                  isCoach
                    ? { backgroundColor: eduGame.bubbleCoach, borderColor: eduGame.bubbleCoachBorder, color: eduGame.ink }
                    : { backgroundColor: eduGame.bubbleStudent }
                }
              >
                {isCoach ? (
                  splitCoachParagraphs(turn.content ?? '').map((para, j) => (
                    <CoachMessageText key={j} text={para} className="mb-2 last:mb-0" />
                  ))
                ) : (
                  <span>{turn.content}</span>
                )}
              </div>
            </motion.div>
          )
        })}

        {composing && (
          <EduCoachWaitingPanel label="네가 따진 생각을 글로 정리하고 있어요…" compact />
        )}
      </div>

      {error && (
        <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
          {error}
        </p>
      )}

      {!completed && !composing && choices.length > 0 && (
        <div className="sticky bottom-0 space-y-2 border-t bg-white/95 pt-3 backdrop-blur-sm" style={{ borderColor: eduGame.border }}>
          {choices.map(choice => (
            <button
              key={choice.id}
              type="button"
              disabled={sending}
              onClick={() => void handleChoice(choice)}
              className={`${eduGameClasses.btnPrimary} w-full px-4 py-3.5 text-left`}
              style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
            >
              {choice.label}
            </button>
          ))}
        </div>
      )}

      {!completed && !composing && choices.length === 0 && !sending && phase === 'narrative_bridge' && dialogue.length === 0 && (
        <div className="text-center text-sm" style={{ color: eduGame.muted }}>
          <Link to="/edu" className="underline">
            홈으로
          </Link>
        </div>
      )}
    </div>
  )
}
