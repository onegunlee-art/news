import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import EssayRevealWrapper from '../../components/edu/EssayRevealWrapper'
import { type EssayArtifact } from '../../components/edu/EssayRevealCard'
import StructurePreviewCard, { type EssayStructurePreview } from '../../components/edu/StructurePreviewCard'
import EduQuestCompletionCelebration from '../../components/edu/EduQuestCompletionCelebration'
import EduStructureReviewCard from '../../components/edu/EduStructureReviewCard'
import TypingIndicator from '../../components/edu/TypingIndicator'
import TypewriterText from '../../components/edu/TypewriterText'
import EduArticleCard from '../../components/edu/EduArticleCard'
import EduArticleSnippetCard from '../../components/edu/EduArticleSnippetCard'
import {
  coachMessageHasSnippet,
  parseCoachAssistantMessage,
} from '../../utils/eduCoachMessageParse'
import {
  eduApi,
  getEduToken,
  getEduDisplayName,
  type EduDialogueTurn,
  type EduQuest,
  type EduQuestArticle,
  type EduTierProgress,
} from '../../services/eduApi'
import { EDU_BRAND } from '../../constants/eduBrand'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

const PAGE_MAX = 'max-w-2xl'
const GUIDE_AXIS_SLOTS = 3
/** 탐구 바 3칸 — 글 구조 (축 이름 노출 대신 단계만) */
const EXPLORE_BAR_SLOT_LABELS = ['서론', '본론', '결론'] as const
/** 축 통과 순간 — 행동 격려만 (평가·정답 금지) */
const EXPLORE_PASS_NUDGES = ['한 갈래 따졌어!', '한 갈래 더 따졌어!', '다음 갈래로!'] as const
const EVIDENCE_RECOMMENDED_LEN = 20
const ARTICLE_PHASES: readonly string[] = ['evidence']
/** axis_guide·hammer·reflection: 기사는 코치 snippet으로만. 하단 참고 기사(펼치기) 제거 — 미팅 안정 */

/** Footer input mode — exactly one UI per phase (no overlapping blocks). */
type QuestFooterMode = 'opening' | 'evidence' | 'reflection' | 'chat'

type QuestEntryMode = 'open_response' | 'stance_pick'
type StanceEntryChatAction = 'submit_opening' | 'select_stance'

/** P1-2n: entry_mode derive first, quest_frame fallback (behavior 0 vs frame-only) */
function resolveQuestEntryMode(quest: EduQuest | null | undefined): QuestEntryMode {
  if (!quest) return 'stance_pick'
  if (quest.entry_mode === 'open_response' || quest.entry_mode === 'stance_pick') {
    return quest.entry_mode
  }
  return quest.quest_frame === 'myth_bust' ? 'open_response' : 'stance_pick'
}

/** P1-2n: stance-phase FSM entry action (chat.php aliases unchanged) */
function resolveStanceEntryChatAction(entryMode: QuestEntryMode): StanceEntryChatAction {
  return entryMode === 'open_response' ? 'submit_opening' : 'select_stance'
}

function resolveQuestFooterMode(phase: string, entryMode: QuestEntryMode): QuestFooterMode | null {
  if (phase === 'stance') return entryMode === 'open_response' ? 'opening' : null
  if (phase === 'evidence') return 'evidence'
  if (phase === 'reflection') return 'reflection'
  if (phase === 'reasoning' || phase === 'hammer') return 'chat'
  if (phase === 'guide_axis' || phase === 'guide_conclusion') return 'chat'
  return null
}

/** 마지막 코치(assistant) 턴 — 상단 고정 질문 패널용 (로직 변경 없음) */
function lastAssistantDialogueIndex(dialogue: EduDialogueTurn[]): number {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'assistant') return i
  }
  return -1
}

/** visualViewport — 모바일 키보드 가시 영역 (단일 보정: shell height+top만) */
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

export default function QuestFlowChat() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const bottomRef = useRef<HTMLDivElement>(null)
  const mainScrollRef = useRef<HTMLDivElement>(null)
  const pinScrollRef = useRef<HTMLDivElement>(null)
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
  const [articles, setArticles] = useState<EduQuestArticle[]>([])
  const [completed, setCompleted] = useState(false)
  const [essay, setEssay] = useState<EssayArtifact | null>(null)
  const [xpGained, setXpGained] = useState(0)
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [stanceChanged, setStanceChanged] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [composing, setComposing] = useState(false)
  const [structurePreview, setStructurePreview] = useState<EssayStructurePreview | null>(null)
  const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const [thinkingExpanded, setThinkingExpanded] = useState(false)
  const [playEssayReveal, setPlayEssayReveal] = useState(false)
  const [typingBubbleIndex, setTypingBubbleIndex] = useState<number | null>(null)
  const [guideAxisIndex, setGuideAxisIndex] = useState(0)
  const [explorePulse, setExplorePulse] = useState(false)
  const [explorePulseSlot, setExplorePulseSlot] = useState<number | null>(null)
  const [exploreNudgeText, setExploreNudgeText] = useState('')
  const prevGuideAxisIndex = useRef(0)
  const saveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const selectedQuestId = searchParams.get('quest_id')?.trim() || ''

  const blurActiveInput = () => {
    if (document.activeElement instanceof HTMLElement) {
      document.activeElement.blur()
    }
  }

  const applySessionState = useCallback((state: Awaited<ReturnType<typeof eduApi.getSessionState>>) => {
    setQuest(state.quest)
    setProgressPct(state.progress_pct)
    setPhase(state.blueprint?.phase ?? 'stance')
    setDialogue(state.dialogue ?? [])
    if (state.quest?.articles?.length) {
      setArticles(state.quest.articles)
    }
    const preview = state.blueprint?.essay_structure
    if (preview?.sections?.length) {
      setStructurePreview(preview as EssayStructurePreview)
    }
    if (state.blueprint?.evidence) {
      setEvidenceInput(String(state.blueprint.evidence))
    }
    setEvidenceNudgeCount(Number(state.blueprint?.evidence_nudge_count ?? 0))
    setGuideAxisIndex(Number(state.blueprint?.guide_axis_index ?? 0))
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

      if (selectedQuestId) {
        const started = await eduApi.startSession(selectedQuestId)
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
      setSessionId(sid)

      const state = await eduApi.getSessionState(sid)
      applySessionState(state)

      if (state.stage === 'completed' && state.essay) {
        setCompleted(true)
        setSaveStatus('saved')
        setEssay({
          title: state.essay.title,
          subtitle: state.essay.subtitle,
          sections: state.essay.sections,
          conclusion_heading: state.essay.conclusion_heading,
          conclusion_paragraphs: state.essay.conclusion_paragraphs,
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
  }, [selectedQuestId, applySessionState])

  useEffect(() => {
    if (!getEduToken()) {
      navigate('/edu')
      return
    }
    void init()
  }, [navigate, init])

  useEffect(() => {
    const el = mainScrollRef.current
    if (!el) return
    el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
  }, [dialogue, completed, composing, sending, typingBubbleIndex])

  const scrollToBottom = () => {
    const pinEl = pinScrollRef.current
    const mainEl = mainScrollRef.current
    if (pinEl && pinEl.scrollHeight > pinEl.clientHeight) {
      pinEl.scrollTo({ top: pinEl.scrollHeight, behavior: 'auto' })
    } else if (mainEl) {
      mainEl.scrollTo({ top: mainEl.scrollHeight, behavior: 'auto' })
    }
  }

  const handleComposeInputFocus = () => setInputFocused(true)
  const handleComposeInputBlur = () => {
    window.setTimeout(() => setInputFocused(false), 100)
  }

  useEffect(() => {
    return () => {
      if (saveTimerRef.current) clearTimeout(saveTimerRef.current)
    }
  }, [])

  useEffect(() => {
    if (guideAxisIndex > prevGuideAxisIndex.current && guideAxisIndex > 0) {
      const filledSlot = guideAxisIndex - 1
      setExplorePulse(true)
      setExplorePulseSlot(filledSlot)
      setExploreNudgeText(EXPLORE_PASS_NUDGES[Math.min(filledSlot, EXPLORE_PASS_NUDGES.length - 1)])
      const t = setTimeout(() => {
        setExplorePulse(false)
        setExplorePulseSlot(null)
        setExploreNudgeText('')
      }, 900)
      prevGuideAxisIndex.current = guideAxisIndex
      return () => clearTimeout(t)
    }
    prevGuideAxisIndex.current = guideAxisIndex
  }, [guideAxisIndex])

  const appendAssistant = (content: string, animate = true) => {
    setDialogue((prev) => {
      if (animate) {
        const nextIndex = prev.length
        requestAnimationFrame(() => setTypingBubbleIndex(nextIndex))
      }
      return [...prev, { role: 'assistant', content }]
    })
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
        hero_sentence: data.hero_sentence,
        full_text: data.full_text,
      })
      setEssay({
        title: res.title,
        subtitle: res.subtitle,
        sections: res.sections,
        conclusion_heading: res.conclusion_heading,
        conclusion_paragraphs: res.conclusion_paragraphs,
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

  const handleCompose = async (sid: string) => {
    setComposing(true)
    setError('')
    try {
      const res = await eduApi.composeEssay(sid)
      setCompleted(true)
      const artifact: EssayArtifact = {
        title: res.title,
        subtitle: res.subtitle,
        sections: res.sections,
        conclusion_heading: res.conclusion_heading,
        conclusion_paragraphs: res.conclusion_paragraphs,
        full_text: res.full_text ?? '',
        hero_sentence: res.hero_sentence ?? null,
        feedback: res.feedback ?? null,
      }
      setEssay(artifact)
      setXpGained(res.xp_gained ?? 0)
      if (res.tier) setTier(res.tier)
      setProgressPct(100)
      setSaveStatus(res.saved ? 'saved' : 'idle')
      setPlayEssayReveal(true)
      appendAssistant(
        res.title
          ? `네 글이 완성됐고 자동으로 저장됐어! 아래에서 읽고 필요하면 고쳐봐.`
          : '글을 완성하고 저장했어! 필요하면 아래에서 고쳐봐.'
      )
    } catch (e) {
      setError(e instanceof Error ? e.message : '글 생성 실패')
    } finally {
      setComposing(false)
    }
  }

  const handleChatResponse = async (
    res: Awaited<ReturnType<typeof eduApi.sendChat>>,
    sid: string
  ) => {
    if (res.stance_changed) setStanceChanged(true)
    if (res.articles?.length) setArticles(res.articles)
    if (res.structure_preview?.sections?.length) {
      setStructurePreview(res.structure_preview as EssayStructurePreview)
    }
    await syncSessionState(sid)
    if (res.should_compose) {
      await handleCompose(sid)
    }
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
    if (phase === 'stance' && resolveStanceEntryChatAction(resolveQuestEntryMode(quest)) === 'submit_opening') return
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

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white text-[#1a1a1a]">
        불러오는 중…
      </div>
    )
  }

  const studentTurnCount = dialogue.filter((t) => t.role === 'student').length
  const lastTurn = dialogue[dialogue.length - 1]
  const authorName = getEduDisplayName() ?? '나'
  const entryMode = resolveQuestEntryMode(quest)
  const footerMode = resolveQuestFooterMode(phase, entryMode)
  const showGuideAxisBar =
    !completed && (phase === 'guide_axis' || phase === 'guide_conclusion')
  const guideAxisCompleted =
    phase === 'guide_conclusion' ? GUIDE_AXIS_SLOTS : Math.min(GUIDE_AXIS_SLOTS, guideAxisIndex)
  const guideAxisCurrentSlot =
    phase === 'guide_conclusion' || guideAxisIndex >= GUIDE_AXIS_SLOTS ? -1 : guideAxisIndex
  const showArticles = articles.length > 0 && !completed && ARTICLE_PHASES.includes(phase)
  const evidenceLen = evidenceInput.trim().length
  const evidenceReady = evidenceLen > 0

  const pinCoachUi = !completed && footerMode !== null
  const pinnedCoachIndex = pinCoachUi ? lastAssistantDialogueIndex(dialogue) : -1
  const pinnedCoachTurn = pinnedCoachIndex >= 0 ? dialogue[pinnedCoachIndex] : null
  const showPinnedCoach = pinnedCoachTurn !== null
  const historyDialogue = dialogue
    .map((turn, i) => ({ turn, i }))
    .filter(({ i }) => !showPinnedCoach || i !== pinnedCoachIndex)

  const keyboardOpen = keyboardInset > 40
  const compactMode =
    !completed &&
    footerMode !== null &&
    (keyboardOpen || inputFocused) &&
    (showPinnedCoach || showGuideAxisBar)
  const showPinZone = !completed && (showGuideAxisBar || showPinnedCoach)

  return (
    <div
      className={`${eduGameClasses.chatShell} fixed left-0 right-0 flex flex-col`}
      style={{
        color: eduGame.ink,
        fontFamily: eduGame.fontBody,
        backgroundColor: eduGame.bg,
        top: viewportHeight != null ? viewportOffsetTop : 0,
        height: viewportHeight ?? '100dvh',
        maxHeight: viewportHeight ?? '100dvh',
      }}
    >
      <header className={`shrink-0 border-b px-4 py-3 ${PAGE_MAX} mx-auto w-full`} style={{ borderColor: eduGame.border }}>
        <div className="flex items-center justify-between gap-2">
          <Link to="/edu" className="text-xs underline" style={{ color: EDU_BRAND.muted }}>
            ← 홈
          </Link>
          <span
            className="text-xl leading-none"
            style={{ fontFamily: EDU_BRAND.fontLogo, color: EDU_BRAND.accent }}
            aria-hidden
          >
            g.
          </span>
        </div>
        {quest && (
          <div className="mt-2">
            <p className="text-xs" style={{ color: EDU_BRAND.muted }}>{quest.quest_code}</p>
            {quest.time_anchor && (
              <p className="text-[11px] mt-0.5" style={{ color: EDU_BRAND.muted }}>
                {quest.time_anchor}
              </p>
            )}
            <h1 className="text-base font-bold leading-snug">{quest.quest_title}</h1>
          </div>
        )}
        <div className="mt-2 flex items-center gap-2">
          <div className="flex-1 h-2 rounded-full overflow-hidden" style={{ backgroundColor: eduGame.border }}>
            <div
              className="h-full transition-all duration-500 rounded-full"
              style={{ width: `${progressPct}%`, backgroundColor: eduGame.primary }}
            />
          </div>
          <span className="text-[10px] whitespace-nowrap font-medium" style={{ color: eduGame.muted }}>
            생각 정리 {progressPct}%
          </span>
        </div>
      </header>

      {showPinZone && (
        <div
          ref={pinScrollRef}
          className={`z-20 border-b ${compactMode ? `flex-1 min-h-0 overflow-y-auto ${eduGameClasses.chatScroll}` : 'shrink-0'}`}
          style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
        >
          <div className={`${PAGE_MAX} mx-auto w-full px-4`}>
            {showGuideAxisBar && (
              <div className="pt-2 pb-1 shrink-0">
                <AxisExploreBar
                  completed={guideAxisCompleted}
                  currentSlot={guideAxisCurrentSlot}
                  pulse={explorePulse}
                  pulseSlot={explorePulseSlot}
                  nudgeText={exploreNudgeText}
                />
              </div>
            )}
            {showPinnedCoach && pinnedCoachTurn && (
              <div className={`${showGuideAxisBar ? 'pb-2' : 'py-2'}`}>
                <DialogueBubble
                  turn={pinnedCoachTurn}
                  typewriter={pinnedCoachIndex === typingBubbleIndex}
                  coachEnter={pinnedCoachIndex === typingBubbleIndex}
                  onTypewriterComplete={() => setTypingBubbleIndex(null)}
                  onTypewriterProgress={scrollToBottom}
                />
              </div>
            )}
          </div>
        </div>
      )}

      <main
        ref={mainScrollRef}
        className={`${compactMode ? 'hidden' : 'flex-1 min-h-0'} ${eduGameClasses.chatScroll} ${PAGE_MAX} mx-auto w-full px-4 py-4 overflow-y-auto space-y-3`}
      >
        {phase === 'stance' && dialogue.length === 0 && quest && !completed && entryMode === 'stance_pick' && (
          <section className="space-y-3 mb-4">
            <p className={eduGameClasses.textKo} style={{ fontSize: eduGame.fontSize.body, lineHeight: eduGame.lineHeight.body, color: eduGame.muted }}>
              오늘의 입장을 선택하세요.
            </p>
            <button
              type="button"
              disabled={sending}
              onClick={() => handleStance('pro')}
              className={`w-full text-left border-2 rounded-xl p-4 hover:bg-[#f8f8f8] ${eduGameClasses.textKo}`}
              style={{ borderColor: eduGame.ink, fontSize: eduGame.fontSize.body, lineHeight: eduGame.lineHeight.body }}
            >
              <span className="font-bold block mb-1" style={{ fontSize: eduGame.fontSize.label }}>
                찬성
              </span>
              {quest.pro_line}
            </button>
            <button
              type="button"
              disabled={sending}
              onClick={() => handleStance('con')}
              className={`w-full text-left border-2 rounded-xl p-4 hover:bg-[#f8f8f8] ${eduGameClasses.textKo}`}
              style={{ borderColor: eduGame.ink, fontSize: eduGame.fontSize.body, lineHeight: eduGame.lineHeight.body }}
            >
              <span className="font-bold block mb-1" style={{ fontSize: eduGame.fontSize.label }}>
                반대
              </span>
              {quest.con_line}
            </button>
          </section>
        )}

        {phase === 'stance' && dialogue.length === 0 && quest && !completed && entryMode === 'open_response' && (
          <section className="space-y-3 mb-4">
            {(quest.hook_short || quest.hook_full) && (
              <p
                className={`leading-relaxed ${eduGameClasses.textKoPre}`}
                style={{ fontSize: eduGame.fontSize.body, lineHeight: eduGame.lineHeight.body }}
              >
                {quest.hook_short || quest.hook_full}
              </p>
            )}
            <p className={eduGameClasses.textKo} style={{ color: EDU_BRAND.muted, fontSize: eduGame.fontSize.label }}>
              위 질문에 대해 네 생각을 자유롭게 적어줘.
            </p>
          </section>
        )}

        {!completed &&
          historyDialogue.map(({ turn, i }) => (
            <DialogueBubble
              key={`${turn.at ?? i}-${i}`}
              turn={turn}
              typewriter={turn.role === 'assistant' && i === typingBubbleIndex}
              coachEnter={turn.role === 'assistant' && i === typingBubbleIndex}
              onTypewriterComplete={() => setTypingBubbleIndex(null)}
              onTypewriterProgress={scrollToBottom}
            />
          ))}

        {!completed && sending && <TypingIndicator />}
        {!completed && composing && !sending && (
          <TypingIndicator label="네 글을 만들고 있어…" />
        )}

        {completed && (
          <EduQuestCompletionCelebration
            xpGained={xpGained}
            streakDays={tier?.streak_days ?? 0}
            tier={tier}
            active={completed}
          />
        )}

        {completed && structurePreview && (
          <EduStructureReviewCard structure={structurePreview} />
        )}

        {completed && dialogue.length > 0 && (
          <ThinkingProcessPanel
            dialogue={dialogue}
            expanded={thinkingExpanded}
            onToggle={() => setThinkingExpanded((v) => !v)}
            turnCount={studentTurnCount}
            preview={lastTurn?.content ?? ''}
          />
        )}

        {showArticles && (
          <section className="space-y-2 border border-[#ccc] rounded p-3 bg-[#fafafa]">
            <p className="text-xs font-bold">참고 기사</p>
            {articles.map((a) => (
              <EduArticleCard
                key={a.news_id}
                article={a}
                disabled={sending || composing}
                onInteract={blurActiveInput}
              />
            ))}
          </section>
        )}

        {structurePreview && !completed && (
          <StructurePreviewCard structure={structurePreview} />
        )}

        {completed && essay && (
          <section className="space-y-6 pt-2 mt-2">
            <div
              className="border-t-2 pt-8"
              style={{ borderColor: eduGame.primaryLight }}
            >
              <div className="flex items-center justify-between gap-2 mb-6">
                <p className="font-bold" style={{ fontSize: eduGame.fontSize.bodyLg, color: eduGame.primaryDark }}>
                  나만의 글
                </p>
                <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                  {saveStatus === 'saving' && '저장 중…'}
                  {saveStatus === 'saved' && '✓ 자동 저장됨'}
                  {saveStatus === 'error' && '저장 실패 — 다시 시도해줘'}
                </span>
              </div>
              {stanceChanged && (
                <span
                  className="inline-block font-bold px-3 py-1 rounded-full mb-4"
                  style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primaryDark, backgroundColor: eduGame.primaryLight }}
                >
                  생각이 바뀌었다
                </span>
              )}
              <EssayRevealWrapper
                essay={essay}
                onChange={handleEssayChange}
                disabled={saveStatus === 'saving'}
                authorName={authorName}
                playReveal={playEssayReveal}
                onRevealComplete={() => setPlayEssayReveal(false)}
              />
              {!playEssayReveal && (
                <p className={`mt-6 text-center ${eduGameClasses.textKo}`} style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>
                  고치고 싶은 부분을 탭하면 편집할 수 있어
                </p>
              )}
            </div>
            {!playEssayReveal && (
              <>
                {essay.feedback && (
                  <p
                    className={`p-4 rounded-xl ${eduGameClasses.textKo}`}
                    style={{ fontSize: eduGame.fontSize.body, color: eduGame.muted, backgroundColor: eduGame.surface, lineHeight: eduGame.lineHeight.body }}
                  >
                    {essay.feedback}
                  </p>
                )}
                <div className="space-y-2 pt-2">
                  <button
                    type="button"
                    onClick={() => essay && void persistEssay(essay)}
                    disabled={saveStatus === 'saving'}
                    className={`w-full py-3.5 border-2 rounded-xl font-bold disabled:opacity-40 ${eduGameClasses.textKo}`}
                    style={{ borderColor: eduGame.border, color: eduGame.ink, fontSize: eduGame.fontSize.button }}
                  >
                    지금 저장
                  </button>
                  <Link
                    to={`/edu/share/${sessionId}`}
                    className={`block w-full py-3.5 text-center ${eduGameClasses.btnPrimary}`}
                    style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
                  >
                    공유 카드 만들기
                  </Link>
                  <Link
                    to="/edu/profile"
                    className={`block w-full py-3.5 text-center rounded-xl font-bold border-2 ${eduGameClasses.textKo}`}
                    style={{ borderColor: eduGame.border, color: eduGame.ink, fontSize: eduGame.fontSize.button }}
                  >
                    내 글함
                  </Link>
                  <Link
                    to="/edu"
                    className={`block w-full py-3.5 text-center rounded-xl font-medium ${eduGameClasses.textKo}`}
                    style={{ color: eduGame.muted, fontSize: eduGame.fontSize.label }}
                  >
                    홈으로
                  </Link>
                </div>
              </>
            )}
          </section>
        )}

        {error && (
          <p className="text-sm text-red-600 border border-red-200 bg-red-50 p-3 rounded">{error}</p>
        )}
        <div ref={bottomRef} />
      </main>

      {!completed && footerMode !== null && (
        <footer
          className={`shrink-0 border-t px-4 py-3 ${PAGE_MAX} mx-auto w-full z-30`}
          style={{
            borderColor: eduGame.border,
            backgroundColor: eduGame.bg,
            paddingBottom: 'calc(0.75rem + env(safe-area-inset-bottom, 0px))',
          }}
        >
          {footerMode === 'opening' && (
            <div className="space-y-2">
              <textarea
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onFocus={handleComposeInputFocus}
                onBlur={handleComposeInputBlur}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault()
                    void handleSubmitOpening()
                  }
                }}
                placeholder="네 생각을 입력해…"
                disabled={sending || composing}
                rows={3}
                className={`w-full resize-none ${eduGameClasses.input}`}
                style={{
                  borderColor: eduGame.border,
                  color: eduGame.ink,
                  fontSize: eduGame.fontSize.body,
                  lineHeight: eduGame.lineHeight.body,
                }}
              />
              <button
                type="button"
                onClick={() => void handleSubmitOpening()}
                disabled={sending || composing || !input.trim()}
                className={`w-full py-3.5 ${eduGameClasses.btnPrimary}`}
                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
              >
                {sending ? '제출 중…' : '생각 보내기'}
              </button>
            </div>
          )}
          {footerMode === 'evidence' && (
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <label className="font-bold" style={{ color: eduGame.muted, fontSize: eduGame.fontSize.label }}>
                  기사에서 찾은 근거
                </label>
                <span
                  className="tabular-nums"
                  style={{
                    color: evidenceLen >= EVIDENCE_RECOMMENDED_LEN ? eduGame.primary : eduGame.muted,
                    fontSize: eduGame.fontSize.caption,
                  }}
                >
                  {evidenceLen}자
                  {evidenceLen > 0 && evidenceLen < EVIDENCE_RECOMMENDED_LEN
                    ? ` · ${EVIDENCE_RECOMMENDED_LEN}자 이상이면 좋아요`
                    : ''}
                </span>
              </div>
              <textarea
                value={evidenceInput}
                onChange={(e) => {
                  setEvidenceInput(e.target.value)
                  if (error) setError('')
                }}
                onFocus={handleComposeInputFocus}
                onBlur={handleComposeInputBlur}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault()
                    void handleSubmitEvidence()
                  }
                }}
                placeholder="기사에서 본 구체적인 사실을 적어줘…"
                disabled={sending || composing}
                rows={3}
                className={`w-full resize-none ${eduGameClasses.input}`}
                style={{
                  borderColor: eduGame.border,
                  color: eduGame.ink,
                  fontSize: eduGame.fontSize.body,
                  lineHeight: eduGame.lineHeight.body,
                }}
              />
              <button
                type="button"
                onClick={() => void handleSubmitEvidence()}
                disabled={sending || composing || !evidenceReady}
                className={`w-full py-3.5 ${eduGameClasses.btnPrimary}`}
                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
              >
                {sending ? '제출 중…' : evidenceNudgeCount > 0 ? '다시 제출 — 다음 단계로' : '근거 제출'}
              </button>
              {evidenceNudgeCount > 0 && (
                <p className={`text-center ${eduGameClasses.textKo}`} style={{ color: eduGame.muted, fontSize: eduGame.fontSize.caption }}>
                  코치가 더 구체적으로 물어봤어요. 15자 이상으로 다시 제출하면 반론 단계로 넘어가요.
                </p>
              )}
            </div>
          )}
          {footerMode === 'reflection' && (
            <div className="flex gap-2">
              <button
                type="button"
                onClick={handleConfirmReflection}
                disabled={sending || composing}
                className={`flex-1 py-3.5 ${eduGameClasses.btnPrimary}`}
                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
              >
                맞아 — 글 만들기
              </button>
            </div>
          )}
          {footerMode === 'chat' && (
            <div className="flex gap-2 items-stretch">
              <input
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onFocus={handleComposeInputFocus}
                onBlur={handleComposeInputBlur}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    void handleSend()
                  }
                }}
                placeholder="네 생각을 입력해…"
                disabled={sending || composing}
                className={`flex-1 min-w-0 ${eduGameClasses.input}`}
                style={{
                  borderColor: eduGame.border,
                  color: eduGame.ink,
                  fontSize: eduGame.fontSize.body,
                  lineHeight: eduGame.lineHeight.body,
                }}
              />
              <button
                type="button"
                onClick={() => void handleSend()}
                disabled={sending || composing || !input.trim()}
                className={`px-5 py-3 shrink-0 ${eduGameClasses.btnPrimary}`}
                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
              >
                보내기
              </button>
            </div>
          )}
        </footer>
      )}
    </div>
  )
}

function AxisExploreBar({
  completed,
  currentSlot,
  pulse,
  pulseSlot,
  nudgeText,
}: {
  completed: number
  currentSlot: number
  pulse: boolean
  pulseSlot: number | null
  nudgeText: string
}) {
  return (
    <div className="relative">
      <div className="flex gap-2" role="list" aria-label="서론·본론·결론 진행">
        {Array.from({ length: GUIDE_AXIS_SLOTS }, (_, i) => {
          const slotLabel = EXPLORE_BAR_SLOT_LABELS[i]
          const isDone = i < completed
          const isCurrent = !isDone && i === currentSlot && currentSlot >= 0
          const isPending = !isDone && !isCurrent
          const justFilled = pulse && pulseSlot === i

          const slotStyle: {
            borderColor: string
            backgroundColor: string
          } = isDone
            ? { borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }
            : isCurrent
              ? { borderColor: eduGame.primary, backgroundColor: eduGame.bg }
              : { borderColor: eduGame.border, backgroundColor: isPending ? eduGame.bg : eduGame.surface }

          let slotClass = ''
          if (isCurrent) slotClass = eduGameClasses.animAxisCurrent
          if (justFilled) slotClass = `${slotClass} ${eduGameClasses.animAxisPop}`.trim()

          return (
            <div
              key={i}
              role="listitem"
              aria-current={isCurrent ? 'step' : undefined}
              aria-label={
                isDone
                  ? `${slotLabel} 완료`
                  : isCurrent
                    ? `${slotLabel} 진행 중`
                    : `${slotLabel} 대기`
              }
              className={`relative flex-1 flex flex-col items-center gap-1 py-2.5 rounded-xl border-2 transition-colors duration-300 ${slotClass}`}
              style={slotStyle}
            >
              {justFilled && nudgeText && (
                <span
                  className={`absolute -top-9 left-1/2 z-10 whitespace-nowrap px-2.5 py-1 rounded-full font-bold shadow-sm ${eduGameClasses.animExploreNudge}`}
                  style={{
                    backgroundColor: eduGame.primary,
                    color: eduGame.bg,
                    fontSize: eduGame.fontSize.caption,
                  }}
                  aria-live="polite"
                >
                  {nudgeText}
                </span>
              )}
              <span
                className={`font-bold leading-none ${justFilled ? eduGameClasses.animAxisCheckPop : ''}`}
                style={{
                  color: isDone ? eduGame.primary : isCurrent ? eduGame.primary : eduGame.muted,
                  fontSize: eduGame.fontSize.body,
                }}
                aria-hidden
              >
                {isDone ? '✓' : isCurrent ? '●' : '·'}
              </span>
              <span
                className="font-bold"
                style={{
                  color: isDone ? eduGame.primaryDark : isCurrent ? eduGame.primary : eduGame.muted,
                  fontSize: eduGame.fontSize.caption,
                }}
              >
                {slotLabel}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function DialogueBubble({
  turn,
  brandStudent = false,
  typewriter = false,
  coachEnter = false,
  onTypewriterComplete,
  onTypewriterProgress,
}: {
  turn: EduDialogueTurn
  brandStudent?: boolean
  typewriter?: boolean
  coachEnter?: boolean
  onTypewriterComplete?: () => void
  onTypewriterProgress?: () => void
}) {
  const isStudent = turn.role === 'student'
  const useSnippetLayout = !isStudent && coachMessageHasSnippet(turn.content)
  const segments = useSnippetLayout ? parseCoachAssistantMessage(turn.content) : null
  const showCoachEnter = coachEnter && !isStudent && !brandStudent

  return (
    <div className={`flex ${isStudent ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[85%] px-4 py-2.5 ${
          isStudent ? eduGameClasses.studentBubble : eduGameClasses.coachBubble
        }${showCoachEnter ? ` ${eduGameClasses.animCoachIn}` : ''}`}
        style={
          isStudent
            ? {
                backgroundColor: brandStudent ? eduGame.bubbleStudent : eduGame.ink,
                fontSize: eduGame.fontSize.body,
                lineHeight: eduGame.lineHeight.body,
              }
            : {
                backgroundColor: eduGame.bubbleCoach,
                color: eduGame.ink,
                borderColor: eduGame.bubbleCoachBorder,
                fontSize: eduGame.fontSize.body,
                lineHeight: eduGame.lineHeight.body,
              }
        }
      >
        {isStudent ? (
          turn.content
        ) : useSnippetLayout && segments ? (
          <div className="space-y-1">
            {segments.map((seg, i) =>
              seg.type === 'snippet' ? (
                <EduArticleSnippetCard key={`snip-${i}`} text={seg.value} display={seg.display} />
              ) : (
                <p key={`txt-${i}`} className={eduGameClasses.textKoPre}>
                  {seg.value}
                </p>
              ),
            )}
          </div>
        ) : (
          <TypewriterText
            text={turn.content}
            active={typewriter}
            onComplete={onTypewriterComplete}
            onProgress={onTypewriterProgress}
          />
        )}
      </div>
    </div>
  )
}

function ThinkingProcessPanel({
  dialogue,
  expanded,
  onToggle,
  turnCount,
  preview,
}: {
  dialogue: EduDialogueTurn[]
  expanded: boolean
  onToggle: () => void
  turnCount: number
  preview: string
}) {
  return (
    <section
      className="rounded-xl overflow-hidden"
      style={{ backgroundColor: EDU_BRAND.surface }}
    >
      <button
        type="button"
        onClick={onToggle}
        className="w-full flex items-center justify-between gap-2 px-4 py-3 text-left"
        aria-expanded={expanded}
      >
        <span className="text-sm font-bold" style={{ color: EDU_BRAND.ink }}>
          💭 내 생각 과정 보기
          <span className="font-normal ml-1.5" style={{ color: EDU_BRAND.muted }}>
            ({turnCount}턴)
          </span>
        </span>
        <span className="text-xs shrink-0" style={{ color: EDU_BRAND.accent }}>
          {expanded ? '접기 ▲' : '펼치기 ▼'}
        </span>
      </button>
      {!expanded && preview && (
        <p
          className="px-4 pb-3 text-sm line-clamp-2"
          style={{ color: EDU_BRAND.muted }}
        >
          {preview}
        </p>
      )}
      {expanded && (
        <div className="px-4 pb-4 space-y-3 border-t" style={{ borderColor: EDU_BRAND.border }}>
          {dialogue.map((turn, i) => (
            <DialogueBubble key={`think-${turn.at ?? i}-${i}`} turn={turn} brandStudent />
          ))}
        </div>
      )}
    </section>
  )
}
