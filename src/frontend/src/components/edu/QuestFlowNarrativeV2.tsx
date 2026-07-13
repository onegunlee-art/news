import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import QuestFlowNarrativeV2Mobile from './QuestFlowNarrativeV2Mobile'
import QuestFlowNarrativeV2Pc from './QuestFlowNarrativeV2Pc'
import { filledThoughtBoardCount, narrativeV2SessionIsPolluted } from '../../constants/eduNarrativeBridge'
import { eduCoachLevelByNumber, type EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import {
  eduApi,
  getEduDisplayName,
  type EduBlueprint,
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
import { type EssayArtifact } from './EssayRevealCard'
import {
  resolveCoachPrompt,
  resolveCoachPromptFromApi,
  resolveNarrativeV2Choices,
  resolveNarrativeV2InputMode,
  type NarrativeV2Choice,
  useMobileCompactLayout,
  useVisualViewportLayout,
} from './questFlowNarrativeV2Shared'

export default function QuestFlowNarrativeV2() {
  const [searchParams] = useSearchParams()
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''
  const mobileCompact = useMobileCompactLayout()
  const { viewportHeight, viewportOffsetTop, keyboardInset } = useVisualViewportLayout()

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [board, setBoard] = useState<EduThoughtBoardSlot[]>([])
  const [blueprint, setBlueprint] = useState<EduBlueprint | null>(null)
  const [choices, setChoices] = useState<NarrativeV2Choice[]>([])
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
  const [composeReady, setComposeReady] = useState(false)
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
  const composeDoneRef = useRef(false)
  const animDoneRef = useRef(false)
  const composeResultRef = useRef<EduComposeResponse | null>(null)
  const composeErrorRef = useRef<string | null>(null)
  const comboRecordedRef = useRef(false)
  const boardPeekTimerRef = useRef<number | null>(null)
  const inputRef = useRef<HTMLTextAreaElement>(null)
  const mobileCompactRef = useRef(mobileCompact)

  useEffect(() => {
    mobileCompactRef.current = mobileCompact
  }, [mobileCompact])

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
    if (res.blueprint) setBlueprint(res.blueprint)
    if (res.narrative_turn_count != null) setTurnCount(res.narrative_turn_count)
    setChoices(resolveNarrativeV2Choices(res))
    setInputMode(resolveNarrativeV2InputMode(res))
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
    setBlueprint(state.blueprint ?? null)
    setBoard(state.thought_board ?? state.blueprint?.thought_board ?? [])
    setTurnCount(state.narrative_turn_count ?? state.blueprint?.narrative_turn_count ?? 0)
    setChoices(resolveNarrativeV2Choices(state))
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

  const finishComposeFlow = useCallback(() => {
    if (!composeDoneRef.current || !animDoneRef.current) return

    setAssembling(false)
    setComposeReady(false)

    if (composeErrorRef.current) {
      composeStartedRef.current = false
      composeInFlightRef.current = false
      setError(composeErrorRef.current)
      return
    }

    if (composeResultRef.current) {
      applyComposeResult(composeResultRef.current)
    }
  }, [applyComposeResult])

  const startParallelCompose = useCallback(
    (sid: string) => {
      if (composeInFlightRef.current) return

      composeInFlightRef.current = true
      composeStartedRef.current = true
      composeDoneRef.current = false
      // PC: assemble 애니 없음 → anim 게이트 즉시 통과. Mobile: 현재 배포 동작 유지.
      animDoneRef.current = true
      composeResultRef.current = null
      composeErrorRef.current = null
      setComposeReady(false)
      setAssembling(true)
      setError('')

      void eduApi
        .composeEssay(sid)
        .then(res => {
          composeResultRef.current = res
          composeDoneRef.current = true
          setComposeReady(true)
          if (!mobileCompactRef.current) {
            animDoneRef.current = true
          }
          finishComposeFlow()
        })
        .catch(e => {
          composeErrorRef.current = e instanceof Error ? e.message : '글 생성 실패'
          composeDoneRef.current = true
          setComposeReady(true)
          if (!mobileCompactRef.current) {
            animDoneRef.current = true
          }
          finishComposeFlow()
        })
    },
    [finishComposeFlow]
  )

  const handleAnimComplete = useCallback(() => {
    animDoneRef.current = true
    finishComposeFlow()
  }, [finishComposeFlow])

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

  const handleChoice = async (choice: NarrativeV2Choice) => {
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

  const persistEssay = useCallback(
    async (data: EssayArtifact) => {
      if (!sessionId) return
      setSaveStatus('saving')
      setError('')
      try {
        const res = await eduApi.saveEssay(sessionId, {
          title: data.title,
          subtitle: data.subtitle,
          sections: data.sections,
          conclusion_heading: data.conclusion_heading,
          conclusion_paragraphs: data.conclusion_paragraphs,
          body_paragraphs: data.body_paragraphs,
          narration_mode: data.narration_mode,
          hero_sentence: data.hero_sentence,
          full_text: data.full_text,
        })
        setEssay({
          title: res.title,
          subtitle: res.subtitle,
          sections: res.sections,
          conclusion_heading: res.conclusion_heading,
          conclusion_paragraphs: res.conclusion_paragraphs,
          body_paragraphs: res.body_paragraphs ?? data.body_paragraphs,
          narration_mode: res.narration_mode ?? data.narration_mode,
          full_text: res.full_text ?? data.full_text,
          hero_sentence: res.hero_sentence ?? data.hero_sentence,
          feedback: data.feedback,
        })
        setSaveStatus('saved')
      } catch (e) {
        setSaveStatus('error')
        setError(e instanceof Error ? e.message : '저장 실패')
      }
    },
    [sessionId]
  )

  const displayName = getEduDisplayName()
  const filledCount = filledThoughtBoardCount(board)

  if (loading) return <EduCoachWaitingPanel label="탐구를 준비하고 있어요…" />

  const viewProps = {
    quest,
    sessionId,
    dialogue,
    board,
    blueprint,
    choices,
    inputMode,
    textInput,
    setTextInput,
    inputFocused,
    setInputFocused,
    pulseLayer,
    boardCollapsed,
    toggleBoardCollapsed,
    turnCount,
    progressPct,
    phase,
    sending,
    assembling,
    composeReady,
    completed,
    error,
    essay,
    setEssay,
    xpGained,
    xpBreakdown,
    tier,
    coachLevel,
    levelUp,
    todayComboCount,
    playEssayReveal,
    setPlayEssayReveal,
    saveStatus,
    displayName,
    filledCount,
    coachPrompt,
    inputRef,
    handleChoice,
    handleTextSubmit,
    handleAnimComplete,
    persistEssay,
    viewportHeight,
    viewportOffsetTop,
    keyboardInset,
  }

  if (mobileCompact) {
    return <QuestFlowNarrativeV2Mobile {...viewProps} mobileCompact={mobileCompact} />
  }

  return <QuestFlowNarrativeV2Pc {...viewProps} />
}
