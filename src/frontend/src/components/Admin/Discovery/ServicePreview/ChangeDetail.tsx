import type { DiscoveryChange } from '../types'

interface Props {
  change: DiscoveryChange
  onBack: () => void
}

const steps: { key: keyof DiscoveryChange['briefing']; label: string }[] = [
  { key: 'what_changed', label: '① 무슨 변화' },
  { key: 'why_changed', label: '② 왜 바뀜' },
  { key: 'why_important', label: '③ 왜 중요' },
  { key: 'future_impact', label: '④ 앞으로 영향' },
]

export default function ChangeDetail({ change, onBack }: Props) {
  return (
    <div>
      <button type="button" className="discovery-btn discovery-btn-secondary" onClick={onBack} style={{ marginBottom: 12 }}>
        ← 홈
      </button>
      <h3 style={{ marginTop: 0 }}>{change.title}</h3>
      {steps.map(({ key, label }) => (
        <div key={key} className="discovery-briefing-step">
          <strong>{label}</strong>
          <p style={{ margin: 0, fontSize: 14, lineHeight: 1.55 }}>{change.briefing[key]}</p>
        </div>
      ))}
      <div style={{ marginTop: 16 }}>
        <strong style={{ fontSize: 13 }}>출처</strong>
        <ul style={{ paddingLeft: 18, fontSize: 13 }}>
          {change.sources.map((s) => (
            <li key={s.id ?? s.url}>
              <a href={s.url} target="_blank" rel="noreferrer">{s.name}</a>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
