import { EDU_BRAND } from '../../constants/eduBrand'
import type { EssayArtifact, EssaySection } from './EssayRevealCard'

interface EssayEditorProps {
  essay: EssayArtifact
  onChange: (essay: EssayArtifact) => void
  disabled?: boolean
}

const fieldClass =
  'w-full border-0 border-b rounded-none px-0 py-2 text-base leading-relaxed bg-transparent focus:outline-none focus:border-[#f05123] transition-colors'
const labelClass = 'text-xs font-bold text-[#666] block mb-1'

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
      <div className="space-y-6">
        <div>
          <label className={labelClass}>본문</label>
          <textarea
            value={essay.full_text ?? ''}
            disabled={disabled}
            onChange={(e) => update({ full_text: e.target.value })}
            rows={12}
            className={`${fieldClass} resize-y`}
            style={{ borderColor: EDU_BRAND.border }}
          />
        </div>
        <div
          className="rounded-xl p-4"
          style={{ backgroundColor: EDU_BRAND.surface }}
        >
          <label className={labelClass}>핵심 문장 (공유카드)</label>
          <textarea
            value={essay.hero_sentence ?? ''}
            disabled={disabled}
            onChange={(e) => update({ hero_sentence: e.target.value })}
            rows={2}
            className={`${fieldClass} resize-y bg-transparent`}
            style={{ borderColor: EDU_BRAND.border }}
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
    <div className="space-y-8">
      <div>
        <label className={labelClass}>제목</label>
        <input
          type="text"
          value={essay.title ?? ''}
          disabled={disabled}
          onChange={(e) => update({ title: e.target.value })}
          className={`${fieldClass} font-bold text-lg`}
          style={{ borderColor: EDU_BRAND.border }}
        />
      </div>

      <div>
        <label className={labelClass}>부제</label>
        <textarea
          value={essay.subtitle ?? ''}
          disabled={disabled}
          onChange={(e) => update({ subtitle: e.target.value })}
          rows={2}
          className={`${fieldClass} text-[#666] resize-y`}
          style={{ borderColor: EDU_BRAND.border }}
        />
      </div>

      {(essay.sections ?? []).map((sec, i) => (
        <div
          key={`sec-${i}`}
          className="border-l-2 pl-4 space-y-3"
          style={{ borderColor: EDU_BRAND.accent }}
        >
          <label className={labelClass}>소제목 {i + 1}</label>
          <input
            type="text"
            value={sec.heading}
            disabled={disabled}
            onChange={(e) => updateSection(i, { heading: e.target.value })}
            className={`${fieldClass} font-bold`}
            style={{ borderColor: EDU_BRAND.border }}
          />
          <label className="text-xs text-[#666] block">본문</label>
          <textarea
            value={paragraphsToText(sec.paragraphs)}
            disabled={disabled}
            onChange={(e) => updateSectionBody(i, e.target.value)}
            rows={5}
            className={`${fieldClass} resize-y`}
            style={{ borderColor: EDU_BRAND.border }}
          />
        </div>
      ))}

      <div
        className="border-l-2 pl-4 space-y-3"
        style={{ borderColor: EDU_BRAND.accent }}
      >
        <label className={labelClass}>결론</label>
        <input
          type="text"
          value={essay.conclusion_heading ?? '결론'}
          disabled={disabled}
          onChange={(e) => update({ conclusion_heading: e.target.value })}
          className={`${fieldClass} font-bold`}
          style={{ borderColor: EDU_BRAND.border }}
        />
        <textarea
          value={paragraphsToText(essay.conclusion_paragraphs)}
          disabled={disabled}
          onChange={(e) =>
            update({ conclusion_paragraphs: textToParagraphs(e.target.value) })
          }
          rows={4}
          className={`${fieldClass} resize-y`}
          style={{ borderColor: EDU_BRAND.border }}
        />
      </div>

      <div
        className="rounded-xl p-4"
        style={{ backgroundColor: EDU_BRAND.surface }}
      >
        <label className={labelClass}>핵심 문장 (공유카드)</label>
        <textarea
          value={essay.hero_sentence ?? ''}
          disabled={disabled}
          onChange={(e) => update({ hero_sentence: e.target.value })}
          rows={2}
          className={`${fieldClass} resize-y bg-transparent`}
          style={{ borderColor: EDU_BRAND.border }}
        />
      </div>
    </div>
  )
}
