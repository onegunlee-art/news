const STORAGE_KEY = 'edu_tech_transparency'

/** ?tech_transparency=1 — 심사·데모용 내부 상태 패널 (기본 off) */
export function resolveEduTechTransparency(searchParams: URLSearchParams): boolean {
  if (searchParams.get('tech_transparency') === '1') {
    localStorage.setItem(STORAGE_KEY, '1')
    return true
  }
  if (searchParams.get('tech_transparency') === '0') {
    localStorage.removeItem(STORAGE_KEY)
    return false
  }
  return localStorage.getItem(STORAGE_KEY) === '1'
}

export function isEduTechTransparencyEnabled(): boolean {
  return localStorage.getItem(STORAGE_KEY) === '1'
}
