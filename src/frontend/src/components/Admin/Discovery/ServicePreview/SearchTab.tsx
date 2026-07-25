import { useState } from 'react'
import type { DiscoveryChange } from '../types'

interface Props {
  onSearch: (q: string) => Promise<DiscoveryChange[]>
}

export default function SearchTab({ onSearch }: Props) {
  const [q, setQ] = useState('')
  const [results, setResults] = useState<DiscoveryChange[]>([])
  const [loading, setLoading] = useState(false)

  const run = async () => {
    if (!q.trim()) return
    setLoading(true)
    try {
      setResults(await onSearch(q.trim()))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <h3 style={{ marginTop: 0, fontSize: 16 }}>검색 (30일)</h3>
      <div style={{ display: 'flex', gap: 6 }}>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="키워드"
          style={{ flex: 1, padding: 8, borderRadius: 8, border: '1px solid #ddd' }}
        />
        <button type="button" className="discovery-btn" onClick={run} disabled={loading}>검색</button>
      </div>
      <div style={{ marginTop: 12 }}>
        {results.map((r) => (
          <div key={r.id} style={{ padding: '8px 0', borderBottom: '1px solid #eee' }}>
            <div style={{ fontSize: 11, color: '#888' }}>{(r as DiscoveryChange & { edition_date?: string }).edition_date}</div>
            <div style={{ fontWeight: 600, fontSize: 14 }}>{r.title}</div>
          </div>
        ))}
        {!loading && results.length === 0 && <p style={{ fontSize: 12, color: '#888' }}>검색 결과 없음</p>}
      </div>
    </div>
  )
}
