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

/** Mobile / tablet — file share via Web Share API is realistic */
export function eduSharePdfDeviceLikelySupportsFiles(): boolean {
  if (typeof navigator === 'undefined') return false
  const ua = navigator.userAgent
  if (/Android|iPhone|iPad|iPod/i.test(ua)) return true
  // iPadOS desktop UA
  if (navigator.maxTouchPoints > 1 && /Macintosh/i.test(ua)) return true
  return false
}

export function eduSharePdfCanShareFiles(file: File): boolean {
  if (typeof navigator.share !== 'function') return false
  if (!eduSharePdfDeviceLikelySupportsFiles()) return false
  if (typeof navigator.canShare !== 'function') return true
  try {
    return navigator.canShare({ files: [file] })
  } catch {
    return false
  }
}

export function eduSharePdfLooksValid(blob: Blob): boolean {
  return blob.size > 100
}

/**
 * PDF 공유 — 모바일(파일 share 지원): 기기 공유 시트.
 * 그 외 / 실패: 즉시 다운로드 폴백 (데스크탑 "다시 시도" 팝업 방지).
 */
export async function sharePdfFile(
  blob: Blob,
  filename: string,
  meta: { title: string; text: string }
): Promise<EduSharePdfResult> {
  if (!eduSharePdfLooksValid(blob)) {
    throw new Error('PDF 파일이 비어 있거나 손상됐습니다.')
  }

  const pdfBlob =
    blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
  const file = new File([pdfBlob], filename, { type: 'application/pdf' })

  if (eduSharePdfCanShareFiles(file)) {
    try {
      await navigator.share({
        title: meta.title,
        text: meta.text,
        files: [file],
      })
      return 'shared'
    } catch (err) {
      const name = (err as Error)?.name ?? ''
      if (name === 'AbortError') return 'cancelled'
      // NotAllowedError / DataError etc. → download fallback (no second share attempt)
    }
  }

  triggerPdfDownload(pdfBlob, filename)
  return 'downloaded'
}

export function sharePdfResultMessage(result: EduSharePdfResult): string | null {
  if (result === 'shared' || result === 'cancelled') return null
  if (eduSharePdfDeviceLikelySupportsFiles()) {
    return '공유가 되지 않아 PDF를 저장했어요. 카카오톡 → 채팅 → + → 파일에서 PDF를 선택해 보내세요.'
  }
  return 'PDF 다운로드가 시작됐어요. 저장된 파일을 카카오톡 등으로 보내 주세요.'
}

/** Explicit download (secondary action) */
export function downloadPdfFile(blob: Blob, filename: string): void {
  if (!eduSharePdfLooksValid(blob)) {
    throw new Error('PDF 파일이 비어 있거나 손상됐습니다.')
  }
  const pdfBlob =
    blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
  triggerPdfDownload(pdfBlob, filename)
}
