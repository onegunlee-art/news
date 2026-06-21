import { EDU_BRAND } from '../../constants/eduBrand'

type Props = {
  text: string
  /** P2-B: summary | medium | full — display tier from coach */
  display?: string
}

const DISPLAY_LABEL: Record<string, string> = {
  summary: '요약',
  medium: '발췌',
  full: '원문',
}

/** Mid-quest article excerpt — same family as EduArticleCard (full card at quest end). */
export default function EduArticleSnippetCard({ text, display = 'summary' }: Props) {
  const tierLabel = DISPLAY_LABEL[display] ?? display

  return (
    <div
      className="my-2 border rounded overflow-hidden bg-white text-left"
      style={{ borderColor: EDU_BRAND.border }}
    >
      <div
        className="px-2 py-1.5 flex items-center gap-2 border-b"
        style={{ borderColor: EDU_BRAND.border, backgroundColor: EDU_BRAND.accentBg }}
      >
        <span className="text-[10px] font-bold shrink-0" style={{ color: EDU_BRAND.accent }}>
          📰 기사에서
        </span>
        <span className="text-[10px] shrink-0 border px-1 py-0.5" style={{ color: EDU_BRAND.muted }}>
          조각 · {tierLabel}
        </span>
      </div>
      <p className="px-2 py-2 text-xs leading-relaxed" style={{ color: EDU_BRAND.ink }}>
        {text}
      </p>
    </div>
  )
}
