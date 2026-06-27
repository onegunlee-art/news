const STORAGE_KEY = 'edu_level_debug'

/** ?level_debug=1 로 켜면 localStorage에 유지 (내부 검증용) */
export function resolveEduLevelDebug(searchParams: URLSearchParams): boolean {
  if (searchParams.get('level_debug') === '1') {
    localStorage.setItem(STORAGE_KEY, '1')
    return true
  }
  if (searchParams.get('level_debug') === '0') {
    localStorage.removeItem(STORAGE_KEY)
    return false
  }
  return localStorage.getItem(STORAGE_KEY) === '1'
}

export function isEduLevelDebugEnabled(): boolean {
  return localStorage.getItem(STORAGE_KEY) === '1'
}

/** 서버 allowlist + (선택) URL 플래그 — 탭 UI 게이트 */
export function canShowCoachLevelDebugSwitch(levelDebugAllowed: boolean): boolean {
  return levelDebugAllowed || isEduLevelDebugEnabled()
}
