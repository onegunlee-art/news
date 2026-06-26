import type { ReactNode } from 'react'
import { eduGame } from '../../constants/eduGameTheme'
import { parseCoachHighlightSegments, stripIncompleteCoachMarkers } from '../../utils/eduCoachMessageParse'

type Props = {
  text: string
  className?: string
  style?: React.CSSProperties
  /** 타입라이터 중 미완성 ** · " 마커 숨김 */
  hideIncompleteBold?: boolean
  hideIncompleteMarkers?: boolean
}

/** 코치 **강조** · "핵심어" → 오렌지 하이라이트 (마커 미노출) */
export default function CoachMessageText({
  text,
  className,
  style,
  hideIncompleteBold = false,
  hideIncompleteMarkers = false,
}: Props) {
  const stripIncomplete = hideIncompleteBold || hideIncompleteMarkers
  const source = stripIncomplete ? stripIncompleteCoachMarkers(text) : text
  const segments = parseCoachHighlightSegments(source)

  const nodes: ReactNode[] = []
  for (let i = 0; i < segments.length; i++) {
    const seg = segments[i]
    if (seg.type === 'highlight') {
      nodes.push(
        <mark
          key={`h-${i}`}
          className="rounded px-0.5 font-bold not-italic"
          style={{
            backgroundColor: eduGame.primaryLight,
            color: eduGame.primaryDark,
          }}
        >
          {seg.value}
        </mark>,
      )
    } else if (seg.value) {
      nodes.push(<span key={`p-${i}`}>{seg.value}</span>)
    }
  }

  return (
    <span className={className} style={style}>
      {nodes}
    </span>
  )
}
