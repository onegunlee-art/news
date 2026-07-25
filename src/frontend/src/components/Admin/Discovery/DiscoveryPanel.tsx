import { useCallback, useEffect, useRef, useState } from 'react'
import EditionList from './EditionList'
import EditionEditor from './EditionEditor'
import PreviewFrame from './ServicePreview/PreviewFrame'
import DiscoveryHome from './ServicePreview/DiscoveryHome'
import ChangeDetail from './ServicePreview/ChangeDetail'
import PollDetail from './ServicePreview/PollDetail'
import CommunityTab from './ServicePreview/CommunityTab'
import SearchTab from './ServicePreview/SearchTab'
import MyPageTab from './ServicePreview/MyPageTab'
import { useDiscoveryApi } from './hooks/useDiscoveryApi'
import { useSwipeNavigation } from './hooks/useSwipeNavigation'
import type { DiscoveryChange, DiscoveryEdition, PreviewScreen, PreviewTab } from './types'
import './discovery.css'

type ViewMode = 'ops' | 'preview'

export default function DiscoveryPanel() {
  const api = useDiscoveryApi()
  const [viewMode, setViewMode] = useState<ViewMode>('ops')
  const [editions, setEditions] = useState<DiscoveryEdition[]>([])
  const [changes, setChanges] = useState<DiscoveryChange[]>([])
  const [selectedEdition, setSelectedEdition] = useState<DiscoveryEdition | null>(null)
  const [previewTab, setPreviewTab] = useState<PreviewTab>('home')
  const [screen, setScreen] = useState<PreviewScreen>('home')
  const touchStart = useRef<{ x: number; y: number } | null>(null)

  const today = new Date().toISOString().slice(0, 10)
  const nav = useSwipeNavigation(editions, selectedEdition?.edition_date ?? today)

  const reload = useCallback(async (date?: string) => {
    const list = await api.listEditions()
    setEditions(list.editions)
    const targetDate = date ?? selectedEdition?.edition_date ?? list.editions[0]?.edition_date ?? today
    const preview = await api.fetchPreview(targetDate)
    setSelectedEdition(preview.edition)
    setChanges(preview.changes)
    nav.setDate(targetDate)
  }, [api, nav, selectedEdition?.edition_date, today])

  useEffect(() => {
    reload().catch(() => {})
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const currentChange = changes[nav.changeIdx] ?? null

  const handleGenerate = async () => {
    await api.generateEdition(today)
    await reload(today)
  }

  const handlePublish = async (edition: DiscoveryEdition) => {
    await api.publishEdition(edition.id)
    await reload(edition.edition_date)
  }

  const handleSave = async (change: DiscoveryChange) => {
    await api.updateChange({
      id: change.id,
      title: change.title,
      summary: change.summary,
      briefing: change.briefing,
    })
    await reload(selectedEdition?.edition_date)
  }

  const handleDelete = async (id: number) => {
    await api.deleteChange(id)
    await reload(selectedEdition?.edition_date)
  }

  const onTouchStart = (e: React.TouchEvent) => {
    const t = e.touches[0]
    touchStart.current = { x: t.clientX, y: t.clientY }
  }

  const onTouchEnd = (e: React.TouchEvent) => {
    const start = touchStart.current
    const t = e.changedTouches[0]
    if (!start || previewTab !== 'home' || screen !== 'home') return
    const dx = t.clientX - start.x
    const dy = t.clientY - start.y
    const threshold = 40
    if (Math.abs(dx) < threshold && Math.abs(dy) < threshold) return
    if (Math.abs(dx) > Math.abs(dy)) {
      if (dx < 0) nav.nextChange(changes.length)
      else nav.prevChange(changes.length)
    } else {
      if (dy < 0) nav.goToPastDate()
      else nav.goToRecentDate()
    }
    touchStart.current = null
  }

  useEffect(() => {
    if (!nav.currentDate || nav.currentDate === selectedEdition?.edition_date) return
    api.fetchPreview(nav.currentDate).then((data) => {
      setSelectedEdition(data.edition)
      setChanges(data.changes)
    }).catch(() => {})
  }, [nav.currentDate]) // eslint-disable-line react-hooks/exhaustive-deps

  const tabBar = (
    <div className="discovery-tab-bar">
      {([
        ['home', '홈'],
        ['community', '커뮤니티'],
        ['search', '검색'],
        ['mypage', '마이'],
      ] as const).map(([id, label]) => (
        <button
          key={id}
          type="button"
          className={previewTab === id ? 'active' : ''}
          onClick={() => {
            setPreviewTab(id)
            setScreen('home')
          }}
        >
          {label}
        </button>
      ))}
    </div>
  )

  const previewBody = () => {
    if (previewTab === 'community') return <CommunityTab />
    if (previewTab === 'search') return <SearchTab onSearch={api.search} />
    if (previewTab === 'mypage') return <MyPageTab />

    if (screen === 'changeDetail' && currentChange) {
      return <ChangeDetail change={currentChange} onBack={() => setScreen('home')} />
    }
    if (screen === 'pollDetail' && currentChange) {
      return (
        <PollDetail
          change={currentChange}
          onBack={() => setScreen('home')}
          onVote={async (pollId, idx) => {
            const res = await api.vote(pollId, idx)
            return res.data
          }}
        />
      )
    }

    return (
      <div onTouchStart={onTouchStart} onTouchEnd={onTouchEnd}>
        {nav.boundaryMessage && <div className="discovery-boundary">{nav.boundaryMessage}</div>}
        <DiscoveryHome
          change={currentChange}
          changeIndex={nav.changeIdx}
          total={changes.length}
          date={nav.currentDate}
          onOpenDetail={() => setScreen('changeDetail')}
          onOpenPoll={() => setScreen('pollDetail')}
        />
      </div>
    )
  }

  return (
    <div className="discovery-root">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <div>
          <h2 style={{ margin: 0 }}>오늘의 발견</h2>
          <p style={{ margin: '4px 0 0', color: '#666', fontSize: 13 }}>
            Admin 검수 · 서비스 UI 미리보기 (기존 gist와 완전 격리)
          </p>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <button
            type="button"
            className={`discovery-btn discovery-btn-secondary${viewMode === 'ops' ? '' : ''}`}
            onClick={() => setViewMode('ops')}
          >
            운영
          </button>
          <button type="button" className="discovery-btn" onClick={() => setViewMode('preview')}>
            미리보기
          </button>
        </div>
      </div>

      {api.error && (
        <div className="discovery-boundary" style={{ marginBottom: 12 }}>{api.error}</div>
      )}

      {viewMode === 'ops' ? (
        <div className="discovery-panel">
          <div>
            <EditionList
              editions={editions}
              selectedDate={selectedEdition?.edition_date ?? null}
              onSelect={(date) => reload(date)}
              onGenerate={handleGenerate}
              onPublish={handlePublish}
              loading={api.loading}
            />
          </div>
          <EditionEditor changes={changes} onSave={handleSave} onDelete={handleDelete} />
        </div>
      ) : (
        <PreviewFrame
          title="오늘의 발견"
          subtitle={nav.currentDate}
          footer={tabBar}
        >
          {previewBody()}
        </PreviewFrame>
      )}
    </div>
  )
}
