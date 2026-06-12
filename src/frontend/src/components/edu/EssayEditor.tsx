import type { EssayArtifact, EssaySection } from './EssayRevealCard'

interface EssayEditorProps {
  essay: EssayArtifact
  onChange: (essay: EssayArtifact) => void
  disabled?: boolean
}

function paragraphsToText(paragraphs: string[] | undefined): string {
  return (paragraphs ?? []).join('\n\n')
}

function textToParagraphs(text: string): string[] {
  return text
    .split(/\n{2,}/)
    .map((p) => p.trim())
    .filter(Boolean)
}

export default function EssayEditor({ essay, onChange, disabled = false }: EssayEditorProps) {
  const hasStructure = (essay.sections?.length ?? 0) > 0

  const update = (patch: Partial<EssayArtifact>) => {
    onChange({ ...essay, ...patch })
  }

  if (!hasStructure) {
    return (
      <div>
        <label className="text-[10px] font-bold text-[#666] block mb-1">본문</label>
        <textarea
          value={essay.full_text ?? ''}
          disabled={disabled}
          onChange={(e) => update({ full_text: e.target.value })}
          rows={12}
          className="w-full border border-[#ccc] rounded px-3 py-2 text-sm leading-relaxed resize-y"
        />
        <div className="mt-3">
          <label className="text-[10px] font-bold text-[#666] block mb-1">핵심 문장 (공유카드)</label>
          <textarea
            value={essay.hero_sentence ?? ''}
            disabled={disabled}
            onChange={(e) => update({ hero_sentence: e.target.value })}
            rows={2}
            className="w-full border border-[#ccc] rounded px-3 py-2 text-sm resize-y"
          />
        </div>
      </div>
    )
  }

  const updateSection = (index: number, patch: Partial<EssaySection>) => {
    const sections = [...(essay.sections ?? [])]
    sections[index] = { ...sections[index], ...patch }
    update({ sections })
  }

  const updateSectionBody = (index: number, text: string) => {
    updateSection(index, { paragraphs: textToParagraphs(text) })
  }

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[10px] font-bold text-[#666] block mb-1">제목</label>
        <input
          type="text"
          value={essay.title ?? ''}
          disabled={disabled}
          onChange={(e) => update({ title: e.target.value })}
          className="w-full border border-[#ccc] rounded px-3 py-2 text-sm font-bold"
        />
      </div>

      <div>
        <label className="text-[10px] font-bold text-[#666] block mb-1">부제</label>
        <textarea
          value={essay.subtitle ?? ''}
          disabled={disabled}
          onChange={(e) => update({ subtitle: e.target.value })}
          rows={2}
          className="w-full border border-[#ccc] rounded px-3 py-2 text-sm resize-y"
        />
      </div>

      {(essay.sections ?? []).map((sec, i) => (
        <div key={`sec-${i}`} className="border border-[#ddd] rounded p-3 space-y-2 bg-white">
          <label className="text-[10px] font-bold text-[#666] block">소제목 {i + 1}</label>
          <input
            type="text"
            value={sec.heading}
            disabled={disabled}
            onChange={(e) => updateSection(i, { heading: e.target.value })}
            className="w-full border border-[#ccc] rounded px-2 py-1.5 text-sm font-bold"
          />
          <label className="text-[10px] text-[#666] block">본문</label>
          <textarea
            value={paragraphsToText(sec.paragraphs)}
            disabled={disabled}
            onChange={(e) => updateSectionBody(i, e.target.value)}
            rows={4}
            className="w-full border border-[#ccc] rounded px-2 py-1.5 text-sm leading-relaxed resize-y"
          />
        </div>
      ))}

      <div className="border border-[#ddd] rounded p-3 space-y-2 bg-white">
        <label className="text-[10px] font-bold text-[#666] block">결론 제목</label>
        <input
          type="text"
          value={essay.conclusion_heading ?? '결론'}
          disabled={disabled}
          onChange={(e) => update({ conclusion_heading: e.target.value })}
          className="w-full border border-[#ccc] rounded px-2 py-1.5 text-sm font-bold"
        />
        <label className="text-[10px] text-[#666] block">결론 본문</label>
        <textarea
          value={paragraphsToText(essay.conclusion_paragraphs)}
          disabled={disabled}
          onChange={(e) =>
            update({ conclusion_paragraphs: textToParagraphs(e.target.value) })
          }
          rows={3}
          className="w-full border border-[#ccc] rounded px-2 py-1.5 text-sm leading-relaxed resize-y"
        />
      </div>

      <div>
        <label className="text-[10px] font-bold text-[#666] block mb-1">핵심 문장 (공유카드)</label>
        <textarea
          value={essay.hero_sentence ?? ''}
          disabled={disabled}
          onChange={(e) => update({ hero_sentence: e.target.value })}
          rows={2}
          className="w-full border border-[#ccc] rounded px-3 py-2 text-sm resize-y"
        />
      </div>
    </div>
  )
}
