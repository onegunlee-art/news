/**
 * 결제 등 외부 리다이렉트 직후, initializeAuth → fetchUser 와 인터셉터의
 * 토큰 갱신이 진행 중일 때 액세스 토큰이 잠시 비는 경우를 기다립니다.
 */
export async function waitForAccessToken(maxMs = 5000, stepMs = 150): Promise<string | null> {
  const deadline = Date.now() + maxMs
  while (Date.now() < deadline) {
    const t = typeof localStorage !== 'undefined' ? localStorage.getItem('access_token') : null
    if (t) return t
    await new Promise((r) => setTimeout(r, stepMs))
  }
  return typeof localStorage !== 'undefined' ? localStorage.getItem('access_token') : null
}
