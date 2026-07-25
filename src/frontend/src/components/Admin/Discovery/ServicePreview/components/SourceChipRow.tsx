import type { DiscoverySource } from '../../types'

interface Props {
  sources: DiscoverySource[]
  maxVisible?: number
}

export default function SourceChipRow({ sources, maxVisible = 3 }: Props) {
  const visible = sources.slice(0, maxVisible)
  const extra = sources.length - visible.length

  if (!sources.length) {
    return <div className="discovery-source-chips"><span className="discovery-source-chip">출처 확인 중</span></div>
  }

  return (
    <div className="discovery-source-chips">
      {visible.map((s) => (
        <span key={s.id ?? s.url} className="discovery-source-chip">{s.name || 'Source'}</span>
      ))}
      {extra > 0 && <span className="discovery-source-chip">+{extra}</span>}
    </div>
  )
}
