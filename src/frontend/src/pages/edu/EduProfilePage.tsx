import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import TierProgressCard from '../../components/edu/TierProgressCard'
import {
  clearEduToken,
  eduApi,
  getEduStudent,
  getEduToken,
  type EduCompletedSession,
  type EduSessionState,
  type EduStudent,
  type EduTierProgress,
} from '../../services/eduApi'

function formatDate(value?: string | null): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`
}

function stanceLabel(stance?: string | null): string {
  if (stance === 'pro') return '찬성'
  if (stance === 'con') return '반대'
  return ''
}

export default function EduProfilePage() {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [student, setStudent] = useState<EduStudent | null>(() => getEduStudent())
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [completedCount, setCompletedCount] = useState(0)
  const [sessions, setSessions] = useState<EduCompletedSession[]>([])
  const [expandedId, setExpandedId] = useState<string | null>(null)
  const [sessionDetail, setSessionDetail] = useState<EduSessionState | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)

  useEffect(() => {
    if (!getEduToken()) {
      navigate('/edu')
      return
    }

    const load = async () => {
      setLoading(true)
      setError('')
      try {
        const [profileRes, sessionsRes] = await Promise.all([
          eduApi.studentProfile(),
          eduApi.studentSessions('completed'),
        ])
        setStudent(profileRes.student)
        setTier(profileRes.tier)
        setCompletedCount(profileRes.completed_count)
        setSessions(sessionsRes.sessions)
      } catch (e) {
        setError(e instanceof Error ? e.message : '불러오기 실패')
        if ((e as Error).message?.includes('401')) {
          clearEduToken()
          navigate('/edu')
        }
      } finally {
        setLoading(false)
      }
    }

    void load()
  }, [navigate])

  const handleLogout = () => {
    clearEduToken()
    navigate('/edu')
  }

  const toggleSession = async (sessionId: string) => {
    if (expandedId === sessionId) {
      setExpandedId(null)
      setSessionDetail(null)
      return
    }

    setExpandedId(sessionId)
    setDetailLoading(true)
    setSessionDetail(null)
    try {
      const detail = await eduApi.getSessionState(sessionId)
      setSessionDetail(detail)
    } catch (e) {
      setError(e instanceof Error ? e.message : '글 불러오기 실패')
    } finally {
      setDetailLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white">
      <header className="border-b border-[#333] px-4 py-4 flex items-center justify-between max-w-lg mx-auto">
        <Link to="/edu" className="text-sm text-[#888] hover:text-white">
          ← 홈
        </Link>
        <span className="text-sm font-medium">내 글함</span>
        <button type="button" onClick={handleLogout} className="text-xs underline text-[#666]">
          로그아웃
        </button>
      </header>

      <main className="max-w-lg mx-auto px-4 py-6 space-y-6">
        {loading ? (
          <div className="text-center py-12 text-[#666]">불러오는 중…</div>
        ) : (
          <>
            <section className="border border-[#333] rounded-lg p-5 bg-[#1a1a1a]">
              <div className="flex items-center gap-4">
                {student?.profile_image ? (
                  <img
                    src={student.profile_image}
                    alt=""
                    className="w-14 h-14 rounded-full object-cover border border-[#333]"
                  />
                ) : (
                  <div className="w-14 h-14 rounded-full bg-[#333] flex items-center justify-center text-xl font-bold text-[#E8521C]">
                    {(student?.display_name || '?').slice(0, 1)}
                  </div>
                )}
                <div>
                  <h1 className="text-lg font-bold">{student?.display_name || '학생'}</h1>
                  <p className="text-xs text-[#888] mt-1">
                    완료한 퀘스트 {completedCount}개
                    {student?.has_kakao ? ' · 카카오 로그인' : ''}
                  </p>
                </div>
              </div>
              {tier && (
                <div className="mt-4">
                  <TierProgressCard tier={tier} />
                </div>
              )}
            </section>

            <section className="space-y-3">
              <h2 className="text-xs font-bold text-[#E8521C] uppercase tracking-wider">내가 쓴 글</h2>
              {sessions.length === 0 ? (
                <div className="border border-[#333] rounded-lg p-8 text-center bg-[#1a1a1a]">
                  <p className="text-[#888] text-sm">아직 완료한 글이 없어요.</p>
                  <Link
                    to="/edu"
                    className="inline-block mt-4 px-4 py-2 bg-[#E8521C] text-white rounded-lg text-sm font-medium"
                  >
                    퀘스트 시작하기
                  </Link>
                </div>
              ) : (
                sessions.map((item) => {
                  const isOpen = expandedId === item.session_id
                  return (
                    <article key={item.session_id} className="border border-[#333] rounded-lg bg-[#1a1a1a] overflow-hidden">
                      <button
                        type="button"
                        onClick={() => void toggleSession(item.session_id)}
                        className="w-full text-left p-4 hover:bg-[#222] transition-colors"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0 flex-1">
                            {item.time_anchor && (
                              <p className="text-xs text-[#888] mb-1">{item.time_anchor}</p>
                            )}
                            <h3 className="font-bold text-sm leading-snug">
                              {item.essay_title || item.quest_title}
                            </h3>
                            <p className="text-xs text-[#666] mt-1">
                              {formatDate(item.completed_at)}
                              {stanceLabel(item.stance) ? ` · ${stanceLabel(item.stance)}` : ''}
                            </p>
                            {item.hero_sentence && !isOpen && (
                              <p className="text-sm text-[#999] mt-2 line-clamp-2">{item.hero_sentence}</p>
                            )}
                          </div>
                          <span className="text-[#666] text-lg shrink-0">{isOpen ? '−' : '+'}</span>
                        </div>
                      </button>

                      {isOpen && (
                        <div className="border-t border-[#333] px-4 py-4 space-y-3">
                          {detailLoading ? (
                            <p className="text-sm text-[#666]">글 불러오는 중…</p>
                          ) : sessionDetail?.essay ? (
                            <>
                              {sessionDetail.essay.title && (
                                <h4 className="font-bold">{sessionDetail.essay.title}</h4>
                              )}
                              <p className="text-sm text-[#ccc] whitespace-pre-wrap leading-relaxed">
                                {sessionDetail.essay.full_text}
                              </p>
                              {sessionDetail.essay.hero_sentence && (
                                <p className="text-sm text-[#E8521C] italic">
                                  “{sessionDetail.essay.hero_sentence}”
                                </p>
                              )}
                              <div className="flex gap-2 pt-2">
                                <Link
                                  to={`/edu/share/${item.session_id}`}
                                  className="flex-1 py-2 text-center text-sm bg-[#E8521C] text-white rounded-lg font-medium"
                                >
                                  공유 카드
                                </Link>
                              </div>
                            </>
                          ) : (
                            <p className="text-sm text-[#666]">저장된 글을 찾을 수 없어요.</p>
                          )}
                        </div>
                      )}
                    </article>
                  )
                })
              )}
            </section>
          </>
        )}

        {error && (
          <p className="text-sm text-red-400 border border-red-900 bg-red-900/20 p-3 rounded">{error}</p>
        )}
      </main>
    </div>
  )
}
