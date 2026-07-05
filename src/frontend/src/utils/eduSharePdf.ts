import {
  eduShareDiagEnabled,
  eduShareDiagError,
  eduShareDiagFinish,
  eduShareDiagStart,
  eduShareDiagStep,
  type EduShareDiag,
} from './eduSharePdfDiagnose'

export type EduSharePdfResult = 'shared' | 'downloaded' | 'cancelled'

export type EduSharePdfMeta = {
  title: string
  text: string
  url?: string
}

export type EduSharePdfOutcome = {
  result: EduSharePdfResult
  diagnostics: EduShareDiag
  gestureBlocked: boolean
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

function isGestureError(err: unknown): boolean {
  const e = err as Error
  const name = e?.name ?? ''
  const msg = (e?.message ?? '').toLowerCase()
  return (
    name === 'NotAllowedError' ||
    msg.includes('gesture') ||
    msg.includes('user denied') ||
    msg.includes('permission')
  )
}

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
 * PDF 공유 — diagnostics always logged to console.
 * Pass existingDiag when PDF was fetched before share (measures gesture gap).
 */
export async function sharePdfFile(
  blob: Blob,
  filename: string,
  meta: EduSharePdfMeta,
  existingDiag?: EduShareDiag
): Promise<EduSharePdfOutcome> {
  const diag = existingDiag ?? eduShareDiagStart()
  let gestureBlocked = false

  eduShareDiagStep(diag, 4, 'sharePdfFile enter', `blob=${blob.size} type=${blob.type || '?'}`)

  if (!eduSharePdfLooksValid(blob)) {
    throw new Error('PDF 파일이 비어 있거나 손상됐습니다.')
  }

  const pdfBlob =
    blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
  const file = new File([pdfBlob], filename, { type: 'application/pdf' })

  const isMobile = eduSharePdfIsMobileBrowser()
  eduShareDiagStep(
    diag,
    5,
    'device',
    `mobile=${isMobile} ua=${typeof navigator !== 'undefined' ? navigator.userAgent.slice(0, 80) : '?'}`
  )

  const canFiles = eduSharePdfCanShareFiles(file)
  const textPayload = eduSharePdfBuildTextSharePayload(meta)
  const canText = eduSharePdfCanSharePayload(textPayload)
  eduShareDiagStep(
    diag,
    6,
    'canShare',
    `files=${canFiles} text=${canText} hasShare=${typeof navigator.share}`
  )

  if (!isMobile) {
    eduShareDiagStep(diag, 7, 'branch', 'desktop → download')
    triggerPdfDownload(pdfBlob, filename)
    eduShareDiagFinish(diag, 'desktop_download', 'downloaded')
    return { result: 'downloaded', diagnostics: diag, gestureBlocked: false }
  }

  if (canFiles) {
    eduShareDiagStep(diag, 7, 'branch', 'a: file share')
    try {
      eduShareDiagStep(diag, 8, 'navigator.share files')
      await navigator.share({ title: meta.title, text: meta.text, files: [file] })
      eduShareDiagFinish(diag, 'file_share', 'shared')
      return { result: 'shared', diagnostics: diag, gestureBlocked: false }
    } catch (err) {
      if (shareAbort(err)) {
        eduShareDiagFinish(diag, 'file_share', 'cancelled')
        return { result: 'cancelled', diagnostics: diag, gestureBlocked: false }
      }
      eduShareDiagError(diag, 'file_share', err)
      if (isGestureError(err)) gestureBlocked = true
    }
  } else {
    eduShareDiagStep(diag, 7, 'branch skip files', 'canShare(files)=false')
  }

  if (canText) {
    eduShareDiagStep(diag, 7, 'branch', 'b: text share')
    try {
      eduShareDiagStep(diag, 8, 'navigator.share text')
      await navigator.share(textPayload)
      eduShareDiagFinish(diag, 'text_share', 'shared')
      return { result: 'shared', diagnostics: diag, gestureBlocked: false }
    } catch (err) {
      if (shareAbort(err)) {
        eduShareDiagFinish(diag, 'text_share', 'cancelled')
        return { result: 'cancelled', diagnostics: diag, gestureBlocked: false }
      }
      eduShareDiagError(diag, 'text_share', err)
      if (isGestureError(err)) gestureBlocked = true
    }
  } else {
    eduShareDiagStep(diag, 7, 'branch skip text', 'canShare(text)=false')
  }

  eduShareDiagStep(diag, 7, 'branch', 'c: download fallback')
  triggerPdfDownload(pdfBlob, filename)
  eduShareDiagFinish(diag, 'download_fallback', 'downloaded')

  if (gestureBlocked && eduShareDiagEnabled()) {
    throw new Error(
      `share blocked (${diag.lastError?.name}): PDF fetch 후 제스처 만료 가능 — trace ${diag.traceId}`
    )
  }

  return { result: 'downloaded', diagnostics: diag, gestureBlocked }
}

export function sharePdfResultMessage(
  result: EduSharePdfResult,
  gestureBlocked = false
): string | null {
  if (result === 'shared' || result === 'cancelled') return null
  if (gestureBlocked) {
    return '공유 시트를 열지 못했습니다(제스처 만료 의심). PDF는 저장됐어요 — 카카오톡에서 파일로 첨부해 주세요.'
  }
  if (eduSharePdfIsMobileBrowser()) {
    return '공유가 되지 않아 PDF를 저장했어요. 카카오톡 → 채팅 → + → 파일에서 PDF를 선택해 보내세요.'
  }
  return 'PDF 다운로드가 시작됐어요. 저장된 파일을 카카오톡 등으로 보내 주세요.'
}

export function downloadPdfFile(blob: Blob, filename: string): void {
  if (!eduSharePdfLooksValid(blob)) {
    throw new Error('PDF 파일이 비어 있거나 손상됐습니다.')
  }
  const pdfBlob =
    blob.type === 'application/pdf' ? blob : new Blob([blob], { type: 'application/pdf' })
  triggerPdfDownload(pdfBlob, filename)
}
