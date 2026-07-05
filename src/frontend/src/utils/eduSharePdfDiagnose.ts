export type EduShareDiagStep = {
  n: number
  label: string
  detail?: string
  ms: number
}

export type EduShareDiag = {
  traceId: string
  clickMs: number
  steps: EduShareDiagStep[]
  lastError?: { step: string; name: string; message: string }
  branch?: string
  result?: string
}

let lastDiag: EduShareDiag | null = null

export function eduShareDiagGetLast(): EduShareDiag | null {
  return lastDiag
}

export function eduShareDiagStart(): EduShareDiag {
  const d: EduShareDiag = {
    traceId: Math.random().toString(36).slice(2, 8),
    clickMs: performance.now(),
    steps: [],
  }
  lastDiag = d
  return d
}

export function eduShareDiagStep(
  diag: EduShareDiag,
  n: number,
  label: string,
  detail?: string
): void {
  const ms = Math.round(performance.now() - diag.clickMs)
  const step = { n, label, detail, ms }
  diag.steps.push(step)
  const msg = detail
    ? `[edu-share ${diag.traceId}] ${n}. ${label} — ${detail} (+${ms}ms)`
    : `[edu-share ${diag.traceId}] ${n}. ${label} (+${ms}ms)`
  console.log(msg)
}

export function eduShareDiagError(diag: EduShareDiag, step: string, err: unknown): void {
  const e = err instanceof Error ? err : new Error(String(err))
  diag.lastError = { step, name: e.name, message: e.message }
  console.error(`[edu-share ${diag.traceId}] ERR @ ${step}`, e.name, e.message, e)
}

export function eduShareDiagFinish(diag: EduShareDiag, branch: string, result: string): void {
  diag.branch = branch
  diag.result = result
  eduShareDiagStep(diag, 9, 'done', `${branch} → ${result}`)
}

export function eduShareDiagSummary(diag: EduShareDiag): string {
  const lines = diag.steps.map(
    (s) => `${s.n}. ${s.label}${s.detail ? `: ${s.detail}` : ''} (+${s.ms}ms)`
  )
  if (diag.lastError) {
    lines.push(`ERR@${diag.lastError.step}: ${diag.lastError.name} — ${diag.lastError.message}`)
  }
  if (diag.branch) lines.push(`branch=${diag.branch} result=${diag.result}`)
  lines.push(`trace=${diag.traceId}`)
  return lines.join('\n')
}

export function eduShareDiagEnabled(): boolean {
  try {
    if (typeof window === 'undefined') return false
    if (new URLSearchParams(window.location.search).get('share_debug') === '1') return true
    return localStorage.getItem('edu_share_debug') === '1'
  } catch {
    return false
  }
}
