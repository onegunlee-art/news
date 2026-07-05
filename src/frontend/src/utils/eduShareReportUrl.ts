export type EduShareUrlResult = 'shared' | 'copied' | 'cancelled'

export async function shareReportUrl(
  url: string,
  studentName: string,
  headline: string
): Promise<EduShareUrlResult> {
  const title = `${studentName} gistudy 리포트`
  const text = headline

  if (typeof navigator.share === 'function') {
    try {
      await navigator.share({ title, text, url })
      return 'shared'
    } catch (err) {
      if ((err as Error)?.name === 'AbortError') {
        return 'cancelled'
      }
    }
  }

  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(url)
    return 'copied'
  }

  throw new Error('공유·복사를 지원하지 않는 브라우저입니다.')
}

export function shareReportUrlMessage(result: EduShareUrlResult): string | null {
  if (result === 'shared') return '리포트 링크를 공유했어요.'
  if (result === 'copied') return '리포트 링크를 복사했어요. 카카오톡 등에 붙여넣어 보내세요.'
  return null
}
