import { useCallback, useEffect, useState } from 'react'
import { Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom'
import DiscoveryAppShell from './components/DiscoveryAppShell'
import DiscoveryHome from './components/DiscoveryHome'
import ChangeDetail from './components/ChangeDetail'
import SearchTab from './components/SearchTab'
import { usePointerSwipe } from './hooks/usePointerSwipe'
import type { DiscoveryChange, DiscoveryEdition, DiscoveryPollStats, PublicTab } from './types'
import './discovery.css'
import PreparingBanner from './components/PreparingBanner'
import PublicCommunityTab from './components/PublicCommunityTab'
import PublicMyPageTab from './components/PublicMyPageTab'
import PublicPollDetail from './components/PublicPollDetail'
import { useDiscoveryPublicApi } from './hooks/useDiscoveryPublicApi'
import { usePublicSwipeNavigation } from './hooks/usePublicSwipeNavigation'
import { todayKstDate } from './utils/kstDate'

const PUBLIC_ENABLED = import.meta.env.VITE_ENABLE_DISCOVERY_PUBLIC === 'true'

function DisabledPublic() {
  return (
    <div className="discovery-root discovery-public-disabled">
      <p>오늘의 발견 공개 서비스가 일시적으로 중단되었습니다.</p>
    </div>
  )
}

function DiscoveryShell({
  children,
  activeTab,
  editionDate,
  showHeader,
}: {
  children: React.ReactNode
  activeTab: PublicTab
  editionDate?: string
  showHeader?: boolean
}) {
  const navigate = useNavigate()

  return (
    <DiscoveryAppShell
      activeTab={activeTab}
      onTabChange={(tab) => {
        if (tab === 'community') navigate('/discovery/community')
        else if (tab === 'mypage') navigate('/discovery/me')
        else if (tab === 'search') navigate('/discovery/search')
      }}
      onLogoTap={() => navigate('/discovery')}
      onSearchTap={() => navigate('/discovery/search')}
      showHeader={showHeader}
      editionDate={editionDate}
    >
      {children}
    </DiscoveryAppShell>
  )
}

function HomePage() {
  const api = useDiscoveryPublicApi()
  const [editions, setEditions] = useState<DiscoveryEdition[]>([])
  const [changes, setChanges] = useState<DiscoveryChange[]>([])
  const [preparingMessage, setPreparingMessage] = useState<string | null>(null)
  const [screen, setScreen] = useState<'home' | 'changeDetail' | 'pollDetail'>('home')
  const [pollStats, setPollStats] = useState<DiscoveryPollStats | null>(null)
  const [pollSelectedIdx, setPollSelectedIdx] = useState<number | null>(null)
  const [comments, setComments] = useState<Array<{ id: number; body: string; created_at: string }>>([])
  const [commentsCursor, setCommentsCursor] = useState<string | null>(null)
  const [hasMoreComments, setHasMoreComments] = useState(false)

  const today = todayKstDate()
  const nav = usePublicSwipeNavigation(editions, today)

  const reload = useCallback(async (date?: string) => {
    const list = await api.listEditions()
    setEditions(list.items)
    const targetDate = date ?? nav.currentDate
    let payload
    if (targetDate === today) {
      payload = await api.fetchToday()
      setPreparingMessage(payload.meta.message ?? null)
    } else {
      payload = await api.fetchEdition(targetDate)
      setPreparingMessage(null)
    }
    setChanges(payload.changes)
    if (payload.edition?.edition_date) {
      nav.setDate(payload.edition.edition_date)
    }
  }, [api, nav, today])

  useEffect(() => {
    reload().catch(() => {})
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!nav.currentDate) return
    reload(nav.currentDate).catch(() => {})
  }, [nav.currentDate]) // eslint-disable-line react-hooks/exhaustive-deps

  const currentChange = changes[nav.changeIdx] ?? null
  const isHomeSwipe = screen === 'home'

  const swipeHandlers = usePointerSwipe({
    enabled: isHomeSwipe,
    onSwipeLeft: () => nav.nextChange(changes.length),
    onSwipeRight: () => nav.prevChange(changes.length),
    onSwipeUp: () => nav.goToPastDate(),
    onSwipeDown: () => nav.goToRecentDate(),
  })

  const loadComments = useCallback(async (pollId: number, cursor?: string | null) => {
    const res = await api.fetchComments(pollId, cursor)
    setComments((prev) => (cursor ? [...prev, ...res.items] : res.items))
    setCommentsCursor(res.next_cursor)
    setHasMoreComments(res.has_more)
  }, [api])

  const handleVoteSubmit = async (pollId: number, optionIdx: number) => {
    const res = await api.vote(pollId, optionIdx)
    setPollStats(res.data.stats)
    setPollSelectedIdx(optionIdx)
    await loadComments(pollId)
    setScreen('pollDetail')
  }

  let body: React.ReactNode
  if (screen === 'changeDetail' && currentChange) {
    body = <ChangeDetail change={currentChange} onBack={() => setScreen('home')} />
  } else if (screen === 'pollDetail' && currentChange && pollStats) {
    body = (
      <PublicPollDetail
        change={currentChange}
        editionDate={nav.currentDate}
        onBack={() => setScreen('home')}
        hasVoted
        stats={pollStats}
        selectedIdx={pollSelectedIdx}
        onVoteSubmit={async () => {}}
        comments={comments}
        hasMoreComments={hasMoreComments}
        onLoadMoreComments={() => {
          const pollId = currentChange.poll?.id
          if (pollId && commentsCursor) {
            void loadComments(pollId, commentsCursor)
          }
        }}
        onSubmitComment={async (text) => {
          const pollId = currentChange.poll?.id
          if (!pollId) return
          await api.postComment(pollId, text)
          await loadComments(pollId)
        }}
      />
    )
  } else {
    body = (
      <div className="discovery-swipe-area" {...swipeHandlers}>
        {preparingMessage && <PreparingBanner message={preparingMessage} />}
        {nav.boundaryMessage && <div className="discovery-boundary">{nav.boundaryMessage}</div>}
        {api.error && <div className="discovery-boundary">{api.error}</div>}
        {currentChange?.poll?.viewer?.has_voted ? (
          <div className="discovery-home">
            <p className="discovery-change-summary">이미 투표에 참여하셨습니다.</p>
            <button
              type="button"
              className="discovery-btn discovery-btn-vote"
              onClick={() => {
                setPollStats(currentChange.poll?.stats ?? null)
                setPollSelectedIdx(currentChange.poll?.viewer?.option_idx ?? null)
                if (currentChange.poll?.id) loadComments(currentChange.poll.id).catch(() => {})
                setScreen('pollDetail')
              }}
            >
              투표 결과 보기
            </button>
          </div>
        ) : (
          <DiscoveryHome
            key={currentChange?.id ?? nav.changeIdx}
            change={currentChange}
            changeIndex={nav.changeIdx}
            total={changes.length}
            onOpenBriefing={() => setScreen('changeDetail')}
            onVoteSubmit={handleVoteSubmit}
          />
        )}
      </div>
    )
  }

  return (
    <DiscoveryShell activeTab="home" editionDate={nav.currentDate} showHeader={screen === 'home'}>
      {body}
    </DiscoveryShell>
  )
}

function ChangeRoute() {
  const { id } = useParams()
  const api = useDiscoveryPublicApi()
  const navigate = useNavigate()
  const [change, setChange] = useState<DiscoveryChange | null>(null)

  useEffect(() => {
    const changeId = Number(id)
    if (!changeId) return
    api.fetchChange(changeId).then((data) => setChange(data.change)).catch(() => {})
  }, [api, id])

  if (!change) return <DiscoveryShell activeTab="home"><p className="discovery-change-summary">불러오는 중…</p></DiscoveryShell>

  return (
    <DiscoveryShell activeTab="home" showHeader={false}>
      <ChangeDetail change={change} onBack={() => navigate('/discovery')} />
    </DiscoveryShell>
  )
}

function PollRoute() {
  const { id } = useParams()
  const api = useDiscoveryPublicApi()
  const navigate = useNavigate()
  const [change, setChange] = useState<DiscoveryChange | null>(null)
  const [editionDate, setEditionDate] = useState('')
  const [hasVoted, setHasVoted] = useState(false)
  const [stats, setStats] = useState<DiscoveryPollStats | null>(null)
  const [selectedIdx, setSelectedIdx] = useState<number | null>(null)
  const [comments, setComments] = useState<Array<{ id: number; body: string; created_at: string }>>([])
  const [commentsCursor, setCommentsCursor] = useState<string | null>(null)
  const [hasMoreComments, setHasMoreComments] = useState(false)

  const pollId = Number(id)

  const loadComments = useCallback(async (cursor?: string | null) => {
    const res = await api.fetchComments(pollId, cursor)
    setComments((prev) => (cursor ? [...prev, ...res.items] : res.items))
    setCommentsCursor(res.next_cursor)
    setHasMoreComments(res.has_more)
  }, [api, pollId])

  useEffect(() => {
    if (!pollId) return
    api.fetchPoll(pollId).then((data) => {
      setChange({ ...data.change, poll: data.poll })
      setEditionDate(data.edition.edition_date)
      setHasVoted(data.viewer.has_voted)
      setStats(data.poll.stats)
      setSelectedIdx(data.viewer.option_idx)
      if (data.viewer.has_voted) {
        loadComments().catch(() => {})
      }
    }).catch(() => {})
  }, [api, pollId, loadComments])

  const handleVoteSubmit = async (optionIdx: number) => {
    const res = await api.vote(pollId, optionIdx)
    setHasVoted(true)
    setStats(res.data.stats)
    setSelectedIdx(optionIdx)
    await loadComments()
  }

  if (!change) {
    return <DiscoveryShell activeTab="home"><p className="discovery-change-summary">불러오는 중…</p></DiscoveryShell>
  }

  return (
    <DiscoveryShell activeTab="home" showHeader={false}>
      <PublicPollDetail
        change={change}
        editionDate={editionDate}
        onBack={() => navigate('/discovery')}
        hasVoted={hasVoted}
        stats={stats}
        selectedIdx={selectedIdx}
        onVoteSubmit={handleVoteSubmit}
        comments={comments}
        hasMoreComments={hasMoreComments}
        onLoadMoreComments={() => {
          if (commentsCursor) {
            void loadComments(commentsCursor)
          }
        }}
        onSubmitComment={async (text) => {
          await api.postComment(pollId, text)
          await loadComments()
        }}
      />
    </DiscoveryShell>
  )
}

function CommunityPage() {
  const api = useDiscoveryPublicApi()
  const navigate = useNavigate()
  const [changes, setChanges] = useState<DiscoveryChange[]>([])
  const [editionDate, setEditionDate] = useState('')

  useEffect(() => {
    api.fetchToday().then((data) => {
      setChanges(data.changes)
      setEditionDate(data.edition?.edition_date ?? data.meta.today_date)
    }).catch(() => {})
  }, [api])

  return (
    <DiscoveryShell activeTab="community">
      <PublicCommunityTab
        changes={changes}
        editionDate={editionDate}
        onOpenPoll={(pollId) => navigate(`/discovery/poll/${pollId}`)}
      />
    </DiscoveryShell>
  )
}

function SearchPage() {
  const api = useDiscoveryPublicApi()
  const [hideHeader, setHideHeader] = useState(false)
  return (
    <DiscoveryShell activeTab="search" showHeader={!hideHeader}>
      <SearchTab onSearch={api.search} onViewChange={(v) => setHideHeader(v === 'results')} />
    </DiscoveryShell>
  )
}

function MyPage() {
  return (
    <DiscoveryShell activeTab="mypage">
      <PublicMyPageTab />
    </DiscoveryShell>
  )
}

export default function DiscoveryApp() {
  if (!PUBLIC_ENABLED) {
    return <DisabledPublic />
  }

  return (
    <div className="discovery-root discovery-public-root">
      <Routes>
        <Route index element={<HomePage />} />
        <Route path="change/:id" element={<ChangeRoute />} />
        <Route path="poll/:id" element={<PollRoute />} />
        <Route path="community" element={<CommunityPage />} />
        <Route path="search" element={<SearchPage />} />
        <Route path="me" element={<MyPage />} />
        <Route path="*" element={<Navigate to="/discovery" replace />} />
      </Routes>
    </div>
  )
}
