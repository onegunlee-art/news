import { useState, useEffect, useCallback, type RefObject } from 'react'
import { useParams, Link, useNavigate, useLocation, useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import MaterialIcon from '../components/Common/MaterialIcon'
import { newsApi } from '../services/api'
import ShareMenu from '../components/Common/ShareMenu'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { formatSourceDisplayName, buildEditorialLine } from '../utils/formatSource'
import { extractTitleFromUrl } from '../utils/extractTitleFromUrl'
import { formatContentHtml, stripHtml, stripAnalysisMetaPhrases } from '../utils/sanitizeHtml'
import PaywallOverlay from '../components/Paywall/PaywallOverlay'
import ArticleChatPanel from '../components/ArticleChat/ArticleChatPanel'
import { useMenuConfig } from '../hooks/useMenuConfig'
import { useSwipeNavigation } from '../hooks/useSwipeNavigation'
import { apiErrorMessage } from '../utils/apiErrorMessage'
import { newsDetailPath } from '../utils/newsDetailLink'

interface NewsDetail {
  id: number
  title: string
  description: string | null
  content: string | null
  why_important: string | null
  narration: string | null
  future_prediction?: string | null
  source: string | null
  original_source?: string | null
  original_title?: string | null
  url: string
  display_date?: string | null
  published_at: string | null
  created_at?: string | null
  updated_at?: string | null
  time_ago: string | null
  is_bookmarked?: boolean
  image_url?: string | null
  author?: string | null
  category?: string | null
  category_parent?: string | null
  prev_article?: { id: number; title: string } | null
  next_article?: { id: number; title: string } | null
  audio_url?: string | null
  access_restricted?: boolean
  restriction_type?: string
  series_id?: string | null
  series_title?: string | null
  series_order?: number | null
  series_articles?: { id: number; title: string; series_order: number }[] | null
}

export default function NewsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const { subCategoryToLabel, parentKeyToLabel, fromTabToApi, tabLabels } = useMenuConfig()
  const locationState = (location.state as { fromTab?: string; swipeDir?: 'left' | 'right' } | null) ?? null
  const fromTab = locationState?.fromTab || searchParams.get('tab') || undefined
  const swipeDir = locationState?.swipeDir

  const { isAuthenticated, login } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [news, setNews] = useState<NewsDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [analysisCollapsed, setAnalysisCollapsed] = useState(true)

  /** 검색·공유 시 표준 URL 고정 (www HTTPS, 슬래시 없음) — og-news-html.php 과 동일 */
  useEffect(() => {
    if (!id || !/^\d+$/.test(id)) return
    const href = `https://www.thegist.co.kr/news/${id}`
    const link = document.createElement('link')
    link.rel = 'canonical'
    link.href = href
    document.head.appendChild(link)
    return () => {
      link.remove()
    }
  }, [id])

  useEffect(() => {
    if (!news || news.id !== Number(id)) return
    const prevTitle = document.title
    document.title = `${news.title} | the gist.`
    const descText = stripHtml(news.description || news.narration || '').slice(0, 160)
    let metaDesc = document.querySelector<HTMLMetaElement>('meta[name="description"]')
    const prevDesc = metaDesc?.content ?? ''
    if (descText) {
      if (!metaDesc) {
        metaDesc = document.createElement('meta')
        metaDesc.name = 'description'
        document.head.appendChild(metaDesc)
      }
      metaDesc.content = descText
    }
    return () => {
      document.title = prevTitle
      if (metaDesc) metaDesc.content = prevDesc
    }
  }, [news, id])

  // 전역 팝업 플레이어에서 재생 (제목 → 매체설명 → 내레이션 → The Gist)
  // Listen 클릭 시 최신 기사 데이터를 강제 조회하여 stale state로 인한 이전 보이스 재생 방지
  const playArticle = async () => {
    if (!news) return
    let data = news
    try {
      const params: Record<string, unknown> = { _t: Date.now() }
      if (fromTab && fromTabToApi[fromTab]) params.from_tab = fromTabToApi[fromTab]
      const res = await newsApi.getDetail(news.id, params)
      if (res.data?.success && res.data?.data) {
        data = res.data.data as NewsDetail
        setNews(data)
      }
    } catch {
      // 조회 실패 시 기존 news 사용
    }
    const dateStr = data.published_at
      ? `${new Date(data.published_at).getFullYear()}년 ${new Date(data.published_at).getMonth() + 1}월 ${new Date(data.published_at).getDate()}일`
      : (data.updated_at || data.created_at)
        ? `${new Date(data.updated_at || data.created_at!).getFullYear()}년 ${new Date(data.updated_at || data.created_at!).getMonth() + 1}월 ${new Date(data.updated_at || data.created_at!).getDate()}일`
        : ''
    const rawSource = (data.original_source && data.original_source.trim()) || (data.source === 'Admin' ? 'the gist.' : data.source || 'the gist.')
    const sourceDisplay = formatSourceDisplayName(rawSource) || 'the gist.'
    const originalTitle = (data.original_title && String(data.original_title).trim()) || extractTitleFromUrl(data.url) || '원문'
    const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
    const critiqueText = data.why_important ? stripHtml(data.why_important) : ''
    const narrationText = stripHtml(data.narration || data.content || data.description || '')
    if (!narrationText && !critiqueText) {
      alert('재생할 본문 내용이 없습니다.')
      return
    }
    addAudioItem({
      id: data.id,
      title: data.title,
      description: data.description ?? data.content ?? null,
      source: data.source ?? null,
      category: data.category ?? null,
      published_at: data.published_at ?? null,
    })
    const imageUrl = data.image_url || getPlaceholderImageUrl(
      { id: data.id, title: data.title, description: data.description, published_at: data.published_at, url: data.url, source: data.source },
      800,
      400
    )
    openAndPlay(data.title, editorialLine, narrationText, critiqueText, 1.0, imageUrl, data.id, data.audio_url)
  }

  const fetchNewsDetail = useCallback(async (newsId: number) => {
    setIsLoading(true)
    setError(null)

    try {
      const params = fromTab && fromTabToApi[fromTab] ? { from_tab: fromTabToApi[fromTab] } : undefined
      const response = await newsApi.getDetail(newsId, params)
      if (response.data.success) {
        const d = response.data.data
        setNews(d)
        setIsBookmarked(d.is_bookmarked || false)
      }
    } catch (error: unknown) {
      setError(apiErrorMessage(error, '뉴스를 불러올 수 없습니다.'))
    } finally {
      setIsLoading(false)
    }
  }, [fromTab, fromTabToApi])

  const onSwipePrev = useCallback(() => {
    if (!news?.prev_article) return
    navigate(
      {
        pathname: `/news/${news.prev_article.id}`,
        search: fromTab ? `tab=${encodeURIComponent(fromTab)}` : '',
      },
      { state: fromTab ? { fromTab, swipeDir: 'right' as const } : { swipeDir: 'right' as const } },
    )
  }, [news, fromTab, navigate])

  const onSwipeNext = useCallback(() => {
    if (!news?.next_article) return
    navigate(
      {
        pathname: `/news/${news.next_article.id}`,
        search: fromTab ? `tab=${encodeURIComponent(fromTab)}` : '',
      },
      { state: fromTab ? { fromTab, swipeDir: 'left' as const } : { swipeDir: 'left' as const } },
    )
  }, [news, fromTab, navigate])

  const { containerRef, offsetX, isSwiping, cssTransition } = useSwipeNavigation({
    enabled: !!news && !isLoading,
    hasPrev: !!news?.prev_article,
    hasNext: !!news?.next_article,
    onSwipePrev,
    onSwipeNext,
  })

  useEffect(() => {
    if (id) void fetchNewsDetail(parseInt(id, 10))
  }, [id, fetchNewsDetail])

  // 기사가 바뀌면 AI 분석 보기는 항상 접힌 상태로 리셋
  useEffect(() => {
    setAnalysisCollapsed(true)
  }, [id])

  // 기사 상세 진입 시 항상 맨 위로 스크롤 (진입 직후 + 로딩 완료 후)
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [id])
  useEffect(() => {
    if (!isLoading) {
      window.scrollTo(0, 0)
    }
  }, [isLoading])

  // 선생성된 audio_url이 있으면 브라우저에 미리 받아두기 (듣기 버튼 클릭 시 즉시 재생)
  useEffect(() => {
    if (news?.audio_url && !news.access_restricted) {
      const link = document.createElement('link')
      link.rel = 'preload'
      link.as = 'fetch'
      link.href = news.audio_url
      link.crossOrigin = 'anonymous'
      document.head.appendChild(link)
      return () => { document.head.removeChild(link) }
    }
  }, [news?.audio_url, news?.access_restricted])

  const handleBookmark = async () => {
    if (!isAuthenticated || !id) return

    try {
      if (isBookmarked) {
        await newsApi.removeBookmark(parseInt(id))
        setIsBookmarked(false)
      } else {
        await newsApi.bookmark(parseInt(id))
        setIsBookmarked(true)
      }
    } catch (error: unknown) {
      alert(apiErrorMessage(error, '즐겨찾기 처리에 실패했습니다.'))
    }
  }

  // 표시용 날짜: display_date 우선 (created_at 기준, docs/DATE_POLICY.md). updated_at는 사용하지 않음.
  const formatHeaderDate = () => {
    const dateStr = news?.display_date ?? news?.published_at ?? news?.created_at
    if (dateStr) {
      const date = new Date(dateStr)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return news?.time_ago ?? ''
  }

  // 소스 이름 매핑 (표시 시 " Magazine" 제거, e.g. Foreign Affairs Magazine → Foreign Affairs) - 매체 설명용
  const getSourceName = () => {
    let raw: string
    if (news?.original_source && news.original_source.trim()) raw = news.original_source
    else if (news?.source === 'Admin') return 'the gist.'
    else raw = news?.source || 'the gist.'
    return formatSourceDisplayName(raw) || 'the gist.'
  }

  // 카테고리 라벨: 리스트와 동일하게 하위 카테고리 표시 (제목 위 오렌지색)
  const getCategoryLabel = () => {
    if (news?.category) return subCategoryToLabel[news.category] ?? news.category
    return 'the gist.'
  }

  // 글 목록 라벨: 하위 카테고리만 표시 (없으면 최신)
  const getListLabel = () => {
    const parent = news?.category_parent ?? (news?.category === 'economy' ? 'economy' : news?.category === 'entertainment' || news?.category === 'technology' ? 'special' : 'diplomacy')
    return parentKeyToLabel[parent] ?? parentKeyToLabel.latest ?? '최신'
  }

  // 이미지 URL (기사별 고유 시드, 중복 없음)
  const getImageUrl = () => {
    if (news?.image_url) return news.image_url
    if (!news) return 'https://picsum.photos/seed/default/800/400'
    return getPlaceholderImageUrl(
      { id: news.id, title: news.title, description: news.description, published_at: news.published_at, url: news.url, source: news.source },
      800,
      400
    )
  }

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-[60vh]">
        <LoadingSpinner size="large" />
      </div>
    )
  }

  if (error && !news) {
    return (
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-16 text-center">
        <div className="text-page-muted mb-4">
          <MaterialIcon name="warning" className="w-16 h-16 mx-auto" size={64} />
        </div>
        <h2 className="text-xl font-bold text-page mb-2">오류 발생</h2>
        <p className="text-page-secondary mb-6">{error}</p>
        <Link
          to="/"
          className="inline-block px-6 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg transition-colors"
        >
          홈으로 돌아가기
        </Link>
      </div>
    )
  }

  if (!news) return null

  const seriesNavEl =
    news.series_articles && news.series_articles.length > 1 ? (
      <div className="mb-8 rounded-xl border border-page bg-page-secondary/30 overflow-hidden">
        <div className="px-4 py-3 bg-page-secondary/50 border-b border-page">
          <h3 className="text-sm font-semibold text-page">
            {news.series_title || '시리즈'}
            <span className="ml-2 text-xs font-normal text-page-secondary">({news.series_articles.length}편)</span>
          </h3>
        </div>
        <ul className="divide-y divide-page">
          {news.series_articles.map((sa, idx) => {
            const isCurrent = sa.id === news.id
            return (
              <li key={sa.id}>
                {isCurrent ? (
                  <div className="flex items-center gap-3 px-4 py-3 bg-primary-500/10">
                    <span className="shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-primary-500 text-white text-xs font-bold">
                      {idx + 1}
                    </span>
                    <span className="text-sm font-medium text-primary-500 truncate">{sa.title}</span>
                  </div>
                ) : (
                  <Link
                    to={newsDetailPath(sa.id, fromTab)}
                    state={fromTab ? { fromTab } : undefined}
                    className="flex items-center gap-3 px-4 py-3 hover:bg-page-secondary transition-colors"
                  >
                    <span className="shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-page-secondary text-page-secondary text-xs font-medium">
                      {idx + 1}
                    </span>
                    <span className="text-sm text-page-secondary truncate hover:text-primary-500 transition-colors">{sa.title}</span>
                  </Link>
                )}
              </li>
            )
          })}
        </ul>
      </div>
    ) : null

  return (
    <div className="min-h-screen bg-page pb-8">
      {/* 상단 헤더 - Layout Header(h-14) 바로 아래에 붙도록 top-14 */}
      <div className="sticky top-14 z-30 bg-page border-b border-page">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-between h-12">
            {/* 상단 Back 버튼 - 상위 카테고리 라벨만 표시 */}
            {(() => {
              const backTab: string = fromTab && tabLabels.includes(fromTab) ? fromTab : getListLabel()
              return (
                <button
                  type="button"
                  onClick={() => navigate('/', { state: { restoreTab: backTab } })}
                  className="inline-flex items-center gap-2 text-sm text-page-secondary hover:text-page transition-colors"
                  aria-label={`${backTab} 목록으로 돌아가기`}
                >
                  <span className="text-lg leading-none">←</span>
                  <span className="font-medium">{backTab}</span>
                </button>
              )
            })()}

            {/* 오른쪽 액션 버튼들 */}
            <div className="flex items-center gap-4">
              {/* 오디오 재생 (팝업 플레이어) - 접근 제한 시 비활성 */}
              <button 
                onClick={news?.access_restricted ? undefined : playArticle}
                className={`p-1 transition-colors ${news?.access_restricted ? 'text-page-muted cursor-not-allowed' : 'text-page-secondary hover:text-page'}`}
                title={news?.access_restricted ? '구독 후 이용 가능' : '음성으로 듣기'}
                aria-label="재생"
                disabled={news?.access_restricted}
              >
                <MaterialIcon name="headphones" className="w-5 h-5" size={20} />
              </button>
              {/* 공유하기 (메뉴: 카카오톡 / 링크 복사 / 시스템 공유) */}
              {news && (
                <ShareMenu
                  title={news.title}
                  description={news.description || ''}
                  imageUrl={getImageUrl()}
                  webUrl={window.location.href}
                  className="text-page-secondary hover:text-page"
                  titleAttr="공유하기"
                />
              )}
              {/* 즐겨찾기 */}
              <button
                type="button"
                onClick={(e) => {
                  e.preventDefault()
                  e.stopPropagation()
                  if (!isAuthenticated) {
                    if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
                      navigate('/login', {
                        state: { returnTo: id ? newsDetailPath(parseInt(id, 10), fromTab) : undefined },
                      })
                    }
                    return
                  }
                  handleBookmark()
                }}
                className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-page-secondary hover:text-page'}`}
                title="즐겨찾기"
                aria-label={isBookmarked ? '즐겨찾기 해제' : '즐겨찾기 추가'}
              >
                {isBookmarked ? (
                  <MaterialIcon name="bookmark" filled className="w-5 h-5 text-primary-500" size={20} />
                ) : (
                  <MaterialIcon name="bookmark_border" className="w-5 h-5" size={20} />
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto flex flex-col lg:flex-row lg:gap-8">
        <div
          ref={containerRef as RefObject<HTMLDivElement>}
          className="flex-1 min-w-0 touch-pan-y overflow-x-hidden"
          style={{
            transform: `translate3d(${offsetX}px, 0, 0)`,
            transition: cssTransition,
            willChange: isSwiping ? 'transform' : undefined,
          }}
        >
      <motion.article
        key={news.id}
        initial={
          swipeDir === 'left'
            ? { x: '100%', opacity: 0.88 }
            : swipeDir === 'right'
              ? { x: '-100%', opacity: 0.88 }
              : { opacity: 0 }
        }
        animate={{ x: 0, opacity: 1 }}
        transition={{ type: 'tween', duration: 0.28, ease: [0.25, 0.1, 0.25, 1] }}
        className="w-full min-w-0"
      >
        {/* 대표 이미지 - 썸네일 정책: 정사각형(1:1) */}
        <div className="aspect-square bg-page-secondary overflow-hidden">
          <img
            src={getImageUrl()}
            alt={news.title}
            width={800}
            height={800}
            className="w-full h-full object-cover"
            decoding="async"
            {...({ fetchpriority: 'high' } as Record<string, string>)}
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                { id: news.id, title: news.title, description: news.description, published_at: news.published_at, url: news.url, source: news.source },
                800,
                400
              )
            }}
          />
        </div>

        <div className="px-4 pt-5 pb-8">
          {/* 카테고리 및 날짜 (리스트와 동일한 하부 카테고리 표시) */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <span className="text-primary-500 font-medium">{getCategoryLabel()}</span>
            <span className="text-page-muted"> | </span>
            <span className="text-page-secondary">{formatHeaderDate()}</span>
          </div>

          {/* 제목 */}
          <h1 className="text-2xl font-bold text-page leading-snug mb-2">
            {news.title}
          </h1>

          {/* 매체 설명 */}
          <p className="text-sm text-page-secondary mb-6">
            이 글은 {getSourceName()}에 게재된 {(news.original_title && news.original_title.trim()) || extractTitleFromUrl(news.url) || '원문'} 글의 시각을 참고하였습니다.
          </p>

          {/* 저자 정보 (있을 경우) */}
          {news.author && (
            <div className="text-sm text-page-secondary mb-4">
              <span className="font-medium text-page">{news.author}</span> 씀
            </div>
          )}

          {/* 오디오 재생 버튼 - 접근 제한 시 비활성 */}
          {!news.access_restricted && (
            <button
              onClick={playArticle}
              className="inline-flex items-center gap-2 text-base text-primary-500 hover:text-primary-600 transition-colors mb-6 pb-6 border-b border-page w-full"
            >
              <MaterialIcon name="headphones" className="w-5 h-5 shrink-0" size={20} />
              <span className="font-medium">AI 보이스로 듣기</span>
            </button>
          )}

          {/* === 접근 제한 시: 부분 콘텐츠 + 그래디언트 페이드 + 페이월 === */}
          {news.access_restricted ? (
            <>
              <div className="relative">
                {/* 부분 콘텐츠 (잘린 텍스트) + 하단 그래디언트 페이드 */}
                <div className="max-h-[40vh] overflow-hidden relative">
                  {news.why_important && (
                    <div className="mb-6 bg-amber-50 dark:bg-gray-800 dark:border dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                      <div className="px-5 py-5 sm:px-6 sm:py-6">
                        <div
                          className="text-gray-800 dark:text-gray-50 leading-relaxed whitespace-pre-wrap"
                          dangerouslySetInnerHTML={{ __html: formatContentHtml(news.why_important) }}
                        />
                      </div>
                    </div>
                  )}
                  {news.narration && (
                    <div className="prose prose-lg max-w-none text-page-secondary leading-relaxed whitespace-pre-wrap"
                      dangerouslySetInnerHTML={{ __html: formatContentHtml(news.narration) }}
                    />
                  )}
                  {!news.narration && news.description && (
                    <div className="prose prose-lg max-w-none text-page-secondary leading-relaxed"
                      dangerouslySetInnerHTML={{ __html: formatContentHtml(news.description) }}
                    />
                  )}
                  {/* 그래디언트 페이드 오버레이 */}
                  <div className="absolute bottom-0 left-0 right-0 h-48 bg-gradient-to-t from-white dark:from-gray-900 to-transparent pointer-events-none" />
                </div>

                {/* 페이월 오버레이 */}
                <PaywallOverlay
                  isAuthenticated={isAuthenticated}
                  restrictionType={
                    (news.restriction_type === 'subscription_required' ||
                      news.restriction_type === 'login_or_subscribe'
                      ? news.restriction_type
                      : null) as 'subscription_required' | 'login_or_subscribe' | undefined
                  }
                  returnTo={id ? newsDetailPath(parseInt(id, 10), fromTab) : undefined}
                  onKakaoLogin={login}
                />
              </div>
              {seriesNavEl}
            </>
          ) : (
            <>
              {/* === 전체 접근: 기존 기사 콘텐츠 렌더링 === */}
              {/* 비평(요약글) 영역 */}
              {news.why_important && (
                <div className="mb-20 bg-amber-50 dark:bg-gray-800 dark:border dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                  <div className="px-5 py-5 sm:px-6 sm:py-6">
                    <div
                      className="text-gray-800 dark:text-gray-50 leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 dark:[&_td]:border-gray-600 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 dark:[&_th]:border-gray-600 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100 dark:[&_th]:bg-gray-700"
                      dangerouslySetInnerHTML={{ __html: formatContentHtml(news.why_important) }}
                    />
                  </div>
                </div>
              )}

              {/* 내레이션 — 메인 콘텐츠 */}
              {news.narration && (
                <div className="prose prose-lg max-w-none mb-20 text-page-secondary leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100"
                  dangerouslySetInnerHTML={{ __html: formatContentHtml(news.narration) }}
                />
              )}

              {/* 내레이션이 없는 경우: description 표시 */}
              {!news.narration && news.description && (
                <div className="prose prose-lg max-w-none mb-20 text-page-secondary leading-relaxed [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100"
                  dangerouslySetInnerHTML={{ __html: formatContentHtml(news.description) }}
                />
              )}

              {seriesNavEl}

              {/* 참고 글 AI 구조 분석 */}
              {(news.content || (news.url && news.url !== '#')) && (
                <div className="mb-8 border-t border-page pt-6 mt-8">
                  <div className="flex justify-between items-center gap-4 mb-3">
                    <h3 className="flex items-center gap-2 text-sm font-semibold text-primary-500 uppercase tracking-wider shrink-0">
                      <MaterialIcon name="cognition_2" className="w-4 h-4" size={16} />
                      <button
                        type="button"
                        onClick={() => setAnalysisCollapsed((c) => !c)}
                        className="font-semibold text-primary-500 hover:text-primary-600 cursor-pointer"
                      >
                        {analysisCollapsed ? '원문 AI 분석 펼치기' : '원문 AI 분석 접기'}
                      </button>
                    </h3>
                    {news.url && news.url !== '#' && (
                      <a
                        href={news.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-1.5 text-sm font-semibold text-primary-500 hover:text-primary-600 transition-colors shrink-0"
                      >
                        <MaterialIcon name="open_in_new" className="w-4 h-4" size={16} />
                        원문 보러가기
                      </a>
                    )}
                  </div>
                  {!analysisCollapsed && news.content && (
                    <div className="p-4 bg-page-secondary rounded-lg border border-page text-sm text-page-secondary leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100"
                      dangerouslySetInnerHTML={{ __html: formatContentHtml(stripAnalysisMetaPhrases(news.content)) }}
                    />
                  )}
                </div>
              )}
            </>
          )}

          {!news.access_restricted && (
            <ArticleChatPanel newsId={news.id} />
          )}

          {/* 하단 네비: 3줄 (이전 · 목록 · 다음) */}
          {(() => {
            const backTab: string = fromTab && tabLabels.includes(fromTab) ? fromTab : getListLabel()
            return (
              <nav className="mt-12 mb-4" aria-label="기사 네비게이션">
                <div className="border-t border-b border-page">
                  {/* 1줄: (←) 이전 "기사 제목" */}
                  <div className="border-b border-page">
                    {news.prev_article ? (
                      <Link
                        to={newsDetailPath(news.prev_article.id, fromTab)}
                        state={fromTab ? { fromTab } : undefined}
                        className="flex items-center gap-3 px-4 py-5 group transition-colors hover:bg-page-secondary"
                      >
                        <span className="text-page-secondary text-base shrink-0 group-hover:text-page transition-colors">←</span>
                        <span className="text-sm text-page-secondary shrink-0">이전</span>
                        <p className="text-sm text-page-secondary font-medium truncate group-hover:text-primary-500 transition-colors" title={news.prev_article.title}>
                          {news.prev_article.title}
                        </p>
                      </Link>
                    ) : (
                      <div className="flex items-center gap-3 px-4 py-5">
                        <span className="text-page-secondary text-base shrink-0 opacity-40">←</span>
                        <p className="text-sm text-page-secondary">이전 기사가 없습니다</p>
                      </div>
                    )}
                  </div>

                  {/* 2줄: 목록으로 돌아가기 (매우 얇게) */}
                  <div className="border-b border-page">
                    <button
                      type="button"
                      onClick={() => navigate('/', { state: { restoreTab: backTab } })}
                      className="w-full flex items-center justify-center gap-1.5 py-2.5 text-sm text-page-secondary hover:text-page hover:bg-page-secondary transition-colors"
                    >
                      <MaterialIcon name="menu" className="w-4 h-4 shrink-0" size={18} />
                      <span>목록으로 돌아가기</span>
                    </button>
                  </div>

                  {/* 3줄: (→) 다음 "기사 제목" */}
                  <div>
                    {news.next_article ? (
                      <Link
                        to={newsDetailPath(news.next_article.id, fromTab)}
                        state={fromTab ? { fromTab } : undefined}
                        className="flex items-center justify-end gap-3 px-4 py-5 group transition-colors hover:bg-page-secondary"
                      >
                        <span className="text-sm text-page-secondary font-medium truncate group-hover:text-primary-500 transition-colors text-right" title={news.next_article.title}>
                          {news.next_article.title}
                        </span>
                        <span className="text-sm text-page-secondary shrink-0">다음</span>
                        <span className="text-page-secondary text-base shrink-0 group-hover:text-page transition-colors">→</span>
                      </Link>
                    ) : (
                      <div className="flex items-center justify-end gap-3 px-4 py-5">
                        <p className="text-sm text-page-secondary">다음 기사가 없습니다</p>
                        <span className="text-page-secondary text-base shrink-0 opacity-40">→</span>
                      </div>
                    )}
                  </div>
                </div>
              </nav>
            )
          })()}
        </div>
      </motion.article>
        </div>
      </div>

      {error && (
        <div className="fixed bottom-4 left-4 right-4 max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto bg-red-50 text-red-600 px-4 py-3 rounded-lg text-center text-sm">
          {error}
        </div>
      )}
    </div>
  )
}
