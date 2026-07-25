import { useCallback, useEffect, useState } from 'react'
import EditionList from './EditionList'
import EditionEditor from './EditionEditor'
import DiscoveryAppShell from './ServicePreview/DiscoveryAppShell'
import DiscoveryHome from './ServicePreview/DiscoveryHome'
import ChangeDetail from './ServicePreview/ChangeDetail'
import PollDetail from './ServicePreview/PollDetail'
import CommunityTab from './ServicePreview/CommunityTab'
import SearchTab from './ServicePreview/SearchTab'
import MyPageTab from './ServicePreview/MyPageTab'
import { useDiscoveryApi } from './hooks/useDiscoveryApi'
import { useSwipeNavigation } from './hooks/useSwipeNavigation'
import { usePointerSwipe } from './hooks/usePointerSwipe'
import type { DiscoveryChange, DiscoveryEdition, DiscoveryPollStats, PreviewScreen, PreviewTab } from './types'
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
  const [pollStats, setPollStats] = useState<DiscoveryPollStats | null>(null)
  const [pollSelectedIdx, setPollSelectedIdx] = useState<number | null>(null)

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
  const isHomeSwipe = previewTab === 'home' && screen === 'home'

  const swipeHandlers = usePointerSwipe({
    enabled: isHomeSwipe,
    onSwipeLeft: () => nav.nextChange(changes.length),
    onSwipeRight: () => nav.prevChange(changes.length),
    onSwipeUp: () => nav.goToPastDate(),
    onSwipeDown: () => nav.goToRecentDate(),
  })

  useEffect(() => {
    if (!nav.currentDate || nav.currentDate === selectedEdition?.edition_date) return
    api.fetchPreview(nav.currentDate).then((data) => {
      setSelectedEdition(data.edition)
      setChanges(data.changes)
    }).catch(() => {})
  }, [nav.currentDate]) // eslint-disable-line react-hooks/exhaustive-deps

  const goHome = () => {
    setPreviewTab('home')
    setScreen('home')
    nav.setDate(today)
    setPollStats(null)
    setPollSelectedIdx(null)
  }

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

  const handleVoteSubmit = async (pollId: number, optionIdx: number) => {
    const res = await api.vote(pollId, optionIdx)
    setPollStats(res.data)
    setPollSelectedIdx(optionIdx)
    setScreen('pollDetail')
  }

  const previewContent = () => {
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
          editionDate={nav.currentDate}
          onBack={() => setScreen('home')}
          initialStats={pollStats}
          initialSelectedIdx={pollSelectedIdx}
        />
      )
    }

    return (
      <div className="discovery-swipe-area" {...swipeHandlers}>
        {nav.boundaryMessage && <div className="discovery-boundary">{nav.boundaryMessage}</div>}
        <DiscoveryHome
          key={currentChange?.id ?? nav.changeIdx}
          change={currentChange}
          changeIndex={nav.changeIdx}
          total={changes.length}
          onOpenBriefing={() => setScreen('changeDetail')}
          onVoteSubmit={handleVoteSubmit}
        />
      </div>
    )
  }

  return (
    <div className="discovery-root">
      <div className="discovery-ops-header">
        <div>
          <h2>오늘의 발견</h2>
          <p>Admin 검수 · 서비스 UI 미리보기 (기존 gist와 완전 격리)</p>
        </div>
        <div className="discovery-ops-toggle">
          <button type="button" className={`discovery-btn discovery-btn-secondary${viewMode === 'ops' ? ' active' : ''}`} onClick={() => setViewMode('ops')}>운영</button>
          <button type="button" className="discovery-btn" onClick={() => setViewMode('preview')}>미리보기</button>
        </div>
      </div>

      {api.error && <div className="discovery-boundary">{api.error}</div>}

      {viewMode === 'ops' ? (
        <div className="discovery-panel">
          <EditionList
            editions={editions}
            selectedDate={selectedEdition?.edition_date ?? null}
            onSelect={(date) => reload(date)}
            onGenerate={handleGenerate}
            onPublish={handlePublish}
            loading={api.loading}
          />
          <EditionEditor changes={changes} onSave={handleSave} onDelete={handleDelete} />
        </div>
      ) : (
        <DiscoveryAppShell
          activeTab={previewTab}
          onTabChange={(tab) => {
            setPreviewTab(tab)
            setScreen('home')
          }}
          onLogoTap={goHome}
          onSearchTap={() => setPreviewTab('search')}
          showHeader={screen === 'home' && previewTab === 'home'}
          editionDate={nav.currentDate}
        >
          {previewContent()}
        </DiscoveryAppShell>
      )}
    </div>
  )
}
