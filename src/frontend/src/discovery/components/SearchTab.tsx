import { useState } from 'react'
import DiscoveryIcon from './ui/DiscoveryIcon'
import SourceChipRow from './ui/SourceChipRow'
import { DUMMY_RECENT_SEARCHES } from '../fixtures/dummyFixtures'
import type { DiscoveryChange, SearchView } from '../types'

const PERIOD_LABELS: Record<string, string> = {
  '7': '7일',
  '15': '15일',
  '30': '30일',
}

const TOPIC_LABELS: Record<string, string> = {
  geopolitics: '지정학',
  business: '경제',
  tech: '기술',
  climate: '기후',
}

interface Props {
  onSearch: (q: string) => Promise<DiscoveryChange[]>
  onViewChange?: (view: SearchView) => void
}

function formatResultDate(date?: string | null): string {
  if (!date) return '—'
  const [y, m, d] = date.split('-').map(Number)
  if (!y || !m || !d) return date
  return `${y}년 ${m}월 ${d}일`
}

export default function SearchTab({ onSearch, onViewChange }: Props) {
  const [view, setView] = useState<SearchView>('home')
  const [filterOpen, setFilterOpen] = useState(false)
  const [q, setQ] = useState('')
  const [results, setResults] = useState<DiscoveryChange[]>([])
  const [loading, setLoading] = useState(false)
  const [recent, setRecent] = useState(DUMMY_RECENT_SEARCHES)
  const [period, setPeriod] = useState('30')
  const [sources, setSources] = useState<string[]>(['Reuters', 'BBC'])
  const [topics, setTopics] = useState<string[]>(['geopolitics'])

  const setSearchView = (next: SearchView) => {
    setView(next)
    if (next !== 'results') setFilterOpen(false)
    onViewChange?.(next)
  }

  const runSearch = async (query: string) => {
    const term = query.trim()
    if (!term) return
    setQ(term)
    setLoading(true)
    setSearchView('results')
    if (!recent.includes(term)) {
      setRecent((r) => [term, ...r].slice(0, 6))
    }
    try {
      setResults(await onSearch(term))
    } finally {
      setLoading(false)
    }
  }

  const sourceSummary = sources.length === 0 ? '전체' : sources.join(' · ')
  const topicSummary = topics.length === 0
    ? '전체'
    : topics.map((t) => TOPIC_LABELS[t] ?? t).join(' · ')

  const filterSheet = filterOpen ? (
    <div className="discovery-filter-sheet" onClick={() => setFilterOpen(false)}>
      <div className="discovery-filter-panel" onClick={(e) => e.stopPropagation()}>
        <div className="discovery-filter-header">
          <h2>필터</h2>
          <button type="button" className="discovery-filter-close" aria-label="닫기" onClick={() => setFilterOpen(false)}>
            <DiscoveryIcon name="x" size={22} />
          </button>
        </div>

        <div className="discovery-filter-section">
          <div className="discovery-filter-section-title">기간</div>
          {['7', '15', '30'].map((p) => (
            <label key={p} className="discovery-filter-option">
              <input type="radio" name="period" checked={period === p} onChange={() => setPeriod(p)} />
              최근 {p}일
            </label>
          ))}
        </div>

        <div className="discovery-filter-section">
          <div className="discovery-filter-section-title">출처</div>
          <label className="discovery-filter-option">
            <input type="checkbox" checked={sources.length === 0} onChange={() => setSources([])} />
            전체
          </label>
          {['Reuters', 'BBC', 'EU 공식'].map((s) => (
            <label key={s} className="discovery-filter-option">
              <input
                type="checkbox"
                checked={sources.includes(s)}
                onChange={() => setSources((prev) => (
                  prev.includes(s) ? prev.filter((x) => x !== s) : [...prev, s]
                ))}
              />
              {s}
            </label>
          ))}
        </div>

        <div className="discovery-filter-section">
          <div className="discovery-filter-section-title">주제</div>
          <div className="discovery-filter-topic-grid">
            {Object.entries(TOPIC_LABELS).map(([key, label]) => (
              <label key={key} className="discovery-filter-option">
                <input
                  type="checkbox"
                  checked={topics.includes(key)}
                  onChange={() => setTopics((prev) => (
                    prev.includes(key) ? prev.filter((x) => x !== key) : [...prev, key]
                  ))}
                />
                {label}
              </label>
            ))}
          </div>
        </div>

        <div className="discovery-filter-actions">
          <button type="button" className="discovery-btn-filter-apply" onClick={() => setFilterOpen(false)}>
            적용하기
          </button>
          <button
            type="button"
            className="discovery-filter-reset"
            onClick={() => { setPeriod('30'); setSources([]); setTopics([]) }}
          >
            초기화
          </button>
        </div>
      </div>
    </div>
  ) : null

  if (view === 'results') {
    return (
      <div className="discovery-search-results">
        <div className="discovery-search-results-top">
          <button type="button" className="discovery-search-back" aria-label="뒤로" onClick={() => setSearchView('home')}>
            <DiscoveryIcon name="arrow-back" size={22} />
          </button>
          <div className="discovery-search-term-pill">
            <DiscoveryIcon name="search" size={18} />
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && runSearch(q)}
            />
            {q && (
              <button type="button" className="discovery-recent-remove" aria-label="검색어 지우기" onClick={() => setQ('')}>
                <DiscoveryIcon name="x" size={16} />
              </button>
            )}
          </div>
          <button type="button" className="discovery-search-filter-btn" aria-label="필터" onClick={() => setFilterOpen(true)}>
            <DiscoveryIcon name="tune" size={22} />
          </button>
        </div>

        <div className="discovery-search-chips">
          <button type="button" className="discovery-search-chip active" onClick={() => setFilterOpen(true)}>
            {PERIOD_LABELS[period] ?? period}
            <DiscoveryIcon name="chevron-right" size={14} />
          </button>
          <button type="button" className="discovery-search-chip" onClick={() => setFilterOpen(true)}>
            출처 · {sourceSummary}
          </button>
          <button type="button" className="discovery-search-chip" onClick={() => setFilterOpen(true)}>
            주제 · {topicSummary}
          </button>
        </div>

        <div className="discovery-search-meta">
          <span>검색 결과 <strong>{loading ? '…' : results.length}</strong>건</span>
          <span>최신순</span>
        </div>

        {results.map((r) => (
          <div key={r.id} className="discovery-result-item">
            <button type="button" className="discovery-result-bookmark" aria-label="북마크">
              <DiscoveryIcon name="bookmark" size={20} />
            </button>
            <div className="discovery-result-date">{formatResultDate(r.edition_date)}</div>
            <h3 className="discovery-result-title">{r.title}</h3>
            <p className="discovery-change-summary">{r.summary}</p>
            {r.sources?.length ? (
              <div className="discovery-result-sources">
                <SourceChipRow sources={r.sources} maxVisible={3} />
              </div>
            ) : null}
          </div>
        ))}
        {!loading && results.length === 0 && (
          <p className="discovery-search-empty">검색 결과가 없습니다.</p>
        )}
        {filterSheet}
      </div>
    )
  }

  return (
    <div className="discovery-search-home">
      <h1 className="discovery-search-title">변화를 검색하세요</h1>
      <p className="discovery-search-subtitle">
        <DiscoveryIcon name="info" size={14} />
        최근 30일간의 변화만 검색할 수 있습니다.
      </p>

      <div className="discovery-search-bar">
        <DiscoveryIcon name="search" size={20} />
        <input
          value={q}
          placeholder="변화, 국가, 주제, 출처를 검색하세요"
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && runSearch(q)}
        />
      </div>

      {recent.length > 0 && (
        <>
          <div className="discovery-search-section-label">최근 검색</div>
          {recent.map((term) => (
            <div key={term} className="discovery-recent-item">
              <button type="button" className="discovery-recent-term" onClick={() => runSearch(term)}>
                <DiscoveryIcon name="clock" size={18} />
                {term}
              </button>
              <button
                type="button"
                className="discovery-recent-remove"
                aria-label={`${term} 삭제`}
                onClick={() => setRecent((r) => r.filter((x) => x !== term))}
              >
                <DiscoveryIcon name="x" size={16} />
              </button>
            </div>
          ))}
        </>
      )}

      <div className="discovery-archive-card">
        <div className="discovery-archive-card-head">
          <DiscoveryIcon name="calendar" size={22} />
          <h2 className="discovery-archive-card-title">최근 30일 아카이브</h2>
        </div>
        <p className="discovery-archive-card-desc">지난 30일간의 모든 변화를 날짜별로 확인할 수 있습니다.</p>
        <p className="discovery-archive-card-desc">아카이브에서 원하는 날짜의 변화를 찾아보세요.</p>
        <button type="button" className="discovery-archive-card-link">
          자세히 보기
          <DiscoveryIcon name="chevron-right" size={16} stroke="var(--color-primary)" />
        </button>
      </div>
    </div>
  )
}
