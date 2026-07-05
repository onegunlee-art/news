import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import EduOperatorReportPanel from '../../components/edu/EduOperatorReportPanel'
import { eduGame } from '../../constants/eduGameTheme'
import {
  eduOperatorDownloadPdf,
  eduOperatorCreateReportShareLink,
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
  type EduOperatorProfile,
} from '../../utils/eduOperatorSession'
import { shareReportUrl, shareReportUrlMessage } from '../../utils/eduShareReportUrl'
import { downloadPdfFile } from '../../utils/eduSharePdf'

const DASHBOARD_PATH = '/edu/dashboard'
const LOGIN_PATH = '/edu/operator/login'

const ROLE_LABEL: Record<string, string> = {
  owner: '원장',
  teacher: '교사',
}

function formatRelative(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

export default function EduDashboardPage() {
  const navigate = useNavigate()
  const [ready, setReady] = useState(false)
  const [operator, setOperator] = useState<EduOperatorProfile | null>(null)

  const [students, setStudents] = useState<EduOperatorStudent[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [report, setReport] = useState<EduParentReportPayload | null>(null)
  const [loadingList, setLoadingList] = useState(true)
  const [loadingPreview, setLoadingPreview] = useState(false)
  const [loadingPdf, setLoadingPdf] = useState(false)
  const [loadingShare, setLoadingShare] = useState(false)
  const [error, setError] = useState('')
  const [shareHint, setShareHint] = useState('')

  useEffect(() => {
    if (!hasEduOperatorSession()) {
      navigate(`${LOGIN_PATH}?returnTo=${encodeURIComponent(DASHBOARD_PATH)}`, { replace: true })
      return
    }

    void (async () => {
      try {
        const token = getEduOperatorToken()
        if (!token) throw new Error('no token')
        const profile = await eduOperatorVerifySession()
        setEduOperatorSession(token, profile)
        setOperator(profile)
        setReady(true)
      } catch {
        clearEduOperatorSession()
        navigate(`${LOGIN_PATH}?returnTo=${encodeURIComponent(DASHBOARD_PATH)}`, { replace: true })
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

  const summary = useMemo(() => {
    if (students.length === 0) {
      return { count: 0, totalCompleted: 0, avgStreak: 0 }
    }
    const totalCompleted = students.reduce((sum, s) => sum + s.completed_count, 0)
    const avgStreak = Math.round(
      students.reduce((sum, s) => sum + s.streak_days, 0) / students.length
    )
    return { count: students.length, totalCompleted, avgStreak }
  }, [students])

  const selectedStudent = students.find((s) => s.id === selectedId) ?? null

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

  const handleShare = async () => {
    if (!selectedId || !report) return
    setShareHint('')
    setError('')
    setLoadingShare(true)
    try {
      const { share_url } = await eduOperatorCreateReportShareLink(selectedId)
      const result = await shareReportUrl(share_url, report.student_name, report.cover.headline)
      const hint = shareReportUrlMessage(result)
      if (hint) setShareHint(hint)
    } catch (e) {
      setError(e instanceof Error ? e.message : '링크 공유 실패')
    } finally {
      setLoadingShare(false)
    }
  }

  const handleDownload = async () => {
    setShareHint('')
    setError('')
    const result = await fetchPdfBlob()
    if (!result) return
    try {
      downloadPdfFile(result.blob, result.filename)
      setShareHint('PDF 다운로드가 시작됐어요.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'PDF 저장 실패')
    }
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

  const profile = operator ?? getEduOperatorProfile()
  const orgLabel = profile?.organization_name ?? '전체 (super)'
  const roleLabel = profile?.role ? (ROLE_LABEL[profile.role] ?? profile.role) : null

  return (
    <div
      className="min-h-screen"
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <header
        className="border-b px-4 py-3 flex items-center justify-between max-w-5xl mx-auto w-full"
        style={{ borderColor: eduGame.border }}
      >
        <div>
          <p className="text-xs font-bold" style={{ color: eduGame.primary }}>
            gistudy · {orgLabel}
          </p>
          <h1 className="text-lg font-bold">학원 대시보드</h1>
          <p className="text-xs mt-0.5" style={{ color: eduGame.muted }}>
            {profile?.display_name || profile?.email}
            {roleLabel ? ` · ${roleLabel}` : ''}
          </p>
        </div>
        <div className="flex items-center gap-3 text-sm">
          <Link to="/edu/operator/reports" className="underline" style={{ color: eduGame.muted }}>
            리포트 (구)
          </Link>
          <Link to="/edu" className="underline" style={{ color: eduGame.muted }}>
            EDU 홈
          </Link>
          <button type="button" onClick={handleLogout} className="underline" style={{ color: eduGame.muted }}>
            로그아웃
          </button>
        </div>
      </header>

      <main className="max-w-5xl mx-auto w-full px-4 py-4 space-y-4">
        <section className="grid grid-cols-3 gap-2">
          {(
            [
              ['학생', summary.count, '명'],
              ['총 완주', summary.totalCompleted, '회'],
              ['평균 연속', summary.avgStreak, '일'],
            ] as const
          ).map(([label, value, unit]) => (
            <div
              key={label}
              className="rounded-xl border p-3 text-center"
              style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
            >
              <p className="text-xs" style={{ color: eduGame.muted }}>
                {label}
              </p>
              <p className="text-xl font-bold mt-0.5" style={{ color: eduGame.primary }}>
                {value}
                <span className="text-xs font-normal ml-0.5">{unit}</span>
              </p>
            </div>
          ))}
        </section>

        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
          <section
            className="rounded-xl border p-3"
            style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
          >
            <h2 className="text-sm font-bold mb-2">학생 목록</h2>
            {loadingList ? (
              <p className="text-sm" style={{ color: eduGame.muted }}>
                불러오는 중…
              </p>
            ) : students.length === 0 ? (
              <p className="text-sm" style={{ color: eduGame.muted }}>
                표시할 학생이 없습니다. org 배정을 확인하세요.
              </p>
            ) : (
              <ul className="space-y-1 max-h-[60vh] overflow-y-auto">
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
                        {s.coach_label_ko} · 완주 {s.completed_count} · 🔥 {s.streak_days}일 ·{' '}
                        {formatRelative(s.last_active_at)}
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="rounded-xl border p-4 min-h-[320px]" style={{ borderColor: eduGame.border }}>
            {!selectedId ? (
              <p className="text-sm" style={{ color: eduGame.muted }}>
                학생을 선택하면 성장 요약과 부모 리포트가 표시됩니다.
              </p>
            ) : (
              <>
                {selectedStudent && (
                  <div
                    className="mb-4 rounded-lg border p-3 grid grid-cols-3 gap-2 text-center text-sm"
                    style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
                  >
                    <div>
                      <p className="text-xs" style={{ color: eduGame.muted }}>
                        완주
                      </p>
                      <p className="font-bold" style={{ color: eduGame.primary }}>
                        {selectedStudent.completed_count}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs" style={{ color: eduGame.muted }}>
                        연속
                      </p>
                      <p className="font-bold" style={{ color: eduGame.primary }}>
                        {selectedStudent.streak_days}일
                      </p>
                    </div>
                    <div>
                      <p className="text-xs" style={{ color: eduGame.muted }}>
                        사고력
                      </p>
                      <p className="font-bold text-xs" style={{ color: eduGame.primary }}>
                        {selectedStudent.coach_label_ko}
                      </p>
                    </div>
                  </div>
                )}

                {loadingPreview ? (
                  <p className="text-sm" style={{ color: eduGame.muted }}>
                    리포트 생성 중… (코치 편지 포함)
                  </p>
                ) : report ? (
                  <EduOperatorReportPanel
                    report={report}
                    loadingPdf={loadingShare || loadingPdf}
                    onShare={() => void handleShare()}
                    onDownload={() => void handleDownload()}
                  />
                ) : null}
              </>
            )}
          </section>
        </div>
      </main>

      {error && (
        <p className="max-w-5xl mx-auto px-4 pb-2 text-sm text-red-600 text-center">{error}</p>
      )}
      {shareHint && (
        <p
          className="max-w-5xl mx-auto px-4 pb-4 text-sm text-center font-medium"
          style={{ color: eduGame.primary }}
        >
          {shareHint}
        </p>
      )}
    </div>
  )
}
