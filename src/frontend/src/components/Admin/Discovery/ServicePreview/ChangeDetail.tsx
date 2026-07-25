import MaterialIcon from '../../../Common/MaterialIcon'
import SourceChipRow from './components/SourceChipRow'
import type { DiscoveryChange } from '../types'

interface Props {
  change: DiscoveryChange
  onBack: () => void
}

const BRIEFING_KEYS: { key: keyof DiscoveryChange['briefing']; label: string }[] = [
  { key: 'what_changed', label: '무슨 변화' },
  { key: 'why_changed', label: '왜 바뀜' },
  { key: 'why_important', label: '왜 중요' },
  { key: 'future_impact', label: '앞으로 영향' },
]

export default function ChangeDetail({ change, onBack }: Props) {
  const highlights = change.briefing.highlights?.length
    ? change.briefing.highlights
    : [
        change.briefing.what_changed,
        change.briefing.why_important,
      ].filter(Boolean).slice(0, 4)

  return (
    <div className="discovery-change-detail">
      <button type="button" className="discovery-back-btn" onClick={onBack}>← 홈</button>
      <h1 className="discovery-detail-title">{change.title}</h1>
      <SourceChipRow sources={change.sources} />

      <div className="discovery-briefing-label">✦ AI 브리핑</div>
      {BRIEFING_KEYS.map(({ key, label }) => (
        <div key={key}>
          <div className="discovery-briefing-label" style={{ fontSize: 12, marginTop: 8 }}>{label}</div>
          <p className="discovery-briefing-body">{change.briefing[key]}</p>
        </div>
      ))}

      {highlights.length > 0 && (
        <ul className="discovery-briefing-bullets">
          {highlights.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      )}

      <section className="discovery-sources-section">
        <div className="discovery-sources-title">Sources</div>
        {change.sources.map((s) => (
          <div key={s.id ?? s.url} className="discovery-source-item">
            <div className="discovery-source-item-name">{s.name}</div>
            {s.article_title && (
              <div className="discovery-source-item-title">{s.article_title}</div>
            )}
            <a href={s.url} target="_blank" rel="noreferrer" className="discovery-source-item-link">
              원문 보기 <MaterialIcon name="open_in_new" size={14} />
            </a>
          </div>
        ))}
      </section>
    </div>
  )
}
