import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import CoachMessageText from './CoachMessageText'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import EduEssayCompletionPanel from './EduEssayCompletionPanel'
import EduQuestComboContinue from './EduQuestComboContinue'
import EduQuestCompletionCelebration from './EduQuestCompletionCelebration'
import EduQuestHomeButton from './EduQuestHomeButton'
import EduThoughtBoardPanel from './EduThoughtBoardPanel'
import { filledThoughtBoardCount } from '../../constants/eduNarrativeBridge'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { eduCoachLevelByNumber, type EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import {
  eduApi,
  getEduDisplayName,
  type EduChatResponse,
  type EduDialogueTurn,
  type EduLevelUpPayload,
  type EduQuest,
  type EduThoughtBoardSlot,
  type EduTierProgress,
  type EduXpBreakdownLine,
} from '../../services/eduApi'
import { recordTodayQuestCompletion } from '../../utils/eduQuestCombo'
import { shouldTriggerEduCompose } from '../../utils/eduComposeTrigger'
import { splitCoachParagraphs } from '../../utils/eduCoachMessageParse'
import { type EssayArtifact } from './EssayRevealCard'

const PAGE_MAX = 'max-w-2xl'

type Choice = { id: string; label: string }

function resolveChoices(res: Pick<EduChatResponse, 'narrative_choices' | 'choice_question' | 'options'>): Choice[] {
  if (Array.isArray(res.narrative_choices) && res.narrative_choices.length > 0) {
    return res.narrative_choices
  }
  if (res.choice_question && Array.isArray(res.options)) {
    return res.options.map((label, i) => ({ id: `opt_${i}`, label }))
  }
  return []
}

function useKeyboardInset(): number {
  const [inset, setInset] = useState(0)
  useEffect(() => {
    const vv = window.visualViewport
    if (!vv) return
    const update = () => {
      const gap = window.innerHeight - vv.height - vv.offsetTop
      setInset(gap > 0 ? gap : 0)
    }
    vv.addEventListener('resize', update)
    vv.addEventListener('scroll', update)
    update()
    return () => {
      vv.removeEventListener('resize', update)
      vv.removeEventListener('scroll', update)
    }
  }, [])
  return inset
}

export default function QuestFlowNarrativeV2() {
  const [searchParams] = useSearchParams()
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''
  const keyboardInset = useKeyboardInset()

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [board, setBoard] = useState<EduThoughtBoardSlot[]>([])
  const [choices, setChoices] = useState<Choice[]>([])
  const [inputMode, setInputMode] = useState('')
  const [textInput, setTextInput] = useState('')
  const [pulseLayer, setPulseLayer] = useState<string | null>(null)
  const [boardCollapsed, setBoardCollapsed] = useState(false)
  const [turnCount, setTurnCount] = useState(0)
  const [progressPct, setProgressPct] = useState(0)
  const [phase, setPhase] = useState('narrative_bridge_v2')
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

  const initCalledRef = useRef(false)
  const composeStartedRef = useRef(false)
  const comboRecordedRef = useRef(false)
  const scrollRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLTextAreaElement>(null)

  const applyResponse = useCallback((res: EduChatResponse) => {
    if (res.phase) setPhase(res.phase)
    if (res.progress_pct != null) setProgressPct(res.progress_pct)
    if (res.thought_board) setBoard(res.thought_board)
    else if (res.blueprint?.thought_board) setBoard(res.blueprint.thought_board)
    if (res.narrative_turn_count != null) setTurnCount(res.narrative_turn_count)
    setChoices(resolveChoices(res))
    setInputMode(res.narrative_v2_input_mode ?? '')
    const pulse = res.board_pulse_layer ?? res.blueprint?.board_pulse_layer ?? null
    if (pulse) {
      setPulseLayer(pulse)
      window.setTimeout(() => setPulseLayer(null), 1200)
    }
  }, [])

  const syncSessionState = useCallback(async (sid: string) => {
    const state = await eduApi.getSessionState(sid)
    setQuest(state.quest)
    setDialogue(state.dialogue ?? [])
    setProgressPct(state.progress_pct)
    setPhase(state.blueprint?.phase ?? 'narrative_bridge_v2')
    setBoard(state.thought_board ?? state.blueprint?.thought_board ?? [])
    setTurnCount(state.narrative_turn_count ?? state.blueprint?.narrative_turn_count ?? 0)
    setChoices(resolveChoices(state))
    setInputMode(state.narrative_v2_input_mode ?? '')
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
      try {
        let sid = ''
        if (questIdParam) {
          sid = (await eduApi.startSession(questIdParam)).session_id
        } else {
          const today = await eduApi.todayQuest()
          if (!today.quest) {
            setError('오늘의 퀘스트가 없습니다.')
            return
          }
          sid = today.active_session?.session_id ?? today.existing_session?.session_id ?? ''
          if (!sid) sid = (await eduApi.startSession(today.quest.quest_id)).session_id
        }
        if (cancelled) return
        setSessionId(sid)
        const state = await syncSessionState(sid)
        if (state.stage === 'completed' && state.essay) {
          setCompleted(true)
          setEssay({
            full_text: state.essay.full_text,
            hero_sentence: state.essay.hero_sentence,
            feedback: state.essay.feedback,
            sections: state.essay.sections,
            title: state.essay.title,
            subtitle: state.essay.subtitle,
          })
          setProgressPct(100)
          return
        }
        if (!initCalledRef.current && (state.dialogue?.length ?? 0) === 0) {
          initCalledRef.current = true
          const res = await eduApi.sendChat(sid, { action: 'narrative_v2_init' })
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
    if (inputMode === 'text') {
      inputRef.current?.focus()
    }
  }, [inputMode])

  useEffect(() => {
    if (!sessionId || loading || completed || composing || composeStartedRef.current) return
    if (phase !== 'compose') return
    composeStartedRef.current = true
    void handleCompose(sessionId)
  }, [sessionId, loading, completed, composing, phase, handleCompose])

  const handleChoice = async (choice: Choice) => {
    if (!sessionId || sending || completed) return
    setSending(true)
    setError('')
    setChoices([])
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'narrative_v2_choice', choice_id: choice.id })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '선택 전송 실패')
      await syncSessionState(sessionId)
    } finally {
      setSending(false)
    }
  }

  const handleTextSubmit = async () => {
    const msg = textInput.trim()
    if (!sessionId || !msg || sending || completed) return
    setSending(true)
    setError('')
    setTextInput('')
    setInputMode('')
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'narrative_v2_message', message: msg })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '입력 전송 실패')
      await syncSessionState(sessionId)
    } finally {
      setSending(false)
    }
  }

  const displayName = getEduDisplayName()
  const filledCount = filledThoughtBoardCount(board)

  if (loading) return <EduCoachWaitingPanel label="탐구를 준비하고 있어요…" />

  if (completed && essay) {
    return (
      <div className={`mx-auto min-h-dvh px-4 py-4 ${PAGE_MAX}`} style={{ fontFamily: eduGame.fontBody }}>
        <EduQuestCompletionCelebration xpGained={xpGained} xpBreakdown={xpBreakdown} levelUp={levelUp} streakDays={tier?.streak_days ?? 0} coachLevel={coachLevel} tier={tier} active={completed} />
        <EduEssayCompletionPanel essay={essay} structure={null} onChange={setEssay} authorName={displayName} playReveal={playEssayReveal} onRevealComplete={() => setPlayEssayReveal(false)} saveStatus={saveStatus} />
        {quest?.quest_id && <EduQuestComboContinue currentQuestId={quest.quest_id} diversity={{ questFrame: quest.quest_frame ?? null }} comboCount={todayComboCount} uiMode="cards" />}
      </div>
    )
  }

  return (
    <div
      className="fixed inset-0 flex flex-col overflow-hidden"
      style={{ fontFamily: eduGame.fontBody, backgroundColor: eduGame.bg, paddingBottom: keyboardInset > 0 ? keyboardInset : undefined }}
    >
      <header className={`shrink-0 border-b px-3 py-2 ${PAGE_MAX} mx-auto w-full`} style={{ borderColor: eduGame.border }}>
        <div className="flex items-center gap-2">
          <EduQuestHomeButton />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-bold">{quest?.quest_title ?? '핵 억지'}</p>
            <p className="text-xs" style={{ color: eduGame.muted }}>
              {displayName} · {turnCount}턴 · 탐구 v2
            </p>
          </div>
          <span className="text-xs font-bold tabular-nums" style={{ color: eduGame.primary }}>
            {progressPct}%
          </span>
        </div>
      </header>

      <div className={`${PAGE_MAX} mx-auto w-full shrink-0`}>
        <EduThoughtBoardPanel
          board={board}
          pulseLayer={pulseLayer}
          collapsed={boardCollapsed}
          onToggle={() => setBoardCollapsed(v => !v)}
          filledCount={filledCount}
        />
      </div>

      <div ref={scrollRef} className={`${PAGE_MAX} mx-auto w-full flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-3 ${eduGameClasses.chatScroll}`}>
        {dialogue.map((turn, i) => {
          const isCoach = turn.role === 'assistant'
          return (
            <motion.div key={`t-${i}`} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} className={`flex ${isCoach ? 'justify-start' : 'justify-end'}`}>
              <div
                className={`max-w-[92%] px-3.5 py-3 text-base ${isCoach ? eduGameClasses.coachBubble : eduGameClasses.studentBubble}`}
                style={isCoach ? { backgroundColor: eduGame.bubbleCoach, borderColor: eduGame.bubbleCoachBorder, color: eduGame.ink } : { backgroundColor: eduGame.bubbleStudent }}
              >
                {isCoach
                  ? splitCoachParagraphs(turn.content ?? '').map((para, j) => <CoachMessageText key={j} text={para} className="mb-2 last:mb-0" />)
                  : turn.content}
              </div>
            </motion.div>
          )
        })}
        {composing && <EduCoachWaitingPanel label="생각판을 바탕으로 글을 쓰고 있어요…" compact />}
      </div>

      {error && <p className="mx-auto max-w-2xl px-3 pb-2 text-sm text-red-600">{error}</p>}

      <footer
        className={`shrink-0 border-t ${PAGE_MAX} mx-auto w-full px-3 pt-2`}
        style={{
          borderColor: eduGame.border,
          backgroundColor: eduGame.bg,
          paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))',
        }}
      >
        {inputMode === 'text' ? (
          <div className="space-y-2">
            <textarea
              ref={inputRef}
              value={textInput}
              onChange={e => setTextInput(e.target.value)}
              rows={3}
              placeholder="네 생각을 한두 문장으로 써 봐…"
              className={`w-full resize-none ${eduGameClasses.input}`}
              style={{ borderColor: eduGame.border, fontSize: eduGame.fontSize.body }}
              disabled={sending || composing}
            />
            <button
              type="button"
              disabled={sending || !textInput.trim()}
              onClick={() => void handleTextSubmit()}
              className={`${eduGameClasses.btnPrimary} w-full py-3.5`}
              style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
            >
              보내기
            </button>
          </div>
        ) : choices.length > 0 ? (
          <div className="space-y-2">
            {choices.map(c => (
              <button
                key={c.id}
                type="button"
                disabled={sending || composing}
                onClick={() => void handleChoice(c)}
                className={`${eduGameClasses.btnPrimary} w-full py-3.5 text-left px-4`}
                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
              >
                {c.label}
              </button>
            ))}
          </div>
        ) : null}
      </footer>
    </div>
  )
}
