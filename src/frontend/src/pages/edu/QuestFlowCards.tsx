import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import EduEssayCompletionPanel from '../../components/edu/EduEssayCompletionPanel'
import { type EssayArtifact } from '../../components/edu/EssayRevealCard'
import { type EssayStructurePreview } from '../../components/edu/StructurePreviewCard'
import { shouldTriggerEduCompose } from '../../utils/eduComposeTrigger'
import { getTodayComboCount, recordTodayQuestCompletion } from '../../utils/eduQuestCombo'
import EduQuestCompletionCelebration from '../../components/edu/EduQuestCompletionCelebration'
import EduQuestComboContinue from '../../components/edu/EduQuestComboContinue'
import CoachMessageText from '../../components/edu/CoachMessageText'
import EduCoachWaitingPanel from '../../components/edu/EduCoachWaitingPanel'
import EduArticleSnippetCard from '../../components/edu/EduArticleSnippetCard'
import CardStructureBar from '../../components/edu/CardStructureBar'
import {
  completedSlotOnPhaseExit,
  structureNudgeForAxisPass,
  structureNudgeForPhaseSlot,
} from '../../components/edu/cardStructureBarState'
import {
  coachMessageHasSnippet,
  parseCoachAssistantMessage,
  splitCoachParagraphs,
} from '../../utils/eduCoachMessageParse'
import {
  eduApi,
  getEduToken,
  getEduDisplayName,
  type EduChatResponse,
  type EduDialogueTurn,
  type EduLevelUpPayload,
  type EduQuest,
  type EduTierProgress,
  type EduXpBreakdownLine,
} from '../../services/eduApi'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { eduQuestPathWithUi, setEduCoachUiMode } from '../../constants/eduCoachUi'
import { resolveEduInsightDebug, type EduStructureInsightDebug } from '../../constants/eduInsightDebug'
import { eduCoachLevelByNumber, type EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import { canShowCoachLevelDebugSwitch, resolveEduLevelDebug } from '../../constants/eduLevelDebug'
import EduStructureInsightDebugPanel from '../../components/edu/EduStructureInsightDebugPanel'
import EduQuestHomeButton from '../../components/edu/EduQuestHomeButton'

const PAGE_MAX = 'max-w-2xl'
const EVIDENCE_RECOMMENDED_LEN = 20
const COACH_CHOICE_CACHE_PREFIX = 'edu_coach_choice_v1'
const STRUCTURE_PULSE_MS = 900

type CoachChoiceState = {
  active: boolean
  options: string[]
  questionText: string
}

function emptyCoachChoice(): CoachChoiceState {
  return { active: false, options: [], questionText: '' }
}

function coachChoiceCacheKey(sessionId: string, coachIndex: number): string {
  return `${COACH_CHOICE_CACHE_PREFIX}:${sessionId}:${coachIndex}`
}

function readCachedCoachChoice(sessionId: string, coachIndex: number): CoachChoiceState | null {
  if (coachIndex < 0) return null
  try {
    const raw = sessionStorage.getItem(coachChoiceCacheKey(sessionId, coachIndex))
    if (!raw) return null
    const parsed = JSON.parse(raw) as CoachChoiceState
    if (!parsed.active || !Array.isArray(parsed.options) || parsed.options.length === 0) {
      return null
    }
    return parsed
  } catch {
    return null
  }
}

function writeCachedCoachChoice(sessionId: string, coachIndex: number, choice: CoachChoiceState): void {
  if (coachIndex < 0) return
  if (!choice.active || choice.options.length === 0) {
    sessionStorage.removeItem(coachChoiceCacheKey(sessionId, coachIndex))
    return
  }
  sessionStorage.setItem(coachChoiceCacheKey(sessionId, coachIndex), JSON.stringify(choice))
}

function resolveCoachChoiceFromResponse(
  res: Pick<EduChatResponse, 'choice_question' | 'options' | 'choice_question_text'>
): CoachChoiceState {
  if (res.choice_question && Array.isArray(res.options) && res.options.length > 0) {
    return {
      active: true,
      options: res.options,
      questionText: (res.choice_question_text ?? '').trim(),
    }
  }
  return emptyCoachChoice()
}

function triggerStructureBarPulse(
  slot: number,
  nudge: string,
  setPulse: (v: boolean) => void,
  setSlot: (v: number | null) => void,
  setNudge: (v: string) => void
): ReturnType<typeof setTimeout> {
  setPulse(true)
  setSlot(slot)
  setNudge(nudge)
  return setTimeout(() => {
    setPulse(false)
    setSlot(null)
    setNudge('')
  }, STRUCTURE_PULSE_MS)
}

type QuestFooterMode = 'opening' | 'evidence' | 'reflection' | 'chat' | 'stance_pick'
type QuestEntryMode = 'open_response' | 'stance_pick'
type StanceEntryChatAction = 'submit_opening' | 'select_stance'

function resolveQuestEntryMode(quest: EduQuest | null | undefined): QuestEntryMode {
  if (!quest) return 'stance_pick'
  if (quest.entry_mode === 'open_response' || quest.entry_mode === 'stance_pick') {
    return quest.entry_mode
  }
  return quest.quest_frame === 'myth_bust' ? 'open_response' : 'stance_pick'
}

function resolveStanceEntryChatAction(entryMode: QuestEntryMode): StanceEntryChatAction {
  return entryMode === 'open_response' ? 'submit_opening' : 'select_stance'
}

function resolveQuestFooterMode(
  phase: string,
  entryMode: QuestEntryMode,
  dialogueLength: number
): QuestFooterMode | null {
  if (phase === 'stance') {
    if (dialogueLength > 0) {
      return 'chat'
    }
    return entryMode === 'open_response' ? 'opening' : 'stance_pick'
  }
  if (phase === 'evidence') return 'evidence'
  if (phase === 'reflection') return 'reflection'
  if (phase === 'reasoning' || phase === 'hammer') return 'chat'
  if (phase === 'guide_axis' || phase === 'guide_conclusion') return 'chat'
  return null
}

function lastAssistantDialogueIndex(dialogue: EduDialogueTurn[]): number {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'assistant') return i
  }
  return -1
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
    .filter((s) => s.type === 'text')
    .map((s) => s.value)
    .join('\n\n')
    .trim()
  const snippets = segments
    .filter((s) => s.type === 'snippet')
    .map((s) => ({ display: s.display, value: s.value }))
  return {
    question: question || content,
    snippets,
  }
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

export default function QuestFlowCards() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const questIdParam = searchParams.get('quest_id')?.trim() || ''
  const insightDebug = resolveEduInsightDebug(searchParams)
  resolveEduLevelDebug(searchParams)
  const { viewportHeight, viewportOffsetTop, keyboardInset } = useVisualViewportLayout()
  const [inputFocused, setInputFocused] = useState(false)

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [input, setInput] = useState('')
  const [evidenceInput, setEvidenceInput] = useState('')
  const [evidenceNudgeCount, setEvidenceNudgeCount] = useState(0)
  const [progressPct, setProgressPct] = useState(0)
  const [phase, setPhase] = useState('stance')
  const [completed, setCompleted] = useState(false)
  const [essay, setEssay] = useState<EssayArtifact | null>(null)
  const [xpGained, setXpGained] = useState(0)
  const [xpBreakdown, setXpBreakdown] = useState<EduXpBreakdownLine[]>([])
  const [gateHit, setGateHit] = useState<boolean | undefined>(undefined)
  const [gateLabelKo, setGateLabelKo] = useState<string | null>(null)
  const [levelUp, setLevelUp] = useState<EduLevelUpPayload | null>(null)
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [coachLevel, setCoachLevel] = useState<EduCoachLevelInfo>(() => eduCoachLevelByNumber(1))
  const [levelDebugAllowed, setLevelDebugAllowed] = useState(false)
  const [stanceChanged, setStanceChanged] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [composing, setComposing] = useState(false)
  const [composeFailed, setComposeFailed] = useState(false)
  const [structurePreview, setStructurePreview] = useState<EssayStructurePreview | null>(null)
  const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const [playEssayReveal, setPlayEssayReveal] = useState(false)
  const [coachChoice, setCoachChoice] = useState<CoachChoiceState>(emptyCoachChoice)
  const [structureInsight, setStructureInsight] = useState<EduStructureInsightDebug | null>(null)
  const [insightLoading, setInsightLoading] = useState(false)
  const [guideAxisIndex, setGuideAxisIndex] = useState(0)
  const [structurePulse, setStructurePulse] = useState(false)
  const [structurePulseSlot, setStructurePulseSlot] = useState<number | null>(null)
  const [structureNudgeText, setStructureNudgeText] = useState('')
  const saveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const prevGuideAxisIndex = useRef(0)
  const prevPhase = useRef('stance')
  const skipStructurePulseRef = useRef(true)
  const composeStartedRef = useRef(false)
  const comboRecordedRef = useRef(false)
  const handleComposeRef = useRef<(sid: string) => Promise<void>>(async () => {})
  const [todayComboCount, setTodayComboCount] = useState(0)

  const applySessionState = useCallback((state: Awaited<ReturnType<typeof eduApi.getSessionState>>) => {
    setQuest(state.quest)
    setProgressPct(state.progress_pct)
    setPhase(state.blueprint?.phase ?? 'stance')
    setDialogue(state.dialogue ?? [])
    const preview = state.blueprint?.essay_structure
    if (preview?.sections?.length) {
      setStructurePreview(preview as EssayStructurePreview)
    }
    if (state.blueprint?.evidence) {
      setEvidenceInput(String(state.blueprint.evidence))
    }
    setEvidenceNudgeCount(Number(state.blueprint?.evidence_nudge_count ?? 0))
    setGuideAxisIndex(Number(state.blueprint?.guide_axis_index ?? 0))
    setCoachChoice(resolveCoachChoiceFromResponse(state))
  }, [])

  const syncSessionState = useCallback(async (sid: string) => {
    const state = await eduApi.getSessionState(sid)
    applySessionState(state)
    return state
  }, [applySessionState])

  const init = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      let sid = ''
      let tierData: EduTierProgress | null = null

      if (questIdParam) {
        const started = await eduApi.startSession(questIdParam)
        sid = started.session_id
        const today = await eduApi.todayQuest().catch(() => null)
        tierData = today?.tier ?? null
      } else {
        const today = await eduApi.todayQuest()
        setQuest(today.quest)
        tierData = today.tier ?? null

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

      setTier(tierData)
      try {
        const progress = await eduApi.tierProgress()
        setTier(progress.tier)
        setCoachLevel(progress.coach_level ?? eduCoachLevelByNumber(1))
        setLevelDebugAllowed(progress.level_debug_allowed ?? false)
      } catch {
        /* guest or offline — default L1 */
      }
      setSessionId(sid)

      const state = await eduApi.getSessionState(sid)
      applySessionState(state)

      if (state.stage === 'completed' && state.essay) {
        setCompleted(true)
        comboRecordedRef.current = true
        setTodayComboCount(getTodayComboCount())
        setSaveStatus('saved')
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
        setStanceChanged(state.essay.stance_changed)
        setProgressPct(100)
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '초기화 실패')
    } finally {
      setLoading(false)
    }
  }, [questIdParam, applySessionState])

  useEffect(() => {
    if (!getEduToken()) {
      navigate('/edu')
      return
    }
    void init()
  }, [navigate, init])

  useEffect(() => {
    return () => {
      if (saveTimerRef.current) clearTimeout(saveTimerRef.current)
    }
  }, [])

  useEffect(() => {
    composeStartedRef.current = false
    setComposeFailed(false)
  }, [sessionId])

  useEffect(() => {
    if (phase !== 'guide_axis') {
      setCoachChoice(emptyCoachChoice())
    }
  }, [phase])

  useEffect(() => {
    if (!insightDebug || !completed || !sessionId || structureInsight) return
    let cancelled = false
    setInsightLoading(true)
    eduApi
      .getStructureInsight(sessionId)
      .then((res) => {
        if (!cancelled) setStructureInsight(res.structure_insight)
      })
      .catch(() => {
        if (!cancelled) setStructureInsight(null)
      })
      .finally(() => {
        if (!cancelled) setInsightLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [insightDebug, completed, sessionId, structureInsight])

  useEffect(() => {
    if (phase !== 'guide_axis') return
    if (skipStructurePulseRef.current) {
      prevGuideAxisIndex.current = guideAxisIndex
      return
    }
    if (guideAxisIndex <= prevGuideAxisIndex.current || guideAxisIndex <= 0) {
      prevGuideAxisIndex.current = guideAxisIndex
      return
    }
    const filledAxis = guideAxisIndex - 1
    const t = triggerStructureBarPulse(
      2,
      structureNudgeForAxisPass(filledAxis),
      setStructurePulse,
      setStructurePulseSlot,
      setStructureNudgeText
    )
    prevGuideAxisIndex.current = guideAxisIndex
    return () => clearTimeout(t)
  }, [guideAxisIndex, phase])

  useEffect(() => {
    if (skipStructurePulseRef.current) {
      prevPhase.current = phase
      prevGuideAxisIndex.current = guideAxisIndex
      skipStructurePulseRef.current = false
      return
    }
    const previous = prevPhase.current
    if (previous && previous !== phase) {
      const slot = completedSlotOnPhaseExit(previous)
      if (slot !== null) {
        const t = triggerStructureBarPulse(
          slot,
          structureNudgeForPhaseSlot(slot),
          setStructurePulse,
          setStructurePulseSlot,
          setStructureNudgeText
        )
        prevPhase.current = phase
        return () => clearTimeout(t)
      }
    }
    prevPhase.current = phase
  }, [phase, guideAxisIndex])

  const coachIndex = lastAssistantDialogueIndex(dialogue)

  useEffect(() => {
    if (!sessionId || completed || coachIndex < 0) {
      setCoachChoice(emptyCoachChoice())
      return
    }
    const cached = readCachedCoachChoice(sessionId, coachIndex)
    if (cached) {
      setCoachChoice(cached)
    }
  }, [sessionId, coachIndex, completed])

  useEffect(() => {
    if (!sessionId || coachIndex < 0) return
    writeCachedCoachChoice(sessionId, coachIndex, coachChoice)
  }, [sessionId, coachIndex, coachChoice])

  const appendAssistant = (content: string) => {
    setDialogue((prev) => [...prev, { role: 'assistant', content }])
  }

  const appendStudent = (content: string) => {
    setDialogue((prev) => [...prev, { role: 'student', content }])
  }

  const persistEssay = async (data: EssayArtifact) => {
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
  }

  const scheduleAutoSave = (data: EssayArtifact) => {
    if (saveTimerRef.current) clearTimeout(saveTimerRef.current)
    setSaveStatus('idle')
    saveTimerRef.current = setTimeout(() => {
      void persistEssay(data)
    }, 1500)
  }

  const handleEssayChange = (updated: EssayArtifact) => {
    setEssay(updated)
    scheduleAutoSave(updated)
  }

  const handleCoachLevelChange = async (level: number) => {
    const res = await eduApi.setCoachLevel(level)
    setCoachLevel(res.coach_level)
  }

  const handleCompose = async (sid: string) => {
    setComposing(true)
    setComposeFailed(false)
    setError('')
    try {
      const res = await eduApi.composeEssay(sid)
      setCompleted(true)
      if (!comboRecordedRef.current) {
        comboRecordedRef.current = true
        setTodayComboCount(recordTodayQuestCompletion())
      }
      const artifact: EssayArtifact = {
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
      }
      setEssay(artifact)
      setXpGained(res.xp_gained ?? 0)
      setXpBreakdown(res.xp_breakdown ?? [])
      setGateHit(res.gate_hit)
      setGateLabelKo(res.gate_label_ko ?? null)
      setLevelUp(res.level_up ?? null)
      if (res.tier) setTier(res.tier)
      if (res.coach_level) setCoachLevel(res.coach_level)
      if (res.level_debug_allowed != null) setLevelDebugAllowed(res.level_debug_allowed)
      setProgressPct(100)
      setSaveStatus(res.saved ? 'saved' : 'idle')
      setPlayEssayReveal(true)
      if (res.structure_insight) {
        setStructureInsight(res.structure_insight)
      }
      appendAssistant(
        res.title
          ? `네 글이 완성됐고 자동으로 저장됐어! 아래에서 읽고 필요하면 고쳐봐.`
          : '글을 완성하고 저장했어! 필요하면 아래에서 고쳐봐.'
      )
    } catch (e) {
      composeStartedRef.current = false
      setComposeFailed(true)
      setError(e instanceof Error ? e.message : '글 생성 실패')
    } finally {
      setComposing(false)
    }
  }
  handleComposeRef.current = handleCompose

  const handleComposeRetry = () => {
    if (!sessionId || composing || completed) return
    composeStartedRef.current = true
    void handleCompose(sessionId)
  }

  useEffect(() => {
    if (!sessionId || loading || completed || composing || composeStartedRef.current) return
    if (phase !== 'compose') return
    composeStartedRef.current = true
    void handleComposeRef.current(sessionId)
  }, [sessionId, loading, completed, composing, phase])

  const handleChatResponse = async (
    res: Awaited<ReturnType<typeof eduApi.sendChat>>,
    sid: string
  ) => {
    if (res.stance_changed) setStanceChanged(true)
    if (res.structure_preview?.sections?.length) {
      setStructurePreview(res.structure_preview as EssayStructurePreview)
    }
    const nextChoice = resolveCoachChoiceFromResponse(res)
    setCoachChoice(nextChoice)
    if (res.phase) setPhase(res.phase)
    else if (res.blueprint?.phase) setPhase(String(res.blueprint.phase))
    if (res.progress_pct != null) setProgressPct(res.progress_pct)
    if (res.blueprint?.guide_axis_index != null) {
      setGuideAxisIndex(Number(res.blueprint.guide_axis_index))
    }

    const triggerCompose = shouldTriggerEduCompose(res)
    if (triggerCompose) {
      composeStartedRef.current = true
      await handleCompose(sid)
    }

    try {
      await syncSessionState(sid)
    } catch (e) {
      setError(e instanceof Error ? e.message : '상태 동기화 실패')
    }
    return nextChoice
  }

  const handleSubmitOpening = async () => {
    if (!input.trim() || !sessionId || sending || completed) return
    if (resolveStanceEntryChatAction(resolveQuestEntryMode(quest)) !== 'submit_opening') return
    const msg = input.trim()
    setInput('')
    setSending(true)
    setError('')
    appendStudent(msg)
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'submit_opening', message: msg })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const handleStance = async (stance: 'pro' | 'con') => {
    if (!sessionId || !quest) return
    if (resolveStanceEntryChatAction(resolveQuestEntryMode(quest)) !== 'select_stance') return
    setSending(true)
    setError('')
    const label = stance === 'pro' ? '찬성' : '반대'
    const line = stance === 'pro' ? quest.pro_line : quest.con_line
    appendStudent(`${label}: ${line}`)
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'select_stance', stance })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const handleConfirmReflection = async () => {
    if (!sessionId || sending || composing) return
    setSending(true)
    setError('')
    appendStudent('맞아')
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'confirm_reflection' })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const handleSend = async () => {
    if (!input.trim() || !sessionId || sending || completed || phase === 'evidence') return
    const msg = input.trim()
    setInput('')
    setSending(true)
    setError('')
    appendStudent(msg)
    try {
      const res = await eduApi.sendChat(sessionId, { message: msg })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const handleChoiceSelect = async (choice: string) => {
    const label = choice.trim()
    if (!label || !sessionId || sending || completed) return
    setCoachChoice(emptyCoachChoice())
    setSending(true)
    setError('')
    appendStudent(label)
    try {
      const res = await eduApi.sendChat(sessionId, { message: label })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const handleSubmitEvidence = async () => {
    const msg = evidenceInput.trim()
    if (!msg || !sessionId || sending || completed || phase !== 'evidence') {
      if (!msg && phase === 'evidence') {
        setError('기사에서 본 내용을 먼저 적어줘.')
      }
      return
    }
    setSending(true)
    setError('')
    appendStudent(msg)
    try {
      const res = await eduApi.sendChat(sessionId, { message: msg })
      await handleChatResponse(res, sessionId)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const switchToChatMode = () => {
    setEduCoachUiMode('chat')
    navigate(eduQuestPathWithUi(questIdParam || quest?.quest_id, 'chat'))
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white text-[#1a1a1a]">
        불러오는 중…
      </div>
    )
  }

  const authorName = getEduDisplayName() ?? '나'
  const entryMode = resolveQuestEntryMode(quest)
  const footerMode = resolveQuestFooterMode(phase, entryMode, dialogue.length)
  const coachTurn = coachIndex >= 0 ? dialogue[coachIndex] : null
  const evidenceLen = evidenceInput.trim().length
  const evidenceReady = evidenceLen > 0
  const showCoachChoiceButtons =
    phase === 'guide_axis' &&
    footerMode === 'chat' &&
    coachChoice.active &&
    coachChoice.options.length > 0
  const showNarrativeInput =
    footerMode === 'chat' || footerMode === 'opening' || footerMode === 'evidence'

  const isWaiting = sending || composing
  const lastStudentAnswer = (() => {
    for (let i = dialogue.length - 1; i >= 0; i--) {
      if (dialogue[i].role === 'student') return dialogue[i].content
    }
    return null
  })()
  const waitingLabel = composing ? '네 글을 만들고 있어…' : '코치가 읽는 중...'

  const cardKey = completed
    ? 'completed'
    : `${phase}-${coachIndex}-${dialogue.length}-${footerMode ?? 'none'}-${showCoachChoiceButtons ? coachChoice.options.join('|') : 'text'}`

  let cardQuestion = ''
  let cardSnippets: Array<{ display: string; value: string }> = []

  if (phase === 'stance' && dialogue.length === 0 && quest) {
    if (entryMode === 'open_response') {
      cardQuestion = quest.hook_short || quest.hook_full || quest.conflict_summary || quest.quest_title
    } else {
      cardQuestion = '오늘의 입장을 골라줘.'
    }
  } else if (coachTurn) {
    const parsed = parseCoachCardContent(coachTurn.content)
    cardQuestion = parsed.question
    cardSnippets = parsed.snippets
  }

  const displayQuestion =
    showCoachChoiceButtons && coachChoice.questionText
      ? coachChoice.questionText
      : cardQuestion
  const displayQuestionParagraphs = splitCoachParagraphs(displayQuestion)
  const showNarrativeLayout = showNarrativeInput && !showCoachChoiceButtons

  const handlePrimaryAction = () => {
    if (footerMode === 'opening') return handleSubmitOpening()
    if (footerMode === 'evidence') return handleSubmitEvidence()
    if (footerMode === 'reflection') return handleConfirmReflection()
    return handleSend()
  }

  const primaryLabel =
    footerMode === 'opening'
      ? sending
        ? '보내는 중…'
        : '다음'
      : footerMode === 'evidence'
        ? sending
          ? '보내는 중…'
          : evidenceNudgeCount > 0
            ? '다시 제출'
            : '다음'
        : footerMode === 'reflection'
          ? sending || composing
            ? '처리 중…'
            : '맞아 — 글 만들기'
          : sending
            ? '보내는 중…'
            : '다음'

  const primaryDisabled =
    sending ||
    composing ||
    showCoachChoiceButtons ||
    (footerMode === 'opening' && !input.trim()) ||
    (footerMode === 'evidence' && !evidenceReady) ||
    (footerMode === 'chat' && !input.trim())

  const keyboardOpen = keyboardInset > 40 || inputFocused
  const inputRows = keyboardOpen ? 2 : footerMode === 'opening' ? 3 : 2
  const inputMaxHeight = keyboardOpen ? '4.75rem' : footerMode === 'opening' ? '7rem' : '5.5rem'
  const snippetMaxHeight = keyboardOpen
    ? showNarrativeLayout
      ? '12vh'
      : '22vh'
    : '28vh'
  const questionFontSize =
    phase === 'hammer'
      ? keyboardOpen
        ? '0.85rem'
        : '1rem'
      : keyboardOpen
        ? '1.0625rem'
        : '1.25rem'

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
        className={`shrink-0 border-b ${PAGE_MAX} mx-auto w-full`}
        style={{
          borderColor: eduGame.border,
          backgroundColor: eduGame.bg,
          paddingTop: 'max(0.375rem, env(safe-area-inset-top, 0px))',
        }}
      >
        <div className="flex items-center gap-2.5 px-4 py-2">
          <EduQuestHomeButton />
          <div className="min-w-0 flex-1">
            {quest && (
              <p
                className="text-sm font-bold truncate leading-snug"
                style={{ color: eduGame.ink }}
              >
                {quest.quest_title}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={switchToChatMode}
            className="shrink-0 px-2.5 py-1.5 rounded-lg text-xs font-medium border touch-manipulation"
            style={{
              borderColor: eduGame.border,
              color: eduGame.muted,
              backgroundColor: eduGame.surface,
            }}
          >
            채팅
          </button>
        </div>
        <div className="flex items-center gap-2 px-4 pb-2.5">
          <div
            className="flex-1 h-2 rounded-full overflow-hidden"
            style={{ backgroundColor: eduGame.surface }}
            role="progressbar"
            aria-valuenow={progressPct}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label="탐구 진행"
          >
            <div
              className="h-full transition-all duration-500 rounded-full"
              style={{ width: `${progressPct}%`, backgroundColor: eduGame.primary }}
            />
          </div>
          <span
            className="text-xs whitespace-nowrap font-bold tabular-nums shrink-0"
            style={{ color: eduGame.primary, minWidth: '2.25rem', textAlign: 'right' }}
          >
            {progressPct}%
          </span>
        </div>
      </header>

      {!completed && (
        <CardStructureBar
          phase={phase}
          guideAxisIndex={guideAxisIndex}
          pulse={structurePulse}
          pulseSlot={structurePulseSlot}
          nudgeText={structureNudgeText}
          compact={keyboardOpen}
          waiting={isWaiting}
        />
      )}

      {completed ? (
        <main className={`flex-1 min-h-0 overflow-y-auto ${eduGameClasses.chatScroll} ${PAGE_MAX} mx-auto w-full px-4 py-4 space-y-4`}>
          <EduQuestCompletionCelebration
            xpGained={xpGained}
            xpBreakdown={xpBreakdown}
            gateHit={gateHit}
            gateLabelKo={gateLabelKo}
            levelUp={levelUp}
            streakDays={tier?.streak_days ?? 0}
            coachLevel={coachLevel}
            levelDebugSwitch={canShowCoachLevelDebugSwitch(levelDebugAllowed)}
            onCoachLevelChange={handleCoachLevelChange}
            tier={tier}
            active={completed}
          />
          {insightDebug && (
            <EduStructureInsightDebugPanel insight={structureInsight} loading={insightLoading} />
          )}
          {essay && (
            <EduEssayCompletionPanel
              essay={essay}
              structure={structurePreview}
              onChange={handleEssayChange}
              disabled={saveStatus === 'saving'}
              authorName={authorName}
              playReveal={playEssayReveal}
              onRevealComplete={() => setPlayEssayReveal(false)}
              saveStatus={saveStatus}
              stanceChanged={stanceChanged}
            />
          )}
          {essay && quest?.quest_id && (
            <EduQuestComboContinue
              currentQuestId={quest.quest_id}
              diversity={{ questFrame: quest.quest_frame ?? null }}
              comboCount={todayComboCount}
              uiMode="cards"
            />
          )}
          {essay && (
            <Link
                to={`/edu/share/${sessionId}`}
                className={`block w-full py-3.5 text-center ${eduGameClasses.btnPrimary}`}
                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
              >
                공유 카드 만들기
              </Link>
          )}
        </main>
      ) : (
        <div className={`flex-1 min-h-0 flex flex-col overflow-hidden ${PAGE_MAX} mx-auto w-full`}>
          <AnimatePresence mode="wait">
            <motion.div
              key={cardKey}
              initial={{ opacity: 0, x: 48 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: -48 }}
              transition={{ duration: 0.22, ease: 'easeOut' }}
              className="flex-1 min-h-0 flex flex-col overflow-hidden"
            >
              {isWaiting ? (
                <div className="flex-1 min-h-0 flex flex-col justify-center px-4 py-6">
                  <EduCoachWaitingPanel
                    studentAnswer={lastStudentAnswer}
                    label={waitingLabel}
                  />
                </div>
              ) : (
                <>
              {/* 질문·fact — 서술형도 카드 상단에 전체 노출 (절대 한 줄 자르지 않음) */}
              <div className="shrink-0 px-4 pt-3 pb-2">
                <div className="space-y-4">
                  {displayQuestionParagraphs.map((paragraph, i) => (
                    <p
                      key={`${cardKey}-q-${i}`}
                      className={`text-center font-bold ${eduGameClasses.textKoPre}`}
                      style={{
                        fontSize: questionFontSize,
                        lineHeight: 1.55,
                        color: eduGame.ink,
                      }}
                    >
                      <CoachMessageText text={paragraph} />
                    </p>
                  ))}
                </div>

                {cardSnippets.length > 0 && (
                  <div
                    className="mt-3 space-y-2 overflow-y-auto"
                    style={{ maxHeight: snippetMaxHeight }}
                  >
                    {cardSnippets.map((snip, i) => (
                      <EduArticleSnippetCard
                        key={`${cardKey}-snip-${i}`}
                        text={snip.value}
                        display={snip.display}
                      />
                    ))}
                  </div>
                )}

                {footerMode === 'stance_pick' && quest && dialogue.length === 0 && (
                  <div className="mt-3 space-y-2">
                    <button
                      type="button"
                      disabled={sending}
                      onClick={() => void handleStance('pro')}
                      className={`w-full text-left border-2 rounded-xl p-4 ${eduGameClasses.textKo}`}
                      style={{ borderColor: eduGame.primary, fontSize: eduGame.fontSize.body }}
                    >
                      <span className="font-bold block mb-1" style={{ color: eduGame.primary }}>
                        찬성
                      </span>
                      {quest.pro_line}
                    </button>
                    <button
                      type="button"
                      disabled={sending}
                      onClick={() => void handleStance('con')}
                      className={`w-full text-left border-2 rounded-xl p-4 ${eduGameClasses.textKo}`}
                      style={{ borderColor: eduGame.ink, fontSize: eduGame.fontSize.body }}
                    >
                      <span className="font-bold block mb-1">반대</span>
                      {quest.con_line}
                    </button>
                  </div>
                )}
              </div>

              {/* 남는 세로 공간 — 키보드 시 여기만 줄어듦 (질문 영역 침범 방지) */}
              <div className="flex-1 min-h-0" aria-hidden />

              {footerMode && footerMode !== 'stance_pick' && (
                <footer
                  className="shrink-0 border-t px-4 w-full"
                  style={{
                    borderColor: eduGame.border,
                    backgroundColor: eduGame.bg,
                    paddingTop: keyboardOpen ? '0.375rem' : '0.625rem',
                    paddingBottom: keyboardOpen
                      ? 'calc(0.375rem + env(safe-area-inset-bottom, 0px))'
                      : 'calc(0.625rem + env(safe-area-inset-bottom, 0px))',
                  }}
                >
                  <div className="space-y-2">
                    {footerMode === 'evidence' && (
                      <p className="text-center" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                        {evidenceLen}자
                        {evidenceLen > 0 && evidenceLen < EVIDENCE_RECOMMENDED_LEN
                          ? ` · ${EVIDENCE_RECOMMENDED_LEN}자 이상이면 좋아요`
                          : ''}
                      </p>
                    )}

                    {footerMode === 'reflection' ? null : showCoachChoiceButtons ? (
                      <div className="space-y-2.5" role="group" aria-label="입장 선택">
                        {coachChoice.options.map((option, i) => (
                          <button
                            key={`${cardKey}-choice-${i}`}
                            type="button"
                            disabled={sending || composing}
                            onClick={() => void handleChoiceSelect(option)}
                            className={`w-full py-4 px-4 rounded-2xl font-bold border-2 text-center active:scale-[0.98] transition-transform disabled:opacity-40 disabled:active:scale-100 ${eduGameClasses.textKo}`}
                            style={{
                              fontSize: '1.125rem',
                              lineHeight: 1.45,
                              borderColor: eduGame.primary,
                              backgroundColor: i === 0 ? eduGame.primary : eduGame.bg,
                              color: i === 0 ? eduGame.bg : eduGame.ink,
                              boxShadow: i === 0 ? `0 2px 0 ${eduGame.primaryDark}59` : `0 2px 0 ${eduGame.border}`,
                            }}
                          >
                            {option}
                          </button>
                        ))}
                      </div>
                    ) : showNarrativeInput ? (
                      footerMode === 'evidence' ? (
                      <textarea
                        value={evidenceInput}
                        onChange={(e) => {
                          setEvidenceInput(e.target.value)
                          if (error) setError('')
                        }}
                        onFocus={() => setInputFocused(true)}
                        onBlur={() => window.setTimeout(() => setInputFocused(false), 100)}
                        placeholder="기사에서 본 구체적인 사실을 적어줘…"
                        disabled={sending || composing}
                        rows={inputRows}
                        className={`w-full resize-none overflow-y-auto ${eduGameClasses.input}`}
                        style={{
                          borderColor: eduGame.border,
                          fontSize: eduGame.fontSize.body,
                          lineHeight: eduGame.lineHeight.body,
                          maxHeight: inputMaxHeight,
                        }}
                      />
                    ) : (
                      <textarea
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onFocus={() => setInputFocused(true)}
                        onBlur={() => window.setTimeout(() => setInputFocused(false), 100)}
                        placeholder={
                          phase === 'hammer'
                            ? '다른 시각을 듣고 — 네 생각을 한두 문장으로…'
                            : '네 생각을 입력해…'
                        }
                        disabled={sending || composing}
                        rows={inputRows}
                        className={`w-full resize-none overflow-y-auto ${eduGameClasses.input}`}
                        style={{
                          borderColor: eduGame.border,
                          fontSize: eduGame.fontSize.body,
                          lineHeight: eduGame.lineHeight.body,
                          maxHeight: inputMaxHeight,
                        }}
                      />
                    )
                    ) : null}

                    {error && (
                      <div className="text-sm text-red-600 text-center space-y-2">
                        <p>{error}</p>
                        {composeFailed && phase === 'compose' && !completed && sessionId && (
                          <button
                            type="button"
                            onClick={() => void handleComposeRetry()}
                            disabled={composing}
                            className={`w-full py-2.5 rounded-lg font-medium ${eduGameClasses.btnPrimary}`}
                            style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
                          >
                            글 다시 만들기
                          </button>
                        )}
                      </div>
                    )}

                    {!showCoachChoiceButtons && (
                      <button
                        type="button"
                        onClick={() => void handlePrimaryAction()}
                        disabled={primaryDisabled}
                        className={`w-full py-3 ${eduGameClasses.btnPrimary}`}
                        style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
                      >
                        {primaryLabel}
                      </button>
                    )}
                  </div>
                </footer>
              )}

              {footerMode === 'stance_pick' && error && (
                <p className="shrink-0 px-4 pb-3 text-sm text-red-600 text-center">{error}</p>
              )}
                </>
              )}
            </motion.div>
          </AnimatePresence>
        </div>
      )}
    </div>
  )
}
