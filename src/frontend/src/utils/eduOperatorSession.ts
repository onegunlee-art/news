const EDU_OPERATOR_TOKEN_KEY = 'edu_operator_token'
const EDU_OPERATOR_PROFILE_KEY = 'edu_operator_profile'

export type EduOperatorProfile = {
  id: string
  email: string
  display_name: string
  role?: string | null
  organization_id?: string | null
  organization_name?: string | null
}

export function getEduOperatorToken(): string | null {
  try {
    return localStorage.getItem(EDU_OPERATOR_TOKEN_KEY)
  } catch {
    return null
  }
}

export function setEduOperatorSession(token: string, operator: EduOperatorProfile): void {
  localStorage.setItem(EDU_OPERATOR_TOKEN_KEY, token)
  localStorage.setItem(EDU_OPERATOR_PROFILE_KEY, JSON.stringify(operator))
}

export function clearEduOperatorSession(): void {
  localStorage.removeItem(EDU_OPERATOR_TOKEN_KEY)
  localStorage.removeItem(EDU_OPERATOR_PROFILE_KEY)
}

export function getEduOperatorProfile(): EduOperatorProfile | null {
  try {
    const raw = localStorage.getItem(EDU_OPERATOR_PROFILE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as EduOperatorProfile
    if (!parsed?.id) return null
    return parsed
  } catch {
    return null
  }
}

export function hasEduOperatorSession(): boolean {
  return !!getEduOperatorToken()
}
