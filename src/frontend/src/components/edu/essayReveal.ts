const REVEAL_COUNT_KEY = 'edu_essay_reveal_count'

export type RevealMode = 'full' | 'minimal' | 'off'

export function getEssayRevealMode(): RevealMode {
  const count = parseInt(localStorage.getItem(REVEAL_COUNT_KEY) ?? '0', 10)
  if (count >= 3) return 'off'
  if (count >= 1) return 'minimal'
  return 'full'
}

export function markEssayRevealSeen(): void {
  const count = parseInt(localStorage.getItem(REVEAL_COUNT_KEY) ?? '0', 10)
  localStorage.setItem(REVEAL_COUNT_KEY, String(count + 1))
}

export function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

export type RevealStep =
  | { kind: 'congrats' }
  | { kind: 'title'; text: string }
  | { kind: 'subtitle'; text: string }
  | { kind: 'section-heading'; text: string }
  | { kind: 'paragraph'; text: string }
  | { kind: 'conclusion-heading'; text: string }
  | { kind: 'hero'; text: string }
  | { kind: 'byline'; text: string }
  | { kind: 'full-text'; text: string }

export function buildRevealSteps(
  essay: {
    title?: string | null
    subtitle?: string | null
    sections?: { heading: string; paragraphs: string[] }[]
    body_paragraphs?: string[]
    narration_mode?: boolean
    conclusion_heading?: string
    conclusion_paragraphs?: string[]
    full_text?: string
    hero_sentence?: string | null
  },
  authorName?: string | null,
  mode: RevealMode = 'full'
): RevealStep[] {
  const steps: RevealStep[] = []

  if (mode === 'minimal') {
    if (essay.title) steps.push({ kind: 'title', text: essay.title })
    return steps
  }

  if (mode === 'off') return steps

  steps.push({ kind: 'congrats' })

  const narration =
    essay.narration_mode === true ||
    ((essay.body_paragraphs?.length ?? 0) >= 2 && (essay.sections?.length ?? 0) === 0)

  const hasStructure = !narration && (essay.sections?.length ?? 0) > 0

  if (narration) {
    if (essay.title) steps.push({ kind: 'title', text: essay.title })
    if (essay.subtitle) steps.push({ kind: 'subtitle', text: essay.subtitle })
    for (const p of essay.body_paragraphs ?? []) {
      if (p.trim()) steps.push({ kind: 'paragraph', text: p })
    }
    if (essay.hero_sentence) steps.push({ kind: 'hero', text: essay.hero_sentence })
    if (authorName) steps.push({ kind: 'byline', text: authorName })
    return capSteps(steps)
  }

  if (!hasStructure) {
    if (essay.full_text) steps.push({ kind: 'full-text', text: essay.full_text })
    if (essay.hero_sentence) steps.push({ kind: 'hero', text: essay.hero_sentence })
    if (authorName) steps.push({ kind: 'byline', text: authorName })
    return capSteps(steps)
  }

  if (essay.title) steps.push({ kind: 'title', text: essay.title })
  if (essay.subtitle) steps.push({ kind: 'subtitle', text: essay.subtitle })

  for (const sec of essay.sections ?? []) {
    if (sec.heading) steps.push({ kind: 'section-heading', text: sec.heading })
    for (const p of sec.paragraphs ?? []) {
      if (p.trim()) steps.push({ kind: 'paragraph', text: p })
    }
  }

  if ((essay.conclusion_paragraphs?.length ?? 0) > 0) {
    steps.push({
      kind: 'conclusion-heading',
      text: essay.conclusion_heading ?? '결론',
    })
    for (const p of essay.conclusion_paragraphs ?? []) {
      if (p.trim()) steps.push({ kind: 'paragraph', text: p })
    }
  }

  if (essay.hero_sentence) steps.push({ kind: 'hero', text: essay.hero_sentence })
  if (authorName) steps.push({ kind: 'byline', text: authorName })

  return capSteps(steps)
}

/** ~1초 안에 끝나도록 중간 단락은 묶음 */
function capSteps(steps: RevealStep[]): RevealStep[] {
  const MAX_BODY_STEPS = 8
  let bodyCount = 0
  const out: RevealStep[] = []

  for (const step of steps) {
    if (step.kind === 'paragraph' || step.kind === 'full-text') {
      bodyCount++
      if (bodyCount > MAX_BODY_STEPS) continue
    }
    out.push(step)
  }

  return out
}

export function revealTiming(stepCount: number, mode: RevealMode): { staggerSec: number; durationSec: number } {
  if (mode === 'minimal') {
    return { staggerSec: 0, durationSec: 0.28 }
  }
  const budgetMs = 950
  const durationSec = 0.22
  const staggerSec = stepCount <= 1 ? 0 : Math.min(0.09, (budgetMs / stepCount) / 1000)
  return { staggerSec, durationSec }
}
