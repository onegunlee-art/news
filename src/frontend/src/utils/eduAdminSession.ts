const EDU_ADMIN_KEY = 'edu_admin_api_key'

export function getEduAdminKey(): string | null {
  try {
    return sessionStorage.getItem(EDU_ADMIN_KEY)
  } catch {
    return null
  }
}

export function setEduAdminKey(key: string): void {
  sessionStorage.setItem(EDU_ADMIN_KEY, key)
}

export function clearEduAdminKey(): void {
  sessionStorage.removeItem(EDU_ADMIN_KEY)
}

export function hasEduAdminKey(): boolean {
  return !!getEduAdminKey()
}
