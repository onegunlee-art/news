import type { EssayArtifact, EssaySection } from './EssayRevealCard'

export function isEssayNarrationMode(essay: EssayArtifact): boolean {
  if (essay.narration_mode) return true
  return (essay.body_paragraphs?.length ?? 0) >= 2 && (essay.sections?.length ?? 0) === 0
}

export function rebuildNarrationFullText(essay: EssayArtifact): string {
  const blocks: string[] = []
  if (essay.title?.trim()) blocks.push(essay.title.trim())
  if (essay.subtitle?.trim()) blocks.push(essay.subtitle.trim())
  for (const p of essay.body_paragraphs ?? []) {
    const t = p.trim()
    if (t) blocks.push(t)
  }
  return blocks.join('\n\n')
}

export function paragraphsToText(paragraphs: string[] | undefined): string {
  return (paragraphs ?? []).join('\n\n')
}

export function textToParagraphs(text: string): string[] {
  return text
    .split(/\n{2,}/)
    .map((p) => p.trim())
    .filter(Boolean)
}

export function updateSectionAt(
  sections: EssaySection[],
  index: number,
  patch: Partial<EssaySection>
): EssaySection[] {
  const next = [...sections]
  next[index] = { ...next[index], ...patch }
  return next
}
