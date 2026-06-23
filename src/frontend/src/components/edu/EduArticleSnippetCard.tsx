import { eduGame } from '../../constants/eduGameTheme'

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

/** Mid-quest article excerpt — eduGame accent (코치 말풍선 안). */
export default function EduArticleSnippetCard({ text, display = 'summary' }: Props) {
  const tierLabel = DISPLAY_LABEL[display] ?? display

  return (
    <div
      className="my-2 rounded-xl overflow-hidden bg-white text-left border-2 shadow-sm"
      style={{ borderColor: eduGame.primaryLight }}
    >
      <div
        className="px-3 py-2 flex items-center gap-2 border-b-2"
        style={{ borderColor: eduGame.primaryLight, backgroundColor: eduGame.primaryLight }}
      >
        <span className="text-[11px] font-bold shrink-0" style={{ color: eduGame.primaryDark }}>
          📰 기사에서
        </span>
        <span
          className="text-[10px] shrink-0 rounded-full px-2 py-0.5 font-medium"
          style={{ color: eduGame.muted, backgroundColor: eduGame.bg }}
        >
          조각 · {tierLabel}
        </span>
      </div>
      <p className="px-3 py-2.5 text-xs leading-relaxed border-l-4" style={{ color: eduGame.ink, borderColor: eduGame.primary }}>
        {text}
      </p>
    </div>
  )
}
