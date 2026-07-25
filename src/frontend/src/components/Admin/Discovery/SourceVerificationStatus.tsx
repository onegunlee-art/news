import type { DiscoverySource } from './types'

interface Props {
  sources: DiscoverySource[]
}

export default function SourceVerificationStatus({ sources }: Props) {
  if (!sources.length) {
    return <p className="discovery-source-fail">출처 없음</p>
  }
  return (
    <ul style={{ margin: 0, paddingLeft: 16, fontSize: 12 }}>
      {sources.map((s, i) => (
        <li key={s.id ?? i} style={{ marginBottom: 4 }}>
          <a href={s.url} target="_blank" rel="noreferrer">{s.name || s.url}</a>
          {' '}
          {s.verified ? (
            <span className="discovery-source-ok">✓ 검증됨</span>
          ) : (
            <span className="discovery-source-fail">✗ {s.fail_reason || '미검증'}</span>
          )}
        </li>
      ))}
    </ul>
  )
}
