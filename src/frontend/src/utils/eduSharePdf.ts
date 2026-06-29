export type EduSharePdfResult = 'shared' | 'downloaded' | 'cancelled'

function triggerPdfDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.setTimeout(() => URL.revokeObjectURL(url), 5000)
}

/**
 * PDF 공유 — 모바일: 기기 공유 시트(카톡 등). PC: 다운로드 + 안내.
 * canShare가 false여도 share() 시도 (iOS/Android 호환).
 */
export async function sharePdfFile(
  blob: Blob,
  filename: string,
  meta: { title: string; text: string }
): Promise<EduSharePdfResult> {
  const pdfBlob = blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
  const file = new File([pdfBlob], filename, { type: 'application/pdf' })

  if (typeof navigator.share === 'function') {
    const payload: ShareData = {
      title: meta.title,
      text: meta.text,
      files: [file],
    }

    const canTryFiles =
      typeof navigator.canShare !== 'function' || navigator.canShare(payload)

    if (canTryFiles) {
      try {
        await navigator.share(payload)
        return 'shared'
      } catch (err) {
        const name = (err as Error)?.name ?? ''
        if (name === 'AbortError') return 'cancelled'
      }
    }

    try {
      await navigator.share({
        title: meta.title,
        text: `${meta.text}\n\n(PDF는 저장 후 카카오톡에서 파일로 첨부해 주세요)`,
      })
      triggerPdfDownload(pdfBlob, filename)
      return 'shared'
    } catch (err) {
      const name = (err as Error)?.name ?? ''
      if (name === 'AbortError') return 'cancelled'
    }
  }

  triggerPdfDownload(pdfBlob, filename)
  return 'downloaded'
}

export function sharePdfResultMessage(result: EduSharePdfResult): string | null {
  if (result === 'shared') return null
  if (result === 'cancelled') return null
  return 'PDF가 저장됐어요. 카카오톡 → 채팅 → + → 파일에서 PDF를 선택해 보내세요.'
}
