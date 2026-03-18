import { isAxiosError } from 'axios'

/** Axios/일반 Error에서 API 메시지 추출 */
export function apiErrorMessage(err: unknown, fallback: string): string {
  if (isAxiosError(err)) {
    const d = err.response?.data
    if (d && typeof d === 'object' && d !== null && 'message' in d) {
      const m = (d as { message?: unknown }).message
      if (typeof m === 'string' && m) return m
    }
    return err.message || fallback
  }
  if (err instanceof Error) return err.message || fallback
  return fallback
}
