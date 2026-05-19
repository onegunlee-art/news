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

/** Axios/일반 Error에서 API source 추출 (결제 오류 등 상세 분류용) */
export function apiErrorSource(err: unknown, fallback: string): string {
  if (isAxiosError(err)) {
    const d = err.response?.data
    if (d && typeof d === 'object' && d !== null && 'source' in d) {
      const s = (d as { source?: unknown }).source
      if (typeof s === 'string' && s) return s
    }
  }
  return fallback
}

/** Axios 에러에서 source와 message를 함께 추출 */
export function apiErrorDetail(
  err: unknown,
  fallbackSource: string,
  fallbackMessage: string
): { source: string; message: string } {
  return {
    source: apiErrorSource(err, fallbackSource),
    message: apiErrorMessage(err, fallbackMessage),
  }
}
