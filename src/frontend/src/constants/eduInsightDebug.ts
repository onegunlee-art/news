export interface EduStructureInsightDebug {
  saved: boolean
  diagnose_mode: string | null
  diagnose_version: string | null
  exploration_depth_level: number | null
  tension_engaged: string | null
  conclusion_clarity: string | null
  evidence_linked: string | null
  axes_engaged_count: number | null
  axes_total: number | null
  structure_note: string | null
  fallback_reason: string | null
}

const STORAGE_KEY = 'edu_insight_debug'

/** ?insight_debug=1 로 켜면 localStorage에 유지 (내부 검증용) */
export function resolveEduInsightDebug(searchParams: URLSearchParams): boolean {
  if (searchParams.get('insight_debug') === '1') {
    localStorage.setItem(STORAGE_KEY, '1')
    return true
  }
  if (searchParams.get('insight_debug') === '0') {
    localStorage.removeItem(STORAGE_KEY)
    return false
  }
  return localStorage.getItem(STORAGE_KEY) === '1'
}

export function isEduInsightDebugEnabled(): boolean {
  return localStorage.getItem(STORAGE_KEY) === '1'
}
