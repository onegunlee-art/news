export interface EssayStructureSection {
  heading: string
  role?: string
  bullets?: string[]
}

export interface EssayStructurePreview {
  title?: string
  subtitle?: string
  sections?: EssayStructureSection[]
  conclusion_heading?: string
  conclusion_bullets?: string[]
  student_stance?: string
}

const ROLE_LABEL: Record<string, string> = {
  background: '배경',
  tension: '갈등',
  stance: '입장',
  counter: '반론',
}

export default function StructurePreviewCard({ structure }: { structure: EssayStructurePreview }) {
  if (!structure.sections?.length) {
    return null
  }

  return (
    <section className="border border-[#ccc] rounded-lg p-4 bg-[#fafafa] space-y-3">
      <p className="text-xs font-bold text-[#666]">글 구조도 미리보기</p>
      {structure.title && <h2 className="text-sm font-bold">{structure.title}</h2>}
      {structure.subtitle && <p className="text-xs text-[#666]">{structure.subtitle}</p>}
      {structure.student_stance && (
        <span className="inline-block text-[10px] border border-[#1a1a1a] px-2 py-0.5">
          {structure.student_stance}
        </span>
      )}
      <div className="space-y-2">
        {structure.sections.map((sec, i) => (
          <div key={`${sec.heading}-${i}`} className="border border-[#ddd] rounded p-2 bg-white">
            <div className="flex items-center gap-2 mb-1">
              <p className="text-xs font-bold">{sec.heading}</p>
              {sec.role && (
                <span className="text-[10px] text-[#666]">{ROLE_LABEL[sec.role] ?? sec.role}</span>
              )}
            </div>
            <ul className="list-disc list-inside text-xs text-[#444] space-y-0.5">
              {(sec.bullets ?? []).map((b, j) => (
                <li key={j}>{b}</li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      {(structure.conclusion_bullets?.length ?? 0) > 0 && (
        <div className="border border-[#ddd] rounded p-2 bg-white">
          <p className="text-xs font-bold mb-1">{structure.conclusion_heading ?? '결론'}</p>
          <ul className="list-disc list-inside text-xs text-[#444] space-y-0.5">
            {structure.conclusion_bullets!.map((b, j) => (
              <li key={j}>{b}</li>
            ))}
          </ul>
        </div>
      )}
    </section>
  )
}
