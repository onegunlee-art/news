import {
  getEduOperatorToken,
  type EduOperatorProfile,
} from '../utils/eduOperatorSession'

const BASE = '/api/edu/operator/reports.php'
const LOGIN_URL = '/api/edu/operator/login.php'

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

async function operatorFetch(url: string, init?: RequestInit): Promise<Response> {
  const token = getEduOperatorToken()
  const headers = new Headers(init?.headers)
  if (!headers.has('Content-Type') && !(init?.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (token) {
    headers.set('X-Edu-Operator-Token', token)
  }
  return fetch(url, { ...init, headers })
}

async function parseJson<T>(res: Response): Promise<T> {
  const data = await res.json()
  if (!res.ok || data.success === false) {
    throw new Error(data.error || data.message || `HTTP ${res.status}`)
  }
  return data as T
}

export async function eduOperatorLogin(
  email: string,
  password: string
): Promise<{ token: string; operator: EduOperatorProfile }> {
  const res = await fetch(LOGIN_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  const data = await parseJson<{ token: string; operator: EduOperatorProfile }>(res)
  return { token: data.token, operator: data.operator }
}

export async function eduOperatorVerifySession(): Promise<EduOperatorProfile> {
  const res = await operatorFetch('/api/edu/operator/me.php')
  const data = await parseJson<{ operator: EduOperatorProfile }>(res)
  return data.operator
}

export async function eduOperatorListStudents(): Promise<EduOperatorStudent[]> {
  const res = await operatorFetch(`${BASE}?action=students`)
  const data = await parseJson<{ students: EduOperatorStudent[] }>(res)
  return data.students ?? []
}

export async function eduOperatorPreviewReport(studentId: string): Promise<EduParentReportPayload> {
  const res = await operatorFetch(
    `${BASE}?action=preview&student_id=${encodeURIComponent(studentId)}`
  )
  const data = await parseJson<{ report: EduParentReportPayload }>(res)
  return data.report
}

export async function eduOperatorDownloadPdf(
  studentId: string
): Promise<{ blob: Blob; filename: string }> {
  const res = await operatorFetch(`${BASE}?action=pdf`, {
    method: 'POST',
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
  const contentType = res.headers.get('Content-Type') ?? ''
  if (!contentType.includes('application/pdf') && blob.size < 100) {
    throw new Error('PDF 생성에 실패했습니다.')
  }
  const disposition = res.headers.get('Content-Disposition') ?? ''
  const match = disposition.match(/filename="([^"]+)"/)
  const filename = match?.[1] ?? `gistudy-report-${studentId.slice(0, 8)}.pdf`
  return { blob, filename }
}
