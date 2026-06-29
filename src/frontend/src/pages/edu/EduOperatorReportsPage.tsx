import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import {
  eduOperatorDownloadPdf,
  eduOperatorListStudents,
  eduOperatorPreviewReport,
  eduOperatorVerifySession,
  type EduOperatorStudent,
  type EduParentReportPayload,
} from '../../services/eduOperatorApi'
import {
  clearEduOperatorSession,
  getEduOperatorProfile,
  getEduOperatorToken,
  hasEduOperatorSession,
  setEduOperatorSession,
} from '../../utils/eduOperatorSession'

const REPORTS_PATH = '/edu/operator/reports'
const LOGIN_PATH = '/edu/operator/login'

function formatRelative(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

export default function EduOperatorReportsPage() {
  const navigate = useNavigate()
  const [ready, setReady] = useState(false)
  const [operatorName, setOperatorName] = useState('')

  const [students, setStudents] = useState<EduOperatorStudent[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [report, setReport] = useState<EduParentReportPayload | null>(null)
  const [loadingList, setLoadingList] = useState(true)
  const [loadingPreview, setLoadingPreview] = useState(false)
  const [loadingPdf, setLoadingPdf] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!hasEduOperatorSession()) {
      navigate(`${LOGIN_PATH}?returnTo=${encodeURIComponent(REPORTS_PATH)}`, { replace: true })
      return
    }

    void (async () => {
      try {
        const token = getEduOperatorToken()
        if (!token) throw new Error('no token')
        const operator = await eduOperatorVerifySession()
        setEduOperatorSession(token, operator)
        setOperatorName(operator.display_name || operator.email)
        setReady(true)
      } catch {
        clearEduOperatorSession()
        navigate(`${LOGIN_PATH}?returnTo=${encodeURIComponent(REPORTS_PATH)}`, { replace: true })
      }
    })()
  }, [navigate])

  const loadStudents = useCallback(async () => {
    setLoadingList(true)
    setError('')
    try {
      const rows = await eduOperatorListStudents()
      setStudents(rows)
    } catch (e) {
      setError(e instanceof Error ? e.message : '목록을 불러오지 못했습니다.')
    } finally {
      setLoadingList(false)
    }
  }, [])

  useEffect(() => {
    if (ready) void loadStudents()
  }, [ready, loadStudents])

  const selectStudent = async (id: string) => {
    setSelectedId(id)
    setReport(null)
    setLoadingPreview(true)
    setError('')
    try {
      const preview = await eduOperatorPreviewReport(id)
      setReport(preview)
    } catch (e) {
      setError(e instanceof Error ? e.message : '미리보기 실패')
    } finally {
      setLoadingPreview(false)
    }
  }

  const fetchPdfBlob = async () => {
    if (!selectedId) return null
    setLoadingPdf(true)
    setError('')
    try {
      return await eduOperatorDownloadPdf(selectedId)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'PDF 생성 실패')
      return null
    } finally {
      setLoadingPdf(false)
    }
  }

  const handleDownloadPdf = async () => {
    const result = await fetchPdfBlob()
    if (!result) return
    const url = URL.createObjectURL(result.blob)
    const a = document.createElement('a')
    a.href = url
    a.download = result.filename
    a.click()
    URL.revokeObjectURL(url)
  }

  const handleShare = async () => {
    const result = await fetchPdfBlob()
    if (!result || !report) return
    const file = new File([result.blob], result.filename, { type: 'application/pdf' })
    const shareData: ShareData = {
      title: `${report.student_name} gistudy 리포트`,
      text: report.cover.headline,
      files: [file],
    }
    if (typeof navigator.share === 'function' && navigator.canShare?.(shareData)) {
      try {
        await navigator.share(shareData)
        return
      } catch {
        /* fallback */
      }
    }
    const url = URL.createObjectURL(result.blob)
    const a = document.createElement('a')
    a.href = url
    a.download = result.filename
    a.click()
    URL.revokeObjectURL(url)
  }

  const handleLogout = () => {
    clearEduOperatorSession()
    navigate(LOGIN_PATH, { replace: true })
  }

  if (!ready) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: eduGame.bg }}>
        <p style={{ color: eduGame.muted }}>확인 중…</p>
      </div>
    )
  }

  const profile = getEduOperatorProfile()

  return (
    <div className="min-h-screen" style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}>
      <header
        className="border-b px-4 py-3 flex items-center justify-between max-w-5xl mx-auto w-full"
        style={{ borderColor: eduGame.border }}
      >
        <div>
          <p className="text-xs font-bold" style={{ color: eduGame.primary }}>운영자 전용</p>
          <h1 className="text-lg font-bold">부모 리포트 발송</h1>
          {(profile?.email || operatorName) && (
            <p className="text-xs mt-0.5" style={{ color: eduGame.muted }}>
              {operatorName || profile?.email}
            </p>
          )}
        </div>
        <div className="flex items-center gap-3 text-sm">
          <Link to="/edu" className="underline" style={{ color: eduGame.muted }}>
            EDU 홈
          </Link>
          <button type="button" onClick={handleLogout} className="underline" style={{ color: eduGame.muted }}>
            로그아웃
          </button>
        </div>
      </header>

      <main className="max-w-5xl mx-auto w-full px-4 py-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <section className="rounded-xl border p-3" style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}>
          <h2 className="text-sm font-bold mb-2">학생 목록</h2>
          {loadingList ? (
            <p className="text-sm" style={{ color: eduGame.muted }}>불러오는 중…</p>
          ) : (
            <ul className="space-y-1 max-h-[70vh] overflow-y-auto">
              {students.map((s) => (
                <li key={s.id}>
                  <button
                    type="button"
                    onClick={() => void selectStudent(s.id)}
                    className={`w-full text-left rounded-lg px-3 py-2.5 border transition-colors touch-manipulation ${
                      selectedId === s.id ? 'ring-2' : ''
                    }`}
                    style={{
                      borderColor: selectedId === s.id ? eduGame.primary : eduGame.border,
                      backgroundColor: eduGame.bg,
                      ...(selectedId === s.id ? { boxShadow: `0 0 0 2px ${eduGame.primaryRing}` } : {}),
                    }}
                  >
                    <div className="font-bold text-sm">{s.display_name || '이름 없음'}</div>
                    <div className="text-xs mt-0.5" style={{ color: eduGame.muted }}>
                      {s.coach_label_ko} · 완주 {s.completed_count} · {formatRelative(s.last_active_at)}
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="rounded-xl border p-4 min-h-[320px]" style={{ borderColor: eduGame.border }}>
          {!selectedId ? (
            <p className="text-sm" style={{ color: eduGame.muted }}>학생을 선택하면 리포트 미리보기가 표시됩니다.</p>
          ) : loadingPreview ? (
            <p className="text-sm" style={{ color: eduGame.muted }}>리포트 생성 중… (코치 편지 포함)</p>
          ) : report ? (
            <div className="space-y-4">
              <div className="rounded-xl p-4 text-white" style={{ backgroundColor: eduGame.ink }}>
                <p className="text-xs opacity-80 mb-1">● gistudy</p>
                <h3 className="text-xl font-bold leading-snug">{report.cover.headline}</h3>
                <p className="text-sm mt-2 opacity-90">
                  {report.student_name} · {report.grade_label}
                </p>
              </div>

              <div>
                <p className="text-xs font-bold mb-1" style={{ color: eduGame.primary }}>코치의 편지</p>
                {report.coach_letter.paragraphs.map((p, i) => (
                  <p key={i} className={`text-sm mb-2 ${eduGameClasses.textKo}`} style={{ lineHeight: 1.65 }}>
                    {p}
                  </p>
                ))}
                {report.coach_letter.fallback && (
                  <p className="text-xs" style={{ color: eduGame.muted }}>※ LLM 대신 기본 문구 사용</p>
                )}
              </div>

              {report.before_after && (
                <div>
                  <p className="text-xs font-bold mb-2" style={{ color: eduGame.primary }}>생각이 자란 순간</p>
                  <div className="grid gap-2 sm:grid-cols-2 text-sm">
                    <div className="rounded-lg border p-2" style={{ borderColor: eduGame.border }}>
                      <p className="text-xs" style={{ color: eduGame.muted }}>{report.before_after.before_label}</p>
                      <p className="font-bold mt-1">&ldquo;{report.before_after.before_text}&rdquo;</p>
                    </div>
                    <div
                      className="rounded-lg border p-2"
                      style={{ borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }}
                    >
                      <p className="text-xs" style={{ color: eduGame.primary }}>{report.before_after.after_label}</p>
                      <p className="font-bold mt-1">&ldquo;{report.before_after.after_text}&rdquo;</p>
                    </div>
                  </div>
                </div>
              )}

              {report.student_quote && (
                <blockquote
                  className="border-l-4 pl-3 text-sm font-bold"
                  style={{ borderColor: eduGame.primary, lineHeight: 1.55 }}
                >
                  &ldquo;{report.student_quote}&rdquo;
                </blockquote>
              )}

              <div className="flex flex-wrap gap-1.5">
                {report.topic_tags.map((tag) => (
                  <span
                    key={tag}
                    className="text-xs font-bold px-2 py-1 rounded-full border"
                    style={{ borderColor: eduGame.primary }}
                  >
                    {tag.length > 28 ? `${tag.slice(0, 28)}…` : tag}
                  </span>
                ))}
              </div>

              <div className="flex gap-4 text-center pt-2 border-t" style={{ borderColor: eduGame.border }}>
                <div className="flex-1">
                  <div className="text-2xl font-bold" style={{ color: eduGame.primary }}>{report.stats.completed_count}</div>
                  <div className="text-xs" style={{ color: eduGame.muted }}>완주</div>
                </div>
                <div className="flex-1">
                  <div className="text-2xl font-bold" style={{ color: eduGame.primary }}>{report.stats.streak_days}</div>
                  <div className="text-xs" style={{ color: eduGame.muted }}>연속(일)</div>
                </div>
                <div className="flex-1">
                  <div className="text-lg font-bold" style={{ color: eduGame.primary }}>{report.stats.coach_label_ko}</div>
                  <div className="text-xs" style={{ color: eduGame.muted }}>사고력</div>
                </div>
              </div>

              <div className="flex flex-col sm:flex-row gap-2 pt-2">
                <button
                  type="button"
                  disabled={loadingPdf}
                  onClick={() => void handleDownloadPdf()}
                  className={`flex-1 py-3 ${eduGameClasses.btnPrimary}`}
                  style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
                >
                  PDF 생성
                </button>
                <button
                  type="button"
                  disabled={loadingPdf}
                  onClick={() => void handleShare()}
                  className="flex-1 py-3 rounded-xl font-bold border-2 touch-manipulation"
                  style={{ borderColor: eduGame.primary, color: eduGame.primary, fontSize: eduGame.fontSize.button }}
                >
                  공유하기
                </button>
              </div>
            </div>
          ) : null}
        </section>
      </main>

      {error && (
        <p className="max-w-5xl mx-auto px-4 pb-4 text-sm text-red-600 text-center">{error}</p>
      )}
    </div>
  )
}
