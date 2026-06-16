import type { EssaySection } from './EssayRevealCard'

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
