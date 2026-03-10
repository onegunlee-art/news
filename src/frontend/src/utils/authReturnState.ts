/** 로그인/회원가입/카카오 콜백 후 복귀할 경로 및 의도 (세션 저장) */
const AUTH_RETURN_KEY = 'auth_returnTo'
const AUTH_INTENT_KEY = 'auth_intent'

export function saveAuthReturnState(returnTo?: string, intent?: string) {
  if (returnTo) sessionStorage.setItem(AUTH_RETURN_KEY, returnTo)
  else sessionStorage.removeItem(AUTH_RETURN_KEY)
  if (intent) sessionStorage.setItem(AUTH_INTENT_KEY, intent)
  else sessionStorage.removeItem(AUTH_INTENT_KEY)
}

export function consumeAuthReturnState(): { returnTo?: string; intent?: string } {
  const returnTo = sessionStorage.getItem(AUTH_RETURN_KEY)
  const intent = sessionStorage.getItem(AUTH_INTENT_KEY)
  sessionStorage.removeItem(AUTH_RETURN_KEY)
  sessionStorage.removeItem(AUTH_INTENT_KEY)
  return { returnTo: returnTo || undefined, intent: intent || undefined }
}

export function getAuthRedirectTarget(
  returnTo?: string,
  intent?: string,
  isAdmin?: boolean
): string {
  if (isAdmin) return '/admin'
  if (intent === 'subscribe') return returnTo || '/subscribe'
  return returnTo || '/'
}
