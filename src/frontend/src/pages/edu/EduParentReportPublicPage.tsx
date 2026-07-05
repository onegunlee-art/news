import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import EduOperatorReportPanel from '../../components/edu/EduOperatorReportPanel'
import { eduGame } from '../../constants/eduGameTheme'
import type { EduParentReportPayload } from '../../services/eduOperatorApi'

export default function EduParentReportPublicPage() {
  const { token } = useParams<{ token: string }>()
  const [report, setReport] = useState<EduParentReportPayload | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!token) {
      setError('링크가 올바르지 않습니다.')
      setLoading(false)
      return
    }

    void (async () => {
      try {
        const res = await fetch(
          `/api/edu/parent_report_view.php?token=${encodeURIComponent(token)}`
        )
        const raw = await res.text()
        let data: { success?: boolean; error?: string; report?: EduParentReportPayload }
        try {
          data = JSON.parse(raw) as typeof data
        } catch {
          throw new Error(res.status === 401 ? '인증 오류 — 공개 API 경로 확인 필요' : '리포트 응답 형식 오류')
        }
        if (!res.ok || !data.success) {
          throw new Error(data.error || '리포트를 불러오지 못했습니다.')
        }
        setReport(data.report as EduParentReportPayload)
      } catch (e) {
        setError(e instanceof Error ? e.message : '리포트를 불러오지 못했습니다.')
      } finally {
        setLoading(false)
      }
    })()
  }, [token])

  if (loading) {
    return (
      <div
        className="min-h-screen flex items-center justify-center px-4"
        style={{ backgroundColor: eduGame.bg, color: eduGame.muted }}
      >
        리포트 불러오는 중…
      </div>
    )
  }

  if (error || !report) {
    return (
      <div
        className="min-h-screen flex items-center justify-center px-4 text-center"
        style={{ backgroundColor: eduGame.bg, color: eduGame.ink }}
      >
        <div>
          <p className="font-bold mb-2">리포트를 열 수 없어요</p>
          <p className="text-sm" style={{ color: eduGame.muted }}>
            {error || '링크가 만료되었거나 잘못되었습니다.'}
          </p>
        </div>
      </div>
    )
  }

  return (
    <div
      className="min-h-screen py-6 px-4"
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <div className="max-w-lg mx-auto">
        <p className="text-xs text-center mb-4" style={{ color: eduGame.muted }}>
          gistudy · 부모 리포트
        </p>
        <EduOperatorReportPanel report={report} loadingPdf={false} publicView />
      </div>
    </div>
  )
}
