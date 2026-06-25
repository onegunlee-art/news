import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import EduStudentProfileHero from '../../components/edu/EduStudentProfileHero'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
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
  const [topicsCount, setTopicsCount] = useState(0)
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
        setTopicsCount(
          profileRes.topics_count ??
            new Set(sessionsRes.sessions.map((s) => s.quest_id).filter(Boolean)).size
        )
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

  const openSession = async (sessionId: string) => {
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
    <div
      className={`min-h-screen ${eduGameClasses.textKo}`}
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <header
        className="border-b px-4 py-3 flex items-center justify-between max-w-lg mx-auto sticky top-0 z-10"
        style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      >
        <Link to="/edu" className="text-sm underline" style={{ color: eduGame.muted }}>
          ← 홈
        </Link>
        <span className="text-sm font-bold">내 프로필</span>
        <button type="button" onClick={handleLogout} className="text-xs underline" style={{ color: eduGame.muted }}>
          로그아웃
        </button>
      </header>

      <main className="max-w-lg mx-auto px-4 py-5 space-y-6 pb-10">
        {loading ? (
          <div className="text-center py-16" style={{ color: eduGame.muted }}>
            불러오는 중…
          </div>
        ) : (
          <>
            {tier && (
              <EduStudentProfileHero
                student={student}
                tier={tier}
                completedCount={completedCount}
                topicsCount={
                  topicsCount ||
                  new Set(sessions.map((s) => s.quest_id).filter(Boolean)).size
                }
              />
            )}

            <section className="space-y-3">
              <h2 className="font-bold px-1" style={{ fontSize: eduGame.fontSize.label, color: eduGame.primary }}>
                내가 쓴 글
              </h2>
              {sessions.length === 0 ? (
                <div
                  className="rounded-2xl border-2 p-8 text-center"
                  style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
                >
                  <p style={{ fontSize: eduGame.fontSize.body, color: eduGame.muted }}>
                    아직 완료한 글이 없어요.
                  </p>
                  <Link
                    to="/edu"
                    className={`inline-block mt-4 px-5 py-3 ${eduGameClasses.btnPrimary}`}
                    style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
                  >
                    퀘스트 시작하기
                  </Link>
                </div>
              ) : (
                sessions.map((item) => {
                  const isOpen = expandedId === item.session_id
                  const title = item.essay_title || item.quest_title
                  return (
                    <article
                      key={item.session_id}
                      className="rounded-2xl border-2 overflow-hidden"
                      style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
                    >
                      <div className="p-4 flex items-start gap-3">
                        <div className="min-w-0 flex-1">
                          {item.time_anchor && (
                            <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }} className="mb-1">
                              {item.time_anchor}
                            </p>
                          )}
                          <h3 className="font-bold leading-snug" style={{ fontSize: eduGame.fontSize.body }}>
                            {title}
                          </h3>
                          <p className="mt-1" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                            {formatDate(item.completed_at)}
                            {stanceLabel(item.stance) ? ` · ${stanceLabel(item.stance)}` : ''}
                          </p>
                          {item.hero_sentence && !isOpen && (
                            <p
                              className="mt-2 line-clamp-2"
                              style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}
                            >
                              {item.hero_sentence}
                            </p>
                          )}
                        </div>
                        <button
                          type="button"
                          onClick={() => void openSession(item.session_id)}
                          className={`shrink-0 px-3 py-2 rounded-xl font-bold border-2 active:scale-[0.98] transition-transform ${eduGameClasses.textKo}`}
                          style={{
                            fontSize: eduGame.fontSize.caption,
                            borderColor: eduGame.primary,
                            color: isOpen ? eduGame.bg : eduGame.primary,
                            backgroundColor: isOpen ? eduGame.primary : eduGame.bg,
                          }}
                        >
                          {isOpen ? '접기' : '다시 보기'}
                        </button>
                      </div>

                      {isOpen && (
                        <div
                          className="border-t px-4 py-4 space-y-3"
                          style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
                        >
                          {detailLoading ? (
                            <p style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>글 불러오는 중…</p>
                          ) : sessionDetail?.essay ? (
                            <>
                              {sessionDetail.essay.title && (
                                <h4 className="font-bold" style={{ fontSize: eduGame.fontSize.body }}>
                                  {sessionDetail.essay.title}
                                </h4>
                              )}
                              <p
                                className="whitespace-pre-wrap leading-relaxed"
                                style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}
                              >
                                {sessionDetail.essay.full_text}
                              </p>
                              {sessionDetail.essay.hero_sentence && (
                                <p
                                  className="italic"
                                  style={{ fontSize: eduGame.fontSize.label, color: eduGame.primary }}
                                >
                                  “{sessionDetail.essay.hero_sentence}”
                                </p>
                              )}
                              <Link
                                to={`/edu/share/${item.session_id}`}
                                className={`block w-full py-3 text-center ${eduGameClasses.btnPrimary}`}
                                style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
                              >
                                공유 카드 만들기
                              </Link>
                            </>
                          ) : (
                            <p style={{ fontSize: eduGame.fontSize.label, color: eduGame.muted }}>
                              저장된 글을 찾을 수 없어요.
                            </p>
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
          <p className="text-sm text-red-600 border border-red-200 bg-red-50 p-3 rounded-xl">{error}</p>
        )}
      </main>
    </div>
  )
}
