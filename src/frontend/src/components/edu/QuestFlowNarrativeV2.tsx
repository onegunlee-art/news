import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import EduEssayCompletionPanel from './EduEssayCompletionPanel'
import EduQuestComboContinue from './EduQuestComboContinue'
import EduQuestCompletionCelebration from './EduQuestCompletionCelebration'
import QuestFlowNarrativeV2Mobile from './QuestFlowNarrativeV2Mobile'
import QuestFlowNarrativeV2Pc from './QuestFlowNarrativeV2Pc'
import { filledThoughtBoardCount, narrativeV2SessionIsPolluted } from '../../constants/eduNarrativeBridge'
import { eduGame } from '../../constants/eduGameTheme'
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
import { splitCoachParagraphs } from '../../utils/eduCoachMessageParse'
import { type EssayArtifact } from './EssayRevealCard'
import { QUEST_FLOW_PAGE_MAX } from './questFlowNarrativeV2Shared'
import {
  lastAssistantIndex,
  parseCoachCardContent,
  resolveChoices,
  resolveCoachPrompt,
  resolveCoachPromptFromApi,
  resolveInputMode,
} from './questFlowNarrativeV2Utils'

const COMPOSE_MIN_DISPLAY_MS = 1500

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

/** narrative_bridge_v2 오케스트레이터 — state/handler 여기만. view는 Mobile/Pc 분기 */
export default function QuestFlowNarrativeV2() {
  const [searchParams] = useSearchParams()
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''
  const mobileCompact = useMobileCompactLayout()
  const { viewportHeight, viewportOffsetTop, keyboardInset } = useVisualViewportLayout()

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [board, setBoard] = useState<EduThoughtBoardSlot[]>([])
  const [choices, setChoices] = useState<{ id: string; label: string }[]>([])
  const [coachPrompt, setCoachPrompt] = useState('')
  const [inputMode, setInputMode] = useState('')
  const [textInput, setTextInput] = useState('')
  const [inputFocused, setInputFocused] = useState(false)
  const [pulseLayer, setPulseLayer] = useState<string | null>(null)
  const [boardFocusLayer, setBoardFocusLayer] = useState<string | null>(null)
  const [boardCollapsed, setBoardCollapsed] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches
  )
  const [boardCollapsedTouched, setBoardCollapsedTouched] = useState(false)
  const [turnCount, setTurnCount] = useState(0)
  const [progressPct, setProgressPct] = useState(0)
  const [phase, setPhase] = useState('narrative_bridge_v2')
  const [narrativeV2Node, setNarrativeV2Node] = useState('')
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

  const openBoardPanel = useCallback((layerId?: string) => {
    setBoardCollapsedTouched(true)
    if (boardPeekTimerRef.current != null) {
      window.clearTimeout(boardPeekTimerRef.current)
      boardPeekTimerRef.current = null
    }
    setBoardCollapsed(false)
    const id = layerId?.trim() ?? ''
    if (id !== '') {
      setBoardFocusLayer(id)
      window.setTimeout(() => setBoardFocusLayer(null), 2200)
    }
  }, [])

  const applyResponse = useCallback((res: EduChatResponse) => {
    if (res.phase) setPhase(res.phase)
    if (res.progress_pct != null) setProgressPct(res.progress_pct)
    if (res.thought_board) setBoard(res.thought_board)
    else if (res.blueprint?.thought_board) setBoard(res.blueprint.thought_board)
    if (res.narrative_turn_count != null) setTurnCount(res.narrative_turn_count)
    const node = (res.narrative_v2_node ?? res.blueprint?.narrative_v2_node ?? '').trim()
    if (node) setNarrativeV2Node(node)
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
    setNarrativeV2Node(
      (state.narrative_v2_node ?? state.blueprint?.narrative_v2_node ?? '').trim()
    )
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

  const handleChoice = async (choice: { id: string; label: string }) => {
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
  const cardContent = useMemo(() => parseCoachCardContent(coachMessage), [coachMessage])
  const cardParagraphs = splitCoachParagraphs(cardContent.question)
  const cardKey = `${phase}-${coachIndex}-${dialogue.length}-${inputMode}-${choices.map(c => c.id).join('|')}`
  const keyboardOpen = keyboardInset > 40 || inputFocused
  const showTextInput = inputMode === 'text' && !assembling
  const waitingLabel = sending ? '코치가 읽는 중…' : '탐구를 이어가고 있어요…'
  const displayName = getEduDisplayName()
  const filledCount = filledThoughtBoardCount(board)

  if (loading) return <EduCoachWaitingPanel label="탐구를 준비하고 있어요…" />

  if (completed && essay) {
    if (!mobileCompact) {
      return (
        <QuestFlowNarrativeV2Pc
          quest={quest}
          sessionId={sessionId}
          dialogue={dialogue}
          board={board}
          choices={[]}
          inputMode={inputMode}
          textInput=""
          setTextInput={() => {}}
          pulseLayer={null}
          boardCollapsed={false}
          toggleBoardCollapsed={() => {}}
          openBoardPanel={() => {}}
          boardFocusLayer={null}
          turnCount={turnCount}
          progressPct={progressPct}
          phase={phase}
          narrativeV2Node={narrativeV2Node}
          sending={false}
          assembling={false}
          completed
          error=""
          coachIndex={coachIndex}
          cardContent={cardContent}
          cardParagraphs={cardParagraphs}
          cardKey={cardKey}
          keyboardOpen={false}
          showTextInput={false}
          waitingLabel=""
          displayName={displayName}
          filledCount={filledCount}
          mobileCompact={false}
          viewportHeight={null}
          viewportOffsetTop={0}
          keyboardInset={0}
          inputFocused={false}
          setInputFocused={() => {}}
          inputRef={inputRef}
          onChoice={() => {}}
          onTextSubmit={() => {}}
          completion={{
            quest,
            board,
            essay,
            setEssay,
            displayName,
            xpGained,
            xpBreakdown,
            levelUp,
            tier,
            coachLevel,
            todayComboCount,
            playEssayReveal,
            setPlayEssayReveal,
            saveStatus,
          }}
        />
      )
    }

    return (
      <div className={`mx-auto min-h-dvh px-4 py-4 ${QUEST_FLOW_PAGE_MAX}`} style={{ fontFamily: eduGame.fontBody }}>
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
        {quest?.quest_id ? (
          <EduQuestComboContinue
            currentQuestId={quest.quest_id}
            diversity={{ questFrame: quest.quest_frame ?? null }}
            comboCount={todayComboCount}
            uiMode="cards"
          />
        ) : null}
      </div>
    )
  }

  const viewProps = {
    quest,
    sessionId,
    dialogue,
    board,
    choices,
    inputMode,
    textInput,
    setTextInput,
    pulseLayer,
    boardCollapsed,
    toggleBoardCollapsed,
    openBoardPanel,
    boardFocusLayer,
    turnCount,
    progressPct,
    phase,
    narrativeV2Node,
    sending,
    assembling,
    completed,
    error,
    coachIndex,
    cardContent,
    cardParagraphs,
    cardKey,
    keyboardOpen,
    showTextInput,
    waitingLabel,
    displayName,
    filledCount,
    mobileCompact,
    viewportHeight,
    viewportOffsetTop,
    keyboardInset,
    inputFocused,
    setInputFocused,
    inputRef,
    onChoice: handleChoice,
    onTextSubmit: handleTextSubmit,
  }

  if (mobileCompact) {
    return <QuestFlowNarrativeV2Mobile {...viewProps} />
  }

  return <QuestFlowNarrativeV2Pc {...viewProps} />
}
