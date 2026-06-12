import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import TierProgressCard from '../../components/edu/TierProgressCard'
import {
  eduApi,
  getEduToken,
  type EduQuest,
  type EduQuestArticle,
  type EduTierProgress,
  type EduTurnResponse,
} from '../../services/eduApi'
import QuestFlowLegacy from './QuestFlowLegacy'
import QuestFlowChat from './QuestFlowChat'

const USE_CHAT_ENGINE = import.meta.env.VITE_EDU_USE_CHAT_ENGINE !== 'false'
const USE_TURN_FSM = import.meta.env.VITE_EDU_USE_TURN_FSM !== 'false'

const UI_STEPS = ['찬반', '이유', '근거', '반론', '정리', '5문장', 'XP']

const ROLE_LABEL: Record<string, string> = {
  primary: '핵심',
  context: '배경',
  counter: '다른 시각',
}

export default function QuestFlowPage() {
  if (USE_CHAT_ENGINE) {
    return <QuestFlowChat />
  }
  if (!USE_TURN_FSM) {
    return <QuestFlowLegacy />
  }
  return <QuestFlowTurnFsm />
}

function QuestFlowTurnFsm() {
  const navigate = useNavigate()
  const [step, setStep] = useState(0)
  const [turn, setTurn] = useState(0)
  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [prompt, setPrompt] = useState('')
  const [reason, setReason] = useState('')
  const [evidence, setEvidence] = useState('')
  const [articles, setArticles] = useState<EduQuestArticle[]>([])
  const [counterArgument, setCounterArgument] = useState('')
  const [mixupSources, setMixupSources] = useState<EduTurnResponse['mixup_sources']>([])
  const [rebuttal, setRebuttal] = useState('')
  const [stanceChanged, setStanceChanged] = useState(false)
  const [summaryLines, setSummaryLines] = useState<string[]>([])
  const [outline, setOutline] = useState<Record<string, string>>({})
  const [sentences, setSentences] = useState<string[]>(['', '', '', '', ''])
  const [feedback, setFeedback] = useState<string | null>(null)
  const [fullText, setFullText] = useState<string | null>(null)
  const [scqaParts, setScqaParts] = useState<Record<string, string>>({})
  const [heroSentence, setHeroSentence] = useState<string | null>(null)
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [xpGained, setXpGained] = useState(0)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!getEduToken()) {
      navigate('/edu')
      return
    }
    init()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [navigate])

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
      if (existing) {
        setSessionId(existing.session_id)
        mapStageToStep(existing.stage)
        if (existing.stage === 'completed') {
          try {
            const state = await eduApi.getSessionState(existing.session_id)
            if (state.essay?.full_text) {
              setFullText(state.essay.full_text)
              setHeroSentence(state.essay.hero_sentence)
              setFeedback(state.essay.feedback)
              setScqaParts(state.essay.scqa_parts ?? {})
            }
          } catch {
            // ignore restore errors
          }
        }
      } else {
        const started = await eduApi.startSession(today.quest.quest_id)
        setSessionId(started.session_id)
        setStep(0)
        setTurn(0)
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '초기화 실패')
    } finally {
      setLoading(false)
    }
  }

  const mapStageToStep = (stage: string) => {
    if (stage === 'commit') {
      setStep(0)
      setTurn(0)
    } else if (stage === 'hammer') {
      setStep(3)
      setTurn(3)
    } else if (stage === 'reflection') {
      setStep(4)
      setTurn(4)
    } else if (stage === 'writing') {
      setStep(5)
      setTurn(5)
    } else if (stage === 'completed') {
      setStep(6)
    }
  }

  const applyTurnResponse = (res: EduTurnResponse) => {
    if (res.prompt) setPrompt(res.prompt)
    if (res.articles) setArticles(res.articles)
    if (res.counter_argument) setCounterArgument(res.counter_argument)
    if (res.mixup_sources) setMixupSources(res.mixup_sources)
    if (res.summary_lines) setSummaryLines(res.summary_lines)
    if (res.outline) setOutline(res.outline)
    if (res.feedback) setFeedback(res.feedback)
    if (res.full_text) setFullText(res.full_text)
    if (res.scqa_parts) setScqaParts(res.scqa_parts)
    if (res.hero_sentence) setHeroSentence(res.hero_sentence)
    if (res.xp_gained) setXpGained(res.xp_gained)
    if (res.tier) setTier(res.tier)

    if (res.turn === 'completed') {
      setStep(6)
      return
    }

    const nextTurn = typeof res.turn === 'number' ? res.turn : turn
    setTurn(nextTurn)

    if (nextTurn === 1 && !res.needs_followup) setStep(1)
    else if (nextTurn === 1 && res.needs_followup) setStep(1)
    else if (nextTurn === 2) setStep(2)
    else if (nextTurn === 3) setStep(3)
    else if (nextTurn === 4) setStep(4)
    else if (nextTurn === 5) setStep(5)
  }

  const handleStance = async (stance: 'pro' | 'con') => {
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.submitTurn(sessionId, 0, { stance })
      applyTurnResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleReasonSubmit = async () => {
    if (!reason.trim()) return
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.submitTurn(sessionId, 1, { reason })
      applyTurnResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleEvidenceSubmit = async () => {
    if (!evidence.trim()) return
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.submitTurn(sessionId, 2, { evidence })
      applyTurnResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleRebuttalSubmit = async () => {
    if (!rebuttal.trim()) return
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.submitTurn(sessionId, 3, {
        rebuttal,
        stance_changed: stanceChanged,
        new_stance: stanceChanged ? undefined : undefined,
      })
      applyTurnResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleReflectionNext = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.submitTurn(sessionId, 4, {})
      applyTurnResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleWritingSubmit = async () => {
    if (sentences.some((s) => !s.trim())) return
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.submitTurn(sessionId, 5, { sentences })
      applyTurnResponse(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  if (loading && !quest) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white text-[#1a1a1a]">
        불러오는 중…
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-white text-[#1a1a1a]">
      <header className="border-b border-[#1a1a1a] px-4 py-3 max-w-lg mx-auto">
        <Link to="/edu" className="text-xs text-[#666] underline">
          ← 홈
        </Link>
        <div className="mt-2 flex gap-0.5 overflow-x-auto">
          {UI_STEPS.map((label, i) => (
            <div
              key={label}
              className={`flex-shrink-0 min-w-[2.5rem] text-center text-[9px] py-1 px-1 border ${
                i <= step ? 'bg-[#1a1a1a] text-white border-[#1a1a1a]' : 'border-[#ccc] text-[#999]'
              }`}
            >
              {label}
            </div>
          ))}
        </div>
      </header>

      <main className="max-w-lg mx-auto px-4 py-6 space-y-5">
        {quest && (
          <div>
            <p className="text-xs text-[#666]">{quest.quest_code}</p>
            <h1 className="text-lg font-bold leading-snug">{quest.quest_title}</h1>
          </div>
        )}

        {step === 0 && quest && (
          <section className="space-y-3">
            <p className="text-sm text-[#666]">오늘의 입장을 선택하세요.</p>
            <button
              type="button"
              disabled={loading}
              onClick={() => handleStance('pro')}
              className="w-full text-left border-2 border-[#1a1a1a] rounded p-4 hover:bg-[#f8f8f8]"
            >
              <span className="text-xs font-bold block mb-1">찬성</span>
              {quest.pro_line}
            </button>
            <button
              type="button"
              disabled={loading}
              onClick={() => handleStance('con')}
              className="w-full text-left border-2 border-[#1a1a1a] rounded p-4 hover:bg-[#f8f8f8]"
            >
              <span className="text-xs font-bold block mb-1">반대</span>
              {quest.con_line}
            </button>
          </section>
        )}

        {step === 1 && (
          <section className="space-y-4">
            <div className="border border-[#1a1a1a] rounded p-4 bg-[#f8f8f8]">
              <p className="text-xs font-bold mb-2">소크라테스 질문</p>
              <p className="text-sm">{prompt}</p>
            </div>
            <label className="block text-sm">
              네 생각을 말해봐
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                className="mt-2 w-full border border-[#1a1a1a] rounded p-2 text-sm min-h-[100px]"
              />
            </label>
            <button
              type="button"
              disabled={loading || !reason.trim()}
              onClick={handleReasonSubmit}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium disabled:opacity-40"
            >
              다음
            </button>
          </section>
        )}

        {step === 2 && (
          <section className="space-y-4">
            <p className="text-sm text-[#666]">{prompt}</p>
            <div className="space-y-3">
              {articles.map((a) => (
                <ArticleCard key={a.news_id} article={a} />
              ))}
            </div>
            <label className="block text-sm">
              기사를 참고한 근거
              <textarea
                value={evidence}
                onChange={(e) => setEvidence(e.target.value)}
                className="mt-2 w-full border border-[#1a1a1a] rounded p-2 text-sm min-h-[80px]"
              />
            </label>
            <button
              type="button"
              disabled={loading || !evidence.trim()}
              onClick={handleEvidenceSubmit}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium disabled:opacity-40"
            >
              반론 듣기
            </button>
          </section>
        )}

        {step === 3 && (
          <section className="space-y-4">
            <div className="border border-[#1a1a1a] rounded p-4 bg-[#f8f8f8]">
              <p className="text-xs font-bold mb-2">반론 (Hammer)</p>
              <p className="text-sm">{counterArgument}</p>
              {mixupSources && mixupSources.length > 0 && (
                <div className="mt-3 space-y-1">
                  <p className="text-xs font-bold text-[#666]">다른 시각 (Mix-up)</p>
                  {mixupSources.map((m, i) => (
                    <p key={i} className="text-xs text-[#666]">
                      [{m.source}] {m.excerpt}
                    </p>
                  ))}
                </div>
              )}
            </div>
            <label className="block text-sm">
              {prompt}
              <textarea
                value={rebuttal}
                onChange={(e) => setRebuttal(e.target.value)}
                className="mt-2 w-full border border-[#1a1a1a] rounded p-2 text-sm min-h-[80px]"
              />
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={stanceChanged}
                onChange={(e) => setStanceChanged(e.target.checked)}
              />
              입장이 바뀌었어요
            </label>
            <button
              type="button"
              disabled={loading || !rebuttal.trim()}
              onClick={handleRebuttalSubmit}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium disabled:opacity-40"
            >
              정리하기
            </button>
          </section>
        )}

        {step === 4 && (
          <section className="space-y-4">
            <div className="border border-[#1a1a1a] rounded p-4">
              <p className="text-xs font-bold mb-2">3줄 정리</p>
              <ul className="text-sm space-y-1 list-disc pl-4">
                {summaryLines.map((line, i) => (
                  <li key={i}>{line}</li>
                ))}
              </ul>
            </div>
            <button
              type="button"
              disabled={loading}
              onClick={handleReflectionNext}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium"
            >
              5문장 쓰기
            </button>
          </section>
        )}

        {step === 5 && (
          <section className="space-y-4">
            <p className="text-sm text-[#666]">{prompt}</p>
            {[
              ['situation_starter', '1. 상황 (S)'],
              ['complication_starter', '2. 갈등 (C)'],
              ['question_starter', '3. 질문 (Q)'],
              ['answer_starter', '4. 주장 (A)'],
              ['conclusion_starter', '5. 결론'],
            ].map(([key, label], i) => (
              <div key={key}>
                <p className="text-xs text-[#666] mb-1">{label}</p>
                {outline[key] && (
                  <p className="text-xs text-[#999] mb-1">{outline[key]}</p>
                )}
                <input
                  type="text"
                  value={sentences[i]}
                  onChange={(e) => {
                    const next = [...sentences]
                    next[i] = e.target.value
                    setSentences(next)
                  }}
                  className="w-full border border-[#1a1a1a] rounded px-3 py-2 text-sm"
                />
              </div>
            ))}
            <button
              type="button"
              disabled={loading || sentences.some((s) => !s.trim())}
              onClick={handleWritingSubmit}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium disabled:opacity-40"
            >
              완료하기
            </button>
          </section>
        )}

        {step === 6 && (
          <section className="space-y-4">
            {fullText && (
              <div className="border-2 border-[#1a1a1a] rounded p-4">
                <p className="text-xs text-[#666] mb-2">나만의 글</p>
                <p className="text-sm leading-relaxed">{fullText}</p>
              </div>
            )}
            {Object.keys(scqaParts).length > 0 && (
              <div className="space-y-2 border border-[#ccc] rounded p-3">
                {Object.entries(scqaParts).map(([key, val]) =>
                  val ? (
                    <div key={key}>
                      <p className="text-[10px] text-[#999]">{key}</p>
                      <p className="text-xs">{val}</p>
                    </div>
                  ) : null
                )}
              </div>
            )}
            {heroSentence && (
              <div className="border-2 border-[#1a1a1a] rounded p-4">
                <p className="text-xs text-[#666] mb-2">이번에 쓴 핵심 문장</p>
                <p className="text-base font-medium leading-relaxed">&ldquo;{heroSentence}&rdquo;</p>
              </div>
            )}
            {feedback && (
              <p className="text-sm border border-[#ccc] bg-[#f8f8f8] p-3 rounded">{feedback}</p>
            )}
            <p className="text-sm">+{xpGained} XP 획득</p>
            {tier && <TierProgressCard tier={tier} />}
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
      </main>
    </div>
  )
}

function ArticleCard({ article }: { article: EduQuestArticle }) {
  return (
    <div className="border border-[#ccc] rounded p-3">
      <div className="flex items-center gap-2 mb-1">
        <span className="text-[10px] font-bold border border-[#1a1a1a] px-1.5 py-0.5">
          {ROLE_LABEL[article.role] ?? article.role}
        </span>
        {article.source_outlet && (
          <span className="text-[10px] text-[#666]">{article.source_outlet}</span>
        )}
      </div>
      <p className="text-sm font-medium">{article.title}</p>
      {article.why_important && (
        <p className="text-xs text-[#666] mt-1">{article.why_important}</p>
      )}
      {article.excerpt && (
        <p className="text-xs text-[#999] mt-1 line-clamp-3">{article.excerpt}</p>
      )}
      {article.gist_url && (
        <a
          href={article.gist_url}
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs underline mt-2 inline-block"
        >
          the gist에서 읽기
        </a>
      )}
    </div>
  )
}
