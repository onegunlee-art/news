import type { ReactNode } from 'react'
import { eduGame } from '../../constants/eduGameTheme'
import { parseCoachBoldSegments } from '../../utils/eduCoachMessageParse'

type Props = {
  text: string
  className?: string
  style?: React.CSSProperties
  /** 타입라이터 중 미완성 ** 마커 숨김 */
  hideIncompleteBold?: boolean
}

/** 코치 **강조** → 오렌지 하이라이트 (별표 미노출) */
export function stripIncompleteCoachBold(text: string): string {
  const open = text.lastIndexOf('**')
  if (open === -1) return text
  const tail = text.slice(open + 2)
  if (!tail.includes('**')) {
    return text.slice(0, open)
  }
  return text
}

export default function CoachMessageText({
  text,
  className,
  style,
  hideIncompleteBold = false,
}: Props) {
  const source = hideIncompleteBold ? stripIncompleteCoachBold(text) : text
  const segments = parseCoachBoldSegments(source)

  const nodes: ReactNode[] = []
  for (let i = 0; i < segments.length; i++) {
    const seg = segments[i]
    if (seg.type === 'bold') {
      nodes.push(
        <mark
          key={`b-${i}`}
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
