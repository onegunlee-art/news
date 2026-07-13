import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import CoachMessageText from './CoachMessageText'
import EduArticleSnippetCard from './EduArticleSnippetCard'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import EduComposeWaitPanel from './EduComposeWaitPanel'
import EduEssayCompletionPanel from './EduEssayCompletionPanel'
import EduQuestComboContinue from './EduQuestComboContinue'
import EduQuestCompletionCelebration from './EduQuestCompletionCelebration'
import EduQuestHomeButton from './EduQuestHomeButton'
import EduThoughtBoardPanel from './EduThoughtBoardPanel'
import { filledThoughtBoardCount, narrativeV2SessionIsPolluted } from '../../constants/eduNarrativeBridge'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { eduCoachLevelByNumber, type EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import {
  eduApi,
  getEduDisplayName,
  type EduChatResponse,
  type EduComposeResponse,
  type EduDialogueTurn,
  type EduLevelUpPayload,
  type EduQuest,
  type EduThoughtBoardSlot,
  type EduTierProgress,
  type EduXpBreakdownLine,
} from '../../services/eduApi'
import { recordTodayQuestCompletion } from '../../utils/eduQuestCombo'
import { shouldTriggerEduCompose } from '../../utils/eduComposeTrigger'
import {
  coachMessageHasSnippet,
  parseCoachAssistantMessage,
  splitCoachParagraphs,
} from '../../utils/eduCoachMessageParse'
import { type EssayArtifact } from './EssayRevealCard'

const PAGE_MAX = 'max-w-2xl'
const COMPOSE_MIN_DISPLAY_MS = 1500

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

function resolveInputMode(
  res: Pick<EduChatResponse, 'narrative_v2_input_mode' | 'blueprint'>
): string {
  const direct = (res.narrative_v2_input_mode ?? '').trim()
  if (direct !== '') return direct
  const fromBp = (res.blueprint?.narrative_v2_input_mode ?? '').trim()
  return fromBp
}

function parseCoachCardContent(content: string): {
  question: string
  snippets: Array<{ display: string; value: string }>
} {
  if (!coachMessageHasSnippet(content)) {
    return { question: content, snippets: [] }
  }
  const segments = parseCoachAssistantMessage(content)
  const question = segments
    .filter(s => s.type === 'text')
    .map(s => s.value)
    .join('\n\n')
    .trim()
  const snippets = segments
    .filter(s => s.type === 'snippet')
    .map(s => ({ display: s.display, value: s.value }))
  return { question: question || content, snippets }
}

function resolveCoachPrompt(
  dialogue: EduDialogueTurn[],
  fallback = ''
): string {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'assistant') {
      const text = (dialogue[i].content ?? '').trim()
      if (text !== '') return text
    }
  }
  return fallback.trim()
}

function resolveCoachPromptFromApi(
  res: Pick<EduChatResponse, 'assistant_message' | 'choice_question_text'> & {
    dialogue?: EduDialogueTurn[]
  }
): string {
  const fromDialogue = resolveCoachPrompt(res.dialogue ?? [])
  if (fromDialogue !== '') return fromDialogue
  const fromAssistant = (res.assistant_message ?? '').trim()
  if (fromAssistant !== '') return fromAssistant
  return (res.choice_question_text ?? '').trim()
}

function lastAssistantIndex(dialogue: EduDialogueTurn[]): number {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'assistant') return i
  }
  return -1
}

function lastStudentAnswer(dialogue: EduDialogueTurn[]): string | null {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'student') return dialogue[i].content ?? null
  }
  return null
}

function useMobileCompactLayout(): boolean {
  const [mobile, setMobile] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches
  )

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 639px)')
    const onChange = () => setMobile(mq.matches)
    mq.addEventListener('change', onChange)
    return () => mq.removeEventListener('change', onChange)
  }, [])

  return mobile
}

function useVisualViewportLayout(): {
  viewportHeight: number | null
  viewportOffsetTop: number
  keyboardInset: number
} {
  const [layout, setLayout] = useState({
    viewportHeight: null as number | null,
    viewportOffsetTop: 0,
    keyboardInset: 0,
  })

  useEffect(() => {
    const vv = window.visualViewport
    if (!vv) return
    const update = () => {
      setLayout({
        viewportHeight: vv.height,
        viewportOffsetTop: vv.offsetTop,
        keyboardInset: Math.max(0, window.innerHeight - vv.height - vv.offsetTop),
      })
    }
    vv.addEventListener('resize', update)
    vv.addEventListener('scroll', update)
    update()
    return () => {
      vv.removeEventListener('resize', update)
      vv.removeEventListener('scroll', update)
    }
  }, [])

  return layout
}

export default function QuestFlowNarrativeV2() {
  const [searchParams] = useSearchParams()
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''
  const mobileCompact = useMobileCompactLayout()
  const { viewportHeight, viewportOffsetTop, keyboardInset } = useVisualViewportLayout()

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [board, setBoard] = useState<EduThoughtBoardSlot[]>([])
  const [choices, setChoices] = useState<Choice[]>([])
  const [coachPrompt, setCoachPrompt] = useState('')
  const [inputMode, setInputMode] = useState('')
  const [textInput, setTextInput] = useState('')
  const [inputFocused, setInputFocused] = useState(false)
  const [pulseLayer, setPulseLayer] = useState<string | null>(null)
  const [boardCollapsed, setBoardCollapsed] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches
  )
  const [boardCollapsedTouched, setBoardCollapsedTouched] = useState(false)
  const [turnCount, setTurnCount] = useState(0)
  const [progressPct, setProgressPct] = useState(0)
  const [phase, setPhase] = useState('narrative_bridge_v2')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [assembling, setAssembling] = useState(false)
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
  const composeInFlightRef = useRef(false)
  const composeResultRef = useRef<EduComposeResponse | null>(null)
  const composeErrorRef = useRef<string | null>(null)
  const composeShownAtRef = useRef(0)
  const composeRevealTimerRef = useRef<number | null>(null)
  const comboRecordedRef = useRef(false)
  const boardPeekTimerRef = useRef<number | null>(null)
  const inputRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    return () => {
      if (composeRevealTimerRef.current != null) {
        window.clearTimeout(composeRevealTimerRef.current)
      }
    }
  }, [])

  useEffect(() => {
    if (boardCollapsedTouched) return
    setBoardCollapsed(mobileCompact)
  }, [mobileCompact, boardCollapsedTouched])

  useEffect(() => {
    if (!mobileCompact || !pulseLayer) return
    setBoardCollapsed(false)
    if (boardPeekTimerRef.current != null) {
      window.clearTimeout(boardPeekTimerRef.current)
    }
    boardPeekTimerRef.current = window.setTimeout(() => {
      setBoardCollapsed(true)
      boardPeekTimerRef.current = null
    }, 2200)
    return () => {
      if (boardPeekTimerRef.current != null) {
        window.clearTimeout(boardPeekTimerRef.current)
        boardPeekTimerRef.current = null
      }
    }
  }, [pulseLayer, mobileCompact])

  const toggleBoardCollapsed = useCallback(() => {
    setBoardCollapsedTouched(true)
    if (boardPeekTimerRef.current != null) {
      window.clearTimeout(boardPeekTimerRef.current)
      boardPeekTimerRef.current = null
    }
    setBoardCollapsed(v => !v)
  }, [])

  const applyResponse = useCallback((res: EduChatResponse) => {
    if (res.phase) setPhase(res.phase)
    if (res.progress_pct != null) setProgressPct(res.progress_pct)
    if (res.thought_board) setBoard(res.thought_board)
    else if (res.blueprint?.thought_board) setBoard(res.blueprint.thought_board)
    if (res.narrative_turn_count != null) setTurnCount(res.narrative_turn_count)
    setChoices(resolveChoices(res))
    setInputMode(resolveInputMode(res))
    const prompt = resolveCoachPromptFromApi(res)
    if (prompt !== '') setCoachPrompt(prompt)
    const pulse = res.board_pulse_layer ?? res.blueprint?.board_pulse_layer ?? null
    if (pulse) {
      setPulseLayer(pulse)
      window.setTimeout(() => setPulseLayer(null), 1200)
    }
  }, [])

  const syncSessionState = useCallback(async (sid: string) => {
    const state = await eduApi.getSessionState(sid)
    setQuest(state.quest)
    const nextDialogue = state.dialogue ?? []
    setDialogue(nextDialogue)
    setProgressPct(state.progress_pct)
    setPhase(state.blueprint?.phase ?? 'narrative_bridge_v2')
    setBoard(state.thought_board ?? state.blueprint?.thought_board ?? [])
    setTurnCount(state.narrative_turn_count ?? state.blueprint?.narrative_turn_count ?? 0)
    setChoices(resolveChoices(state))
    setInputMode(
      (state.narrative_v2_input_mode ?? state.blueprint?.narrative_v2_input_mode ?? '').trim()
    )
    const prompt = resolveCoachPrompt(nextDialogue, state.choice_question_text ?? '')
    if (prompt !== '') setCoachPrompt(prompt)
    return state
  }, [])

  const applyComposeResult = useCallback((res: EduComposeResponse) => {
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
  }, [])

  const revealComposeResult = useCallback(() => {
    setAssembling(false)

    if (composeErrorRef.current) {
      composeStartedRef.current = false
      composeInFlightRef.current = false
      setError(composeErrorRef.current)
      return
    }

    if (composeResultRef.current) {
      applyComposeResult(composeResultRef.current)
    }
    composeInFlightRef.current = false
  }, [applyComposeResult])

  const scheduleComposeReveal = useCallback(() => {
    const elapsed = Date.now() - composeShownAtRef.current
    const wait = Math.max(0, COMPOSE_MIN_DISPLAY_MS - elapsed)
    if (composeRevealTimerRef.current != null) {
      window.clearTimeout(composeRevealTimerRef.current)
    }
    composeRevealTimerRef.current = window.setTimeout(() => {
      composeRevealTimerRef.current = null
      revealComposeResult()
    }, wait)
  }, [revealComposeResult])

  const startParallelCompose = useCallback(
    (sid: string) => {
      if (composeInFlightRef.current) return

      composeInFlightRef.current = true
      composeStartedRef.current = true
      composeShownAtRef.current = Date.now()
      composeResultRef.current = null
      composeErrorRef.current = null
      setAssembling(true)
      setError('')

      void eduApi
        .composeEssay(sid)
        .then(res => {
          composeResultRef.current = res
          scheduleComposeReveal()
        })
        .catch(e => {
          composeErrorRef.current = e instanceof Error ? e.message : '글 생성 실패'
          scheduleComposeReveal()
        })
    },
    [scheduleComposeReveal]
  )

  const handleChatResponse = useCallback(
    async (res: EduChatResponse, sid: string) => {
      applyResponse(res)
      if (shouldTriggerEduCompose(res)) {
        startParallelCompose(sid)
      }
      await syncSessionState(sid)
    },
    [applyResponse, startParallelCompose, syncSessionState]
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
        const polluted = narrativeV2SessionIsPolluted(
          state.blueprint,
          state.dialogue,
          state.narrative_v2_polluted
        )
        const shouldInit =
          !initCalledRef.current && ((state.dialogue?.length ?? 0) === 0 || polluted)
        if (shouldInit) {
          initCalledRef.current = true
          const res = await eduApi.sendChat(sid, {
            action: 'narrative_v2_init',
            ...(polluted ? { force_reset: true } : {}),
          })
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
    if (inputMode === 'text') {
      window.setTimeout(() => inputRef.current?.focus(), 80)
    }
  }, [inputMode])

  useEffect(() => {
    if (!sessionId || loading || completed || assembling || composeStartedRef.current) return
    if (phase !== 'compose') return
    startParallelCompose(sessionId)
  }, [sessionId, loading, completed, assembling, phase, startParallelCompose])

  const handleChoice = async (choice: Choice) => {
    if (!sessionId || sending || completed) return
    setSending(true)
    setError('')
    setChoices([])
    if (choice.id === 'go_compose') {
      setAssembling(true)
    }
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'narrative_v2_choice', choice_id: choice.id })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      if (choice.id === 'go_compose') {
        setAssembling(false)
      }
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
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'narrative_v2_message', message: msg })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '입력 전송 실패')
      setTextInput(msg)
      await syncSessionState(sessionId)
    } finally {
      setSending(false)
    }
  }

  const coachIndex = lastAssistantIndex(dialogue)
  const coachTurn = coachIndex >= 0 ? dialogue[coachIndex] : null
  const coachMessage = (coachTurn?.content ?? '').trim() || coachPrompt
  const cardContent = useMemo(
    () => parseCoachCardContent(coachMessage),
    [coachMessage]
  )
  const cardParagraphs = splitCoachParagraphs(cardContent.question)
  const cardKey = `${phase}-${coachIndex}-${dialogue.length}-${inputMode}-${choices.map(c => c.id).join('|')}`
  const keyboardOpen = keyboardInset > 40 || inputFocused
  const showTextInput = inputMode === 'text' && !assembling
  const waitingLabel = sending ? '코치가 읽는 중…' : '탐구를 이어가고 있어요…'

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
      className={`${eduGameClasses.chatShell} fixed left-0 right-0 flex flex-col overflow-hidden`}
      style={{
        color: eduGame.ink,
        fontFamily: eduGame.fontBody,
        backgroundColor: eduGame.bg,
        top: viewportHeight != null ? viewportOffsetTop : 0,
        height: viewportHeight ?? '100dvh',
        maxHeight: viewportHeight ?? '100dvh',
      }}
    >
      <header
        className={`shrink-0 border-b ${PAGE_MAX} mx-auto w-full px-3 py-2`}
        style={{
          borderColor: eduGame.border,
          backgroundColor: eduGame.bg,
          paddingTop: 'max(0.375rem, env(safe-area-inset-top, 0px))',
        }}
      >
        <div className="flex items-center gap-2">
          <EduQuestHomeButton />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-bold">{quest?.quest_title ?? '오늘의 탐구'}</p>
            <p className="text-xs" style={{ color: eduGame.muted }}>
              {displayName} · {turnCount}턴 · 생각판 {filledCount}/6
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
          onToggle={toggleBoardCollapsed}
          filledCount={filledCount}
          compact={mobileCompact}
        />
      </div>

      <div className={`flex-1 min-h-0 flex flex-col overflow-hidden ${PAGE_MAX} mx-auto w-full`}>
        <AnimatePresence mode="wait">
          <motion.div
            key={cardKey}
            initial={{ opacity: 0, x: 40 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -40 }}
            transition={{ duration: 0.22, ease: 'easeOut' }}
            className="flex-1 min-h-0 flex flex-col overflow-hidden"
          >
            {sending && !assembling ? (
              <div className="flex-1 min-h-0 flex flex-col justify-center px-4 py-6">
                <EduCoachWaitingPanel studentAnswer={lastStudentAnswer(dialogue)} label={waitingLabel} compact />
              </div>
            ) : (
              <div
                className={`flex-1 min-h-0 overflow-y-auto px-4 pt-3 pb-2 ${mobileCompact ? 'flex flex-col justify-center' : ''}`}
              >
                <div className="space-y-4">
                  {cardParagraphs.map((paragraph, i) => (
                    <p
                      key={`${cardKey}-p-${i}`}
                      className={`text-center font-bold ${eduGameClasses.textKoPre}`}
                      style={{
                        fontSize: keyboardOpen ? '1.0625rem' : mobileCompact ? '1.125rem' : '1.25rem',
                        lineHeight: 1.55,
                        color: eduGame.ink,
                      }}
                    >
                      <CoachMessageText text={paragraph} />
                    </p>
                  ))}
                </div>

                {cardContent.snippets.length > 0 && (
                  <div
                    className="mt-3 space-y-2 overflow-y-auto"
                    style={{ maxHeight: keyboardOpen ? '14vh' : mobileCompact ? '18vh' : '24vh' }}
                  >
                    {cardContent.snippets.map((snip, i) => (
                      <EduArticleSnippetCard key={`${cardKey}-snip-${i}`} text={snip.value} display={snip.display} />
                    ))}
                  </div>
                )}
              </div>
            )}
          </motion.div>
        </AnimatePresence>
      </div>

      {error && <p className={`mx-auto ${PAGE_MAX} w-full px-4 pb-2 text-sm text-red-600`}>{error}</p>}

      <footer
        className={`shrink-0 border-t ${PAGE_MAX} mx-auto w-full px-4`}
        style={{
          borderColor: eduGame.border,
          backgroundColor: eduGame.bg,
          paddingTop: keyboardOpen ? '0.375rem' : '0.625rem',
          paddingBottom: keyboardOpen
            ? `calc(0.375rem + ${Math.max(keyboardInset, 0)}px + env(safe-area-inset-bottom, 0px))`
            : 'calc(0.625rem + env(safe-area-inset-bottom, 0px))',
        }}
      >
        {showTextInput ? (
          <div className="space-y-2">
            <textarea
              ref={inputRef}
              value={textInput}
              onChange={e => setTextInput(e.target.value)}
              onFocus={() => setInputFocused(true)}
              onBlur={() => window.setTimeout(() => setInputFocused(false), 100)}
              rows={keyboardOpen ? 2 : 3}
              placeholder="네 생각을 한두 문장으로 써 봐…"
              className={`w-full resize-none ${eduGameClasses.input}`}
              style={{
                borderColor: eduGame.border,
                fontSize: eduGame.fontSize.body,
                maxHeight: keyboardOpen ? '4.75rem' : '7rem',
              }}
              disabled={sending || assembling}
            />
            <button
              type="button"
              disabled={sending || assembling || !textInput.trim()}
              onClick={() => void handleTextSubmit()}
              className={`${eduGameClasses.btnPrimary} w-full py-4`}
              style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
            >
              {sending ? '보내는 중…' : '내 결론 보내기'}
            </button>
          </div>
        ) : choices.length > 0 ? (
          <div className="space-y-2.5" role="group" aria-label="선택지">
            {choices.map((c, i) => (
              <button
                key={c.id}
                type="button"
                disabled={sending || assembling}
                onClick={() => void handleChoice(c)}
                className={`w-full py-4 px-4 rounded-2xl font-bold border-2 text-center active:scale-[0.98] transition-transform disabled:opacity-40 disabled:active:scale-100 ${eduGameClasses.textKo}`}
                style={{
                  fontSize: '1.0625rem',
                  lineHeight: 1.45,
                  borderColor: eduGame.primary,
                  backgroundColor: i === 0 ? eduGame.primary : eduGame.bg,
                  color: i === 0 ? eduGame.bg : eduGame.ink,
                  boxShadow: i === 0 ? `0 2px 0 ${eduGame.primaryDark}59` : `0 2px 0 ${eduGame.border}`,
                }}
              >
                {c.label}
              </button>
            ))}
          </div>
        ) : null}
      </footer>

      {assembling && sessionId ? (
        <EduComposeWaitPanel board={board} questTitle={quest?.quest_title} turnCount={turnCount} />
      ) : null}
    </div>
  )
}
