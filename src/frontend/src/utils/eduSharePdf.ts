export type EduSharePdfResult = 'shared' | 'downloaded' | 'cancelled'

export type EduSharePdfMeta = {
  title: string
  text: string
  /** Optional public URL (parent report view link, if available) */
  url?: string
}

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

function shareAbort(err: unknown): boolean {
  return (err as Error)?.name === 'AbortError'
}

/** Mobile browser — includes Samsung Internet (canShare(files) often false) */
export function eduSharePdfIsMobileBrowser(): boolean {
  if (typeof navigator === 'undefined') return false
  const ua = navigator.userAgent
  if (/SamsungBrowser/i.test(ua)) return true
  if (/Android|iPhone|iPad|iPod|Mobile/i.test(ua)) return true
  if (navigator.maxTouchPoints > 1 && /Macintosh/i.test(ua)) return true
  return false
}

export function eduSharePdfCanSharePayload(data: ShareData): boolean {
  if (typeof navigator.share !== 'function') return false
  if (typeof navigator.canShare !== 'function') return true
  try {
    return navigator.canShare(data)
  } catch {
    return false
  }
}

export function eduSharePdfCanShareFiles(file: File): boolean {
  if (!eduSharePdfIsMobileBrowser()) return false
  return eduSharePdfCanSharePayload({ files: [file] })
}

export function eduSharePdfBuildTextSharePayload(meta: EduSharePdfMeta): ShareData {
  const lines = [meta.text.trim(), '', `${meta.title} — gistudy 탐구 성장 리포트`]
  if (meta.url?.trim()) {
    lines.push('', meta.url.trim())
  } else {
    lines.push('', 'PDF는 gistudy 대시보드에서 저장·전달할 수 있습니다.')
  }
  const payload: ShareData = {
    title: meta.title,
    text: lines.join('\n'),
  }
  const url = meta.url?.trim()
  if (url) {
    payload.url = url
  }
  return payload
}

export function eduSharePdfCanShareText(meta: EduSharePdfMeta): boolean {
  if (!eduSharePdfIsMobileBrowser()) return false
  return eduSharePdfCanSharePayload(eduSharePdfBuildTextSharePayload(meta))
}

/** @deprecated use eduSharePdfIsMobileBrowser */
export function eduSharePdfDeviceLikelySupportsFiles(): boolean {
  return eduSharePdfIsMobileBrowser()
}

export function eduSharePdfLooksValid(blob: Blob): boolean {
  return blob.size > 100
}

/**
 * PDF 공유 폴백 순서 (모바일):
 *   a) PDF 파일 share (canShare(files))
 *   b) 텍스트/URL share — Samsung Internet 등 파일 미지원
 *   c) 다운로드
 * 데스크탑: share 시도 없이 다운로드만
 */
export async function sharePdfFile(
  blob: Blob,
  filename: string,
  meta: EduSharePdfMeta
): Promise<EduSharePdfResult> {
  if (!eduSharePdfLooksValid(blob)) {
    throw new Error('PDF 파일이 비어 있거나 손상됐습니다.')
  }

  const pdfBlob =
    blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
  const file = new File([pdfBlob], filename, { type: 'application/pdf' })

  if (!eduSharePdfIsMobileBrowser()) {
    triggerPdfDownload(pdfBlob, filename)
    return 'downloaded'
  }

  // a) File share (best on iOS Safari, Android Chrome)
  if (eduSharePdfCanShareFiles(file)) {
    try {
      await navigator.share({
        title: meta.title,
        text: meta.text,
        files: [file],
      })
      return 'shared'
    } catch (err) {
      if (shareAbort(err)) return 'cancelled'
    }
  }

  // b) Text / URL share — Samsung Internet and similar
  const textPayload = eduSharePdfBuildTextSharePayload(meta)
  if (eduSharePdfCanSharePayload(textPayload)) {
    try {
      await navigator.share(textPayload)
      return 'shared'
    } catch (err) {
      if (shareAbort(err)) return 'cancelled'
    }
  }

  // c) Download fallback
  triggerPdfDownload(pdfBlob, filename)
  return 'downloaded'
}

export function sharePdfResultMessage(result: EduSharePdfResult): string | null {
  if (result === 'shared' || result === 'cancelled') return null
  if (eduSharePdfIsMobileBrowser()) {
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
