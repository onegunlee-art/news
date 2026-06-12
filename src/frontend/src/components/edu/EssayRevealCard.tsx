export interface EssaySection {
  heading: string
  paragraphs: string[]
}

export interface EssayArtifact {
  title?: string | null
  subtitle?: string | null
  sections?: EssaySection[]
  conclusion_heading?: string
  conclusion_paragraphs?: string[]
  full_text?: string
  hero_sentence?: string | null
  feedback?: string | null
}

export default function EssayRevealCard({ essay }: { essay: EssayArtifact }) {
  const hasStructure =
    (essay.sections?.length ?? 0) > 0 ||
    Boolean(essay.title) ||
    Boolean(essay.subtitle)

  if (!hasStructure && essay.full_text) {
    return (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed whitespace-pre-wrap">{essay.full_text}</p>
      </div>
    )
  }

  return (
    <div className="space-y-5">
      {essay.title && (
        <h2 className="text-lg font-bold leading-snug">{essay.title}</h2>
      )}
      {essay.subtitle && (
        <p className="text-sm text-[#666] leading-relaxed">{essay.subtitle}</p>
      )}

      {(essay.sections ?? []).map((sec) => (
        <section key={sec.heading} className="space-y-2">
          {sec.heading && (
            <h3 className="text-sm font-bold border-l-2 border-[#1a1a1a] pl-2">{sec.heading}</h3>
          )}
          {(sec.paragraphs ?? []).map((p, i) => (
            <p key={`${sec.heading}-${i}`} className="text-sm leading-relaxed text-[#333]">
              {p}
            </p>
          ))}
        </section>
      ))}

      {(essay.conclusion_paragraphs?.length ?? 0) > 0 && (
        <section className="space-y-2 border-t border-[#eee] pt-4">
          <h3 className="text-sm font-bold">{essay.conclusion_heading ?? '결론'}</h3>
          {(essay.conclusion_paragraphs ?? []).map((p, i) => (
            <p key={`conclusion-${i}`} className="text-sm leading-relaxed text-[#333]">
              {p}
            </p>
          ))}
        </section>
      )}
    </div>
  )
}
