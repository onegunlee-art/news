import { useState } from 'react'
import MaterialIcon from '../../../Common/MaterialIcon'
import GistLogo from '../../../Common/GistLogo'
import SourceChipRow from './components/SourceChipRow'
import { DUMMY_RECENT_SEARCHES } from './dummyPreviewData'
import type { DiscoveryChange, SearchView } from '../types'

interface Props {
  onSearch: (q: string) => Promise<DiscoveryChange[]>
}

export default function SearchTab({ onSearch }: Props) {
  const [view, setView] = useState<SearchView>('home')
  const [q, setQ] = useState('')
  const [results, setResults] = useState<DiscoveryChange[]>([])
  const [loading, setLoading] = useState(false)
  const [recent, setRecent] = useState(DUMMY_RECENT_SEARCHES)
  const [period, setPeriod] = useState('30')
  const [sources, setSources] = useState<string[]>(['Reuters', 'BBC'])
  const [topics, setTopics] = useState<string[]>(['geopolitics'])

  const runSearch = async (query: string) => {
    const term = query.trim()
    if (!term) return
    setQ(term)
    setLoading(true)
    setView('results')
    if (!recent.includes(term)) {
      setRecent((r) => [term, ...r].slice(0, 6))
    }
    try {
      setResults(await onSearch(term))
    } finally {
      setLoading(false)
    }
  }

  if (view === 'filter') {
    return (
      <div className="discovery-filter-sheet" onClick={() => setView('results')}>
        <div className="discovery-filter-panel" onClick={(e) => e.stopPropagation()}>
          <h2 style={{ margin: '0 0 16px', fontSize: 18, fontWeight: 800 }}>필터</h2>
          <div style={{ marginBottom: 16 }}>
            <div style={{ fontWeight: 700, marginBottom: 8 }}>기간</div>
            {['7', '15', '30'].map((p) => (
              <label key={p} style={{ display: 'block', marginBottom: 6 }}>
                <input type="radio" name="period" checked={period === p} onChange={() => setPeriod(p)} /> {p}일
              </label>
            ))}
          </div>
          <div style={{ marginBottom: 16 }}>
            <div style={{ fontWeight: 700, marginBottom: 8 }}>출처</div>
            {['Reuters', 'BBC', 'EU 공식'].map((s) => (
              <label key={s} style={{ display: 'block', marginBottom: 6 }}>
                <input
                  type="checkbox"
                  checked={sources.includes(s)}
                  onChange={() => setSources((prev) => prev.includes(s) ? prev.filter((x) => x !== s) : [...prev, s])}
                /> {s}
              </label>
            ))}
          </div>
          <div style={{ marginBottom: 16 }}>
            <div style={{ fontWeight: 700, marginBottom: 8 }}>주제</div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 6 }}>
              {['geopolitics', 'business', 'tech', 'climate'].map((t) => (
                <label key={t}>
                  <input
                    type="checkbox"
                    checked={topics.includes(t)}
                    onChange={() => setTopics((prev) => prev.includes(t) ? prev.filter((x) => x !== t) : [...prev, t])}
                  /> {t}
                </label>
              ))}
            </div>
          </div>
          <div className="discovery-filter-actions">
            <button type="button" className="discovery-btn discovery-btn-secondary" style={{ flex: 1 }} onClick={() => { setPeriod('30'); setSources([]); setTopics([]) }}>초기화</button>
            <button type="button" className="discovery-btn" style={{ flex: 1 }} onClick={() => setView('results')}>적용하기</button>
          </div>
        </div>
      </div>
    )
  }

  if (view === 'results') {
    return (
      <div>
        <div className="discovery-search-bar">
          <MaterialIcon name="search" size={20} />
          <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && runSearch(q)} />
          <button type="button" className="discovery-icon-btn" style={{ width: 32, height: 32 }} onClick={() => setView('filter')}>
            <MaterialIcon name="tune" size={18} />
          </button>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12, fontSize: 13 }}>
          <span>검색 결과 {loading ? '…' : results.length}건</span>
          <span style={{ color: '#888' }}>최신순</span>
        </div>
        {results.map((r) => (
          <div key={r.id} className="discovery-result-item">
            <div className="discovery-result-date">{(r as DiscoveryChange & { edition_date?: string }).edition_date ?? '—'}</div>
            <div style={{ fontWeight: 700, fontSize: 15, marginBottom: 6 }}>{r.title}</div>
            <p className="discovery-change-summary">{r.summary}</p>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <SourceChipRow sources={r.sources} maxVisible={2} />
              <MaterialIcon name="bookmark_border" size={20} />
            </div>
          </div>
        ))}
        {!loading && results.length === 0 && <p style={{ color: '#888', fontSize: 13 }}>검색 결과가 없습니다.</p>}
        <button type="button" className="discovery-back-btn" onClick={() => setView('home')}>← 검색 홈</button>
      </div>
    )
  }

  return (
    <div>
      <div style={{ textAlign: 'center', marginBottom: 8 }}>
        <GistLogo link={false} size="header" style={{ fontSize: '1.5rem' }} />
      </div>
      <p style={{ textAlign: 'center', fontWeight: 700, fontSize: 16, margin: '12px 0 4px' }}>변화를 검색하세요</p>
      <p style={{ textAlign: 'center', fontSize: 12, color: '#888', margin: '0 0 16px' }}>최근 30일 아카이브에서 키워드를 찾아볼 수 있어요</p>
      <div className="discovery-search-bar">
        <MaterialIcon name="search" size={20} />
        <input
          value={q}
          placeholder="키워드 입력"
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && runSearch(q)}
        />
      </div>
      <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 8 }}>최근 검색</div>
      {recent.map((term) => (
        <div key={term} className="discovery-recent-item">
          <button type="button" style={{ background: 'none', border: 'none', display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 14 }} onClick={() => runSearch(term)}>
            <MaterialIcon name="schedule" size={18} />
            {term}
          </button>
          <button type="button" style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#888' }} onClick={() => setRecent((r) => r.filter((x) => x !== term))}>×</button>
        </div>
      ))}
      <div className="discovery-archive-card">📅 최근 30일 아카이브</div>
    </div>
  )
}
