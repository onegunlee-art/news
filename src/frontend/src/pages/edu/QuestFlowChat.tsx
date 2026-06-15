import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import EssayEditor from '../../components/edu/EssayEditor'
import { type EssayArtifact } from '../../components/edu/EssayRevealCard'
import StructurePreviewCard, { type EssayStructurePreview } from '../../components/edu/StructurePreviewCard'
import TierProgressCard from '../../components/edu/TierProgressCard'
import {
  eduApi,
  getEduToken,
  type EduDialogueTurn,
  type EduQuest,
  type EduQuestArticle,
  type EduTierProgress,
} from '../../services/eduApi'

const ROLE_LABEL: Record<string, string> = {
  primary: '핵심',
  context: '배경',
  counter: '다른 시각',
}

export default function QuestFlowChat() {
  const navigate = useNavigate()
  const bottomRef = useRef<HTMLDivElement>(null)

  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [dialogue, setDialogue] = useState<EduDialogueTurn[]>([])
  const [input, setInput] = useState('')
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
  const saveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    if (!getEduToken()) {
      navigate('/edu')
      return
    }
    init()
  }, [navigate])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [dialogue, completed, composing])

  useEffect(() => {
    return () => {
      if (saveTimerRef.current) clearTimeout(saveTimerRef.current)
    }
  }, [])

  const init = async () => {
    setLoading(true)
    setError('')
    try {
      const today = await eduApi.todayQuest()
      setQuest(today.quest)
      setTier(today.tier || null)

      if (!today.quest) {
        setError('오늘의 퀘스트가 없습니다.')
        return
      }

      const existing = today.active_session || today.existing_session
      let sid = existing?.session_id ?? ''

      if (!sid) {
        const started = await eduApi.startSession(today.quest.quest_id)
        sid = started.session_id
      }

      setSessionId(sid)

      const state = await eduApi.getSessionState(sid)
      setProgressPct(state.progress_pct)
      setPhase(state.blueprint?.phase ?? 'stance')
      setDialogue(state.dialogue ?? [])
      const preview = state.blueprint?.essay_structure
      if (preview?.sections?.length) {
        setStructurePreview(preview as EssayStructurePreview)
      }

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
  }

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

  const handleChatResponse = async (res: Awaited<ReturnType<typeof eduApi.sendChat>>) => {
    setProgressPct(res.progress_pct ?? progressPct)
    setPhase(res.phase ?? phase)
    if (res.articles) setArticles(res.articles)
    if (res.stance_changed) setStanceChanged(true)
    if (res.assistant_message) appendAssistant(res.assistant_message)
    if (res.structure_preview?.sections?.length) {
      setStructurePreview(res.structure_preview as EssayStructurePreview)
    } else if (res.blueprint?.essay_structure?.sections?.length) {
      setStructurePreview(res.blueprint.essay_structure as EssayStructurePreview)
    }
    if (res.should_compose) {
      await handleCompose(res.session_id)
    }
  }

  const handleStance = async (stance: 'pro' | 'con') => {
    if (!sessionId || !quest) return
    setSending(true)
    setError('')
    const label = stance === 'pro' ? '찬성' : '반대'
    const line = stance === 'pro' ? quest.pro_line : quest.con_line
    appendStudent(`${label}: ${line}`)
    try {
      const res = await eduApi.sendChat(sessionId, { action: 'select_stance', stance })
      await handleChatResponse(res)
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
      await handleChatResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setSending(false)
    }
  }

  const handleSend = async () => {
    if (!input.trim() || !sessionId || sending || completed) return
    const msg = input.trim()
    setInput('')
    setSending(true)
    setError('')
    appendStudent(msg)
    try {
      const res = await eduApi.sendChat(sessionId, { message: msg })
      await handleChatResponse(res)
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

  return (
    <div className="min-h-screen bg-white text-[#1a1a1a] flex flex-col">
      <header className="border-b border-[#1a1a1a] px-4 py-3 max-w-lg mx-auto w-full">
        <Link to="/edu" className="text-xs text-[#666] underline">
          ← 홈
        </Link>
        {quest && (
          <div className="mt-2">
            <p className="text-xs text-[#666]">{quest.quest_code}</p>
            <h1 className="text-base font-bold leading-snug">{quest.quest_title}</h1>
          </div>
        )}
        <div className="mt-2 flex items-center gap-2">
          <div className="flex-1 h-1.5 bg-[#eee] rounded overflow-hidden">
            <div
              className="h-full bg-[#1a1a1a] transition-all duration-500"
              style={{ width: `${progressPct}%` }}
            />
          </div>
          <span className="text-[10px] text-[#666] whitespace-nowrap">생각 정리 {progressPct}%</span>
        </div>
      </header>

      <main className="flex-1 max-w-lg mx-auto w-full px-4 py-4 overflow-y-auto space-y-3">
        {phase === 'stance' && dialogue.length === 0 && quest && !completed && (
          <section className="space-y-3 mb-4">
            <p className="text-sm text-[#666]">오늘의 입장을 선택하세요.</p>
            <button
              type="button"
              disabled={sending}
              onClick={() => handleStance('pro')}
              className="w-full text-left border-2 border-[#1a1a1a] rounded p-4 hover:bg-[#f8f8f8]"
            >
              <span className="text-xs font-bold block mb-1">찬성</span>
              {quest.pro_line}
            </button>
            <button
              type="button"
              disabled={sending}
              onClick={() => handleStance('con')}
              className="w-full text-left border-2 border-[#1a1a1a] rounded p-4 hover:bg-[#f8f8f8]"
            >
              <span className="text-xs font-bold block mb-1">반대</span>
              {quest.con_line}
            </button>
          </section>
        )}

        {dialogue.map((turn, i) => (
          <div
            key={`${turn.at ?? i}-${i}`}
            className={`flex ${turn.role === 'student' ? 'justify-end' : 'justify-start'}`}
          >
            <div
              className={`max-w-[85%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap ${
                turn.role === 'student'
                  ? 'bg-[#1a1a1a] text-white'
                  : 'bg-[#f8f8f8] border border-[#ddd]'
              }`}
            >
              {turn.content}
            </div>
          </div>
        ))}

        {articles.length > 0 && !completed && phase === 'evidence' && (
          <section className="space-y-2 border border-[#ccc] rounded p-3 bg-[#fafafa]">
            <p className="text-xs font-bold">참고 기사</p>
            {articles.map((a) => (
              <ArticleCard key={a.news_id} article={a} />
            ))}
          </section>
        )}

        {structurePreview && !completed && (
          <StructurePreviewCard structure={structurePreview} />
        )}

        {composing && (
          <p className="text-sm text-[#666] text-center py-4">네 글을 만들고 있어…</p>
        )}

        {completed && essay && (
          <section className="space-y-4 border-2 border-[#1a1a1a] rounded-lg p-4 mt-4">
            <div className="flex items-center justify-between gap-2">
              <p className="text-xs font-bold text-[#666]">나만의 글</p>
              <span className="text-[10px] text-[#666]">
                {saveStatus === 'saving' && '저장 중…'}
                {saveStatus === 'saved' && '✓ 자동 저장됨'}
                {saveStatus === 'error' && '저장 실패 — 다시 시도해줘'}
              </span>
            </div>
            {stanceChanged && (
              <span className="inline-block text-[10px] font-bold border border-[#1a1a1a] px-2 py-0.5">
                생각이 바뀌었다
              </span>
            )}
            <p className="text-[10px] text-[#999]">제목·본문·핵심 문장을 고치면 1.5초 후 자동 저장돼.</p>
            <EssayEditor essay={essay} onChange={handleEssayChange} disabled={saveStatus === 'saving'} />
            {essay.feedback && (
              <p className="text-xs text-[#666] border border-[#eee] p-2 rounded">{essay.feedback}</p>
            )}
            {xpGained > 0 && <p className="text-sm">+{xpGained} XP 획득</p>}
            {tier && <TierProgressCard tier={tier} />}
            <button
              type="button"
              onClick={() => essay && void persistEssay(essay)}
              disabled={saveStatus === 'saving'}
              className="w-full py-2 text-sm border border-[#1a1a1a] rounded font-medium disabled:opacity-40"
            >
              지금 저장
            </button>
            <Link
              to={`/edu/share/${sessionId}`}
              className="block w-full py-3 text-center bg-[#1a1a1a] text-white rounded font-medium"
            >
              공유 카드 만들기
            </Link>
            <Link
              to="/edu"
              className="block w-full py-3 text-center border border-[#1a1a1a] rounded font-medium"
            >
              홈으로
            </Link>
          </section>
        )}

        {error && (
          <p className="text-sm text-red-600 border border-red-200 bg-red-50 p-3 rounded">{error}</p>
        )}
        <div ref={bottomRef} />
      </main>

      {!completed && phase !== 'stance' && (
        <footer className="border-t border-[#ddd] px-4 py-3 max-w-lg mx-auto w-full bg-white">
          {phase === 'reflection' && (
            <div className="flex gap-2 mb-2">
              <button
                type="button"
                onClick={handleConfirmReflection}
                disabled={sending || composing}
                className="flex-1 py-2 bg-[#1a1a1a] text-white rounded text-sm font-medium disabled:opacity-40"
              >
                맞아 — 글 만들기
              </button>
            </div>
          )}
          <div className="flex gap-2">
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSend()}
              placeholder="네 생각을 입력해…"
              disabled={sending || composing}
              className="flex-1 border border-[#1a1a1a] rounded px-3 py-2 text-sm"
            />
            <button
              type="button"
              onClick={handleSend}
              disabled={sending || composing || !input.trim()}
              className="px-4 py-2 bg-[#1a1a1a] text-white rounded text-sm font-medium disabled:opacity-40"
            >
              보내기
            </button>
          </div>
        </footer>
      )}
    </div>
  )
}

function ArticleCard({ article }: { article: EduQuestArticle }) {
  return (
    <div className="border border-[#ccc] rounded p-2">
      <div className="flex items-center gap-2 mb-1">
        <span className="text-[10px] font-bold border border-[#1a1a1a] px-1 py-0.5">
          {ROLE_LABEL[article.role] ?? article.role}
        </span>
      </div>
      <p className="text-xs font-medium">{article.title}</p>
      {article.excerpt && (
        <p className="text-[10px] text-[#999] mt-1 line-clamp-2">{article.excerpt}</p>
      )}
    </div>
  )
}
