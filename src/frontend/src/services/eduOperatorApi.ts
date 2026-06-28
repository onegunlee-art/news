import { adminFetch } from './api'

const BASE = '/api/admin/edu-parent-report.php'

export type EduOperatorStudent = {
  id: string
  display_name: string
  grade_band: string
  coach_level: number
  coach_label_ko: string
  completed_count: number
  streak_days: number
  last_active_at: string | null
}

export type EduParentReportPayload = {
  student_id: string
  student_name: string
  grade_label: string
  period_label: string
  cover: { headline_count: number; headline: string }
  coach_letter: { paragraphs: string[]; generated?: boolean; fallback?: boolean }
  before_after: {
    before_label: string
    before_quest: string
    before_text: string
    after_label: string
    after_quest: string
    after_text: string
  } | null
  student_quote: string
  growth_path: Array<{ level: number; label_ko: string; current: boolean; done: boolean }>
  topic_tags: string[]
  stats: {
    completed_count: number
    streak_days: number
    coach_level: number
    coach_label_ko: string
  }
}

async function parseJson<T>(res: Response): Promise<T> {
  const data = await res.json()
  if (!res.ok || data.success === false) {
    throw new Error(data.error || data.message || `HTTP ${res.status}`)
  }
  return data as T
}

export async function eduOperatorListStudents(): Promise<EduOperatorStudent[]> {
  const res = await adminFetch(`${BASE}?action=students`)
  const data = await parseJson<{ students: EduOperatorStudent[] }>(res)
  return data.students ?? []
}

export async function eduOperatorPreviewReport(studentId: string): Promise<EduParentReportPayload> {
  const res = await adminFetch(
    `${BASE}?action=preview&student_id=${encodeURIComponent(studentId)}`
  )
  const data = await parseJson<{ report: EduParentReportPayload }>(res)
  return data.report
}

export async function eduOperatorDownloadPdf(studentId: string): Promise<{ blob: Blob; filename: string }> {
  const res = await adminFetch(`${BASE}?action=pdf`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'pdf', student_id: studentId }),
  })
  if (!res.ok) {
    let msg = `HTTP ${res.status}`
    try {
      const err = await res.json()
      msg = err.error || err.message || msg
    } catch {
      /* binary */
    }
    throw new Error(msg)
  }
  const blob = await res.blob()
  const disposition = res.headers.get('Content-Disposition') ?? ''
  const match = disposition.match(/filename="([^"]+)"/)
  const filename = match?.[1] ?? `gistudy-report-${studentId.slice(0, 8)}.pdf`
  return { blob, filename }
}
