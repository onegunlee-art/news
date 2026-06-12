import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import TierProgressCard from '../../components/edu/TierProgressCard'
import {
  eduApi,
  getEduToken,
  type EduQuest,
  type EduTierProgress,
} from '../../services/eduApi'

const UI_STEPS = ['찬반 선택', '반론 읽기', '5문장 쓰기', 'XP·티어']

type HammerData = {
  counter_line: string
  hammer_hint: string
  conflict_summary: string
  reflection_question: string
}

/** Legacy 4-step flow (rollback path when VITE_EDU_USE_TURN_FSM=false) */
export default function QuestFlowLegacy() {
  const navigate = useNavigate()
  const [step, setStep] = useState(0)
  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [sessionId, setSessionId] = useState('')
  const [, setStance] = useState<'pro' | 'con' | ''>('')
  const [hammer, setHammer] = useState<HammerData | null>(null)
  const [reflection, setReflection] = useState('')
  const [sentences, setSentences] = useState<string[]>(['', '', '', '', ''])
  const [v2Sentences, setV2Sentences] = useState<string[]>(['', '', '', '', ''])
  const [feedback, setFeedback] = useState<string | null>(null)
  const [needsV2, setNeedsV2] = useState(false)
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
      } else {
        const started = await eduApi.startSession(today.quest.quest_id)
        setSessionId(started.session_id)
        setStep(0)
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '초기화 실패')
    } finally {
      setLoading(false)
    }
  }

  const mapStageToStep = (stage: string) => {
    if (stage === 'commit') setStep(0)
    else if (stage === 'hammer' || stage === 'reflection') setStep(1)
    else if (stage === 'writing' || stage === 'growth') setStep(2)
    else if (stage === 'completed') setStep(3)
  }

  const handleStance = async (s: 'pro' | 'con') => {
    setLoading(true)
    setStance(s)
    try {
      const res = await eduApi.setStance(sessionId, s)
      setHammer(res.hammer)
      setStep(1)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleHammerNext = async () => {
    setLoading(true)
    try {
      await eduApi.advanceHammer(sessionId, reflection)
      setStep(2)
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleV1Submit = async () => {
    setLoading(true)
    try {
      const res = await eduApi.submitWriting(sessionId, sentences)
      setFeedback(res.teacher_feedback)
      setNeedsV2(res.needs_v2)
      if (res.needs_v2) {
        setV2Sentences([...sentences])
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '오류')
    } finally {
      setLoading(false)
    }
  }

  const handleComplete = async () => {
    setLoading(true)
    try {
      const finalV2 = needsV2 ? v2Sentences : sentences
      const res = await eduApi.complete(sessionId, finalV2, 'refined')
      setHeroSentence(res.hero_sentence)
      setXpGained(res.xp_gained)
      setTier(res.tier)
      setStep(3)
    } catch (e) {
      setError(e instanceof Error ? e.message : '완료 오류')
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
        <div className="mt-2 flex gap-1">
          {UI_STEPS.map((label, i) => (
            <div
              key={label}
              className={`flex-1 text-center text-[10px] py-1 border ${
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

        {step === 1 && hammer && (
          <section className="space-y-4">
            <div className="border border-[#1a1a1a] rounded p-4 bg-[#f8f8f8]">
              <p className="text-xs font-bold mb-2">반론 읽기</p>
              <p className="text-sm mb-2">{hammer.counter_line}</p>
              {hammer.hammer_hint && (
                <p className="text-xs text-[#666]">힌트: {hammer.hammer_hint}</p>
              )}
              <p className="text-xs text-[#666] mt-3">{hammer.conflict_summary}</p>
            </div>
            <label className="block text-sm">
              {hammer.reflection_question}
              <textarea
                value={reflection}
                onChange={(e) => setReflection(e.target.value)}
                className="mt-2 w-full border border-[#1a1a1a] rounded p-2 text-sm min-h-[80px]"
              />
            </label>
            <button
              type="button"
              disabled={loading}
              onClick={handleHammerNext}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium"
            >
              5문장 쓰기로
            </button>
          </section>
        )}

        {step === 2 && (
          <section className="space-y-4">
            <p className="text-sm text-[#666]">
              주장 · 근거 · 반론 · 삶 연결 · 결론 — 5문장
            </p>
            {(needsV2 ? v2Sentences : sentences).map((_, i) => (
              <input
                key={i}
                type="text"
                value={needsV2 ? v2Sentences[i] : sentences[i]}
                onChange={(e) => {
                  const next = [...(needsV2 ? v2Sentences : sentences)]
                  next[i] = e.target.value
                  needsV2 ? setV2Sentences(next) : setSentences(next)
                }}
                placeholder={`${i + 1}번 문장`}
                className="w-full border border-[#1a1a1a] rounded px-3 py-2 text-sm"
              />
            ))}
            {feedback && (
              <p className="text-sm border border-[#ccc] bg-[#f8f8f8] p-3 rounded">{feedback}</p>
            )}
            {!needsV2 ? (
              <button
                type="button"
                disabled={loading}
                onClick={handleV1Submit}
                className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium"
              >
                피드백 받기
              </button>
            ) : (
              <button
                type="button"
                disabled={loading}
                onClick={handleComplete}
                className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium"
              >
                완료하기
              </button>
            )}
          </section>
        )}

        {step === 3 && (
          <section className="space-y-4">
            {heroSentence && (
              <div className="border-2 border-[#1a1a1a] rounded p-4">
                <p className="text-xs text-[#666] mb-2">이번에 쓴 핵심 문장</p>
                <p className="text-base font-medium leading-relaxed">&ldquo;{heroSentence}&rdquo;</p>
              </div>
            )}
            <p className="text-sm">+{xpGained} XP 획득</p>
            {tier && <TierProgressCard tier={tier} />}
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
