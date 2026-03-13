import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate, useLocation } from 'react-router-dom'
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
  access_restricted?: boolean
  restriction_type?: string
}

/** 상위 카테고리 (기사 소속) → 표시 라벨 - back 버튼 fallback용 */
const parentCategoryToLabel: Record<string, string> = {
  diplomacy: '외교',
  economy: '경제',
  special: '특집',
}

/** 하위 카테고리 → 표시 라벨 (리스트와 본문 통일) */
const subCategoryToLabel: Record<string, string> = {
  politics_diplomacy: 'Politics/Diplomacy',
  economy_industry: 'Economy/Industry',
  society: 'Society',
  security_conflict: 'Security/Conflict',
  environment: 'Environment',
  science_technology: 'Science/Technology',
  culture: 'Culture',
  health_development: 'Health/Development',
}

/** 홈 탭 타입 (진입 탭 전달 시 back이 이 탭으로 복귀) */
const HOME_TABS = ['최신', '외교', '경제', '특집', '인기'] as const
type HomeTabType = (typeof HOME_TABS)[number]

/** 진입 탭 → API from_tab (다음 기사가 해당 탭 리스트 기준이 되도록) */
const fromTabToApi: Record<HomeTabType, string> = {
  '최신': 'latest',
  '외교': 'diplomacy',
  '경제': 'economy',
  '특집': 'special',
  '인기': 'popular',
}

export default function NewsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const fromTab = (location.state as { fromTab?: HomeTabType } | null)?.fromTab
  const { isAuthenticated, login } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [news, setNews] = useState<NewsDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [analysisCollapsed, setAnalysisCollapsed] = useState(true)

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
    const rawSource = (data.original_source && data.original_source.trim()) || (data.source === 'Admin' ? 'The Gist' : data.source || 'The Gist')
    const sourceDisplay = formatSourceDisplayName(rawSource) || 'The Gist'
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
    openAndPlay(data.title, editorialLine, narrationText, critiqueText, 1.0, imageUrl, data.id)
  }

  useEffect(() => {
    if (id) {
      fetchNewsDetail(parseInt(id))
    }
  }, [id])

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

  const fetchNewsDetail = async (newsId: number) => {
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
    } catch (error: any) {
      setError(error.response?.data?.message || '뉴스를 불러올 수 없습니다.')
    } finally {
      setIsLoading(false)
    }
  }

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
    } catch (error: any) {
      const msg = error.response?.data?.message ?? error.message ?? '즐겨찾기 처리에 실패했습니다.'
      alert(msg)
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
    else if (news?.source === 'Admin') return 'The Gist'
    else raw = news?.source || 'The Gist'
    return formatSourceDisplayName(raw) || 'The Gist'
  }

  // 카테고리 라벨: 리스트와 동일하게 하위 카테고리 표시 (제목 위 오렌지색)
  const getCategoryLabel = () => {
    if (news?.category) return subCategoryToLabel[news.category] ?? news.category
    return 'The Gist'
  }

  // 글 목록 라벨: 하위 카테고리만 표시 (없으면 최신)
  const getListLabel = () => {
    const parent = news?.category_parent ?? (news?.category === 'economy' ? 'economy' : news?.category === 'entertainment' || news?.category === 'technology' ? 'special' : 'diplomacy')
    return parentCategoryToLabel[parent] ?? '최신'
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

  return (
    <div className="min-h-screen bg-page pb-8">
      {/* 상단 헤더 - Layout Header(h-14) 바로 아래에 붙도록 top-14 */}
      <div className="sticky top-14 z-30 bg-page border-b border-page">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-between h-12">
            {/* 상단 Back 버튼 - 상위 카테고리 라벨만 표시 */}
            {(() => {
              const backTab: HomeTabType = fromTab && HOME_TABS.includes(fromTab) ? fromTab : (getListLabel() as HomeTabType)
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
                      navigate('/login', { state: { returnTo: id ? `/news/${id}` : undefined } })
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
      <motion.article
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        className="flex-1 min-w-0"
      >
        {/* 대표 이미지 - 썸네일 정책: 정사각형(1:1) */}
        <div className="aspect-square bg-page-secondary overflow-hidden">
          <img
            src={getImageUrl()}
            alt={news.title}
            className="w-full h-full object-cover"
            loading="lazy"
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
                returnTo={id ? `/news/${id}` : undefined}
                onKakaoLogin={login}
              />
            </div>
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

              {/* 참고 글 AI 구조 분석 */}
              {(news.content || (news.url && news.url !== '#')) && (
                <div className="mb-8 border-t border-page pt-6 mt-20">
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

          {/* 하단 네비: 3줄 (이전 · 목록 · 다음) */}
          {(() => {
            const backTab: HomeTabType = fromTab && HOME_TABS.includes(fromTab) ? fromTab : (getListLabel() as HomeTabType)
            return (
              <nav className="mt-12 mb-4" aria-label="기사 네비게이션">
                <div className="border-t border-b border-page">
                  {/* 1줄: (←) 이전 "기사 제목" */}
                  <div className="border-b border-page">
                    {news.prev_article ? (
                      <Link
                        to={`/news/${news.prev_article.id}`}
                        state={fromTab ? { fromTab } : undefined}
                        className="flex items-center gap-3 px-4 py-5 group transition-colors hover:bg-page-secondary"
                      >
                        <span className="text-page-muted text-lg shrink-0 group-hover:text-page transition-colors">←</span>
                        <span className="text-[11px] text-page-muted uppercase tracking-wider shrink-0">이전</span>
                        <p className="text-sm text-page font-medium truncate group-hover:text-primary-500 transition-colors" title={news.prev_article.title}>
                          {news.prev_article.title}
                        </p>
                      </Link>
                    ) : (
                      <div className="flex items-center gap-3 px-4 py-5">
                        <span className="text-page-muted text-lg shrink-0 opacity-30">←</span>
                        <p className="text-[11px] text-page-muted">이전 기사가 없습니다</p>
                      </div>
                    )}
                  </div>

                  {/* 2줄: 목록으로 돌아가기 (매우 얇게) */}
                  <div className="border-b border-page">
                    <button
                      type="button"
                      onClick={() => navigate('/', { state: { restoreTab: backTab } })}
                      className="w-full flex items-center justify-center gap-1.5 py-2 text-page-muted hover:text-page hover:bg-page-secondary transition-colors"
                    >
                      <MaterialIcon name="menu" className="w-3.5 h-3.5" size={14} />
                      <span className="text-[10px] tracking-wider">목록으로 돌아가기</span>
                    </button>
                  </div>

                  {/* 3줄: (→) 다음 "기사 제목" */}
                  <div>
                    {news.next_article ? (
                      <Link
                        to={`/news/${news.next_article.id}`}
                        state={fromTab ? { fromTab } : undefined}
                        className="flex items-center justify-end gap-3 px-4 py-5 group transition-colors hover:bg-page-secondary"
                      >
                        <span className="text-sm text-page font-medium truncate group-hover:text-primary-500 transition-colors text-right" title={news.next_article.title}>
                          {news.next_article.title}
                        </span>
                        <span className="text-[11px] text-page-muted uppercase tracking-wider shrink-0">다음</span>
                        <span className="text-page-muted text-lg shrink-0 group-hover:text-page transition-colors">→</span>
                      </Link>
                    ) : (
                      <div className="flex items-center justify-end gap-3 px-4 py-5">
                        <p className="text-[11px] text-page-muted">다음 기사가 없습니다</p>
                        <span className="text-page-muted text-lg shrink-0 opacity-30">→</span>
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

      {error && (
        <div className="fixed bottom-4 left-4 right-4 max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto bg-red-50 text-red-600 px-4 py-3 rounded-lg text-center text-sm">
          {error}
        </div>
      )}
    </div>
  )
}
