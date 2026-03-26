import { useState, useEffect, useRef, useMemo } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
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
import { stripHtml } from '../utils/sanitizeHtml'
import { useInfiniteNewsList, usePopularNews } from '../hooks/useNews'
import { useMenuConfig } from '../hooks/useMenuConfig'
import { apiErrorMessage } from '../utils/apiErrorMessage'

interface NewsItem {
  id?: number
  news_id?: number
  title: string
  description: string
  narration?: string | null
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
  category?: string
  image_url?: string | null
}

const PER_PAGE = 20
const SCROLL_SAVE_KEY = 'home_scroll_'

function filterPlaceholder(items: NewsItem[]): NewsItem[] {
  const placeholderPhrases = ['무엇이 처음부터 왔었']
  return items.filter(
    (item: NewsItem) =>
      !placeholderPhrases.some(
        (phrase) =>
          (item.title && item.title.includes(phrase)) ||
          (item.description && item.description.includes(phrase)) ||
          (item.narration && item.narration.includes(phrase))
      )
  )
}

/** 기사를 2개씩 묶어 행 단위로 (PC 2열 그리드용). 마지막 행은 1개일 수 있음. */
function chunkBy2<T>(arr: T[]): T[][] {
  const out: T[][] = []
  for (let i = 0; i < arr.length; i += 2) {
    out.push(arr.slice(i, i + 2))
  }
  return out
}

export default function HomePage() {
  const location = useLocation()
  const navigate = useNavigate()
  const { tabs, tabLabels, tabToCategory, subCategoryToLabel, specialBadgeText } = useMenuConfig()
  const popularLabel = tabs.find((t) => t.key === 'popular')?.label ?? '인기'
  const specialLabel = tabs.find((t) => t.key === 'special')?.label ?? '특집'
  const [activeTab, setActiveTab] = useState<string>(() => {
    const s = (location.state as { restoreTab?: string } | null) ?? null
    const tab = s?.restoreTab
    return tab && tabLabels.includes(tab) ? tab : tabLabels[0] ?? '최신'
  })

  // React Query: 탭별 데이터 페칭
  const category = tabToCategory[activeTab] ?? undefined
  const isPopularTab = activeTab === popularLabel


  const {
    data: infiniteData,
    isLoading: isLoadingInfinite,
    isFetchingNextPage,
    hasNextPage,
    fetchNextPage,
  } = useInfiniteNewsList(category, PER_PAGE)

  const { data: popularData, isLoading: isLoadingPopular } = usePopularNews()

  // 뉴스 데이터 통합
  const news = useMemo(() => {
    if (isPopularTab) {
      const items = popularData?.data?.items || []
      return filterPlaceholder(items)
    }
    const pages = infiniteData?.pages || []
    const allItems = pages.flatMap((page) => page.data?.items || [])
    return filterPlaceholder(allItems)
  }, [isPopularTab, popularData, infiniteData])

  const isLoading = isPopularTab ? isLoadingPopular : isLoadingInfinite
  const isLoadingMore = isFetchingNextPage


  // 탭별 스크롤 위치 저장 (상세에서 카테고리 버튼으로 돌아왔을 때 복원용)
  useEffect(() => {
    let raf = 0
    const onScroll = () => {
      if (raf) return
      raf = requestAnimationFrame(() => {
        raf = 0
        try {
          sessionStorage.setItem(SCROLL_SAVE_KEY + activeTab, String(window.scrollY))
        } catch {
          // ignore
        }
      })
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      window.removeEventListener('scroll', onScroll)
      if (raf) cancelAnimationFrame(raf)
    }
  }, [activeTab])

  // 상세 페이지에서 카테고리 버튼으로 돌아온 경우: 로딩 완료 후 스크롤 복원 후 state 제거
  const didRestoreRef = useRef(false)
  useEffect(() => {
    const restoreTab = (location.state as { restoreTab?: string } | null)?.restoreTab
    if (!restoreTab) {
      didRestoreRef.current = false
      return
    }
    if (isLoading || didRestoreRef.current) return
    didRestoreRef.current = true
    const raw = sessionStorage.getItem(SCROLL_SAVE_KEY + restoreTab)
    const y = raw ? parseInt(raw, 10) : 0
    if (!Number.isFinite(y) || y <= 0) {
      navigate('/', { replace: true, state: {} })
      return
    }
    const id = requestAnimationFrame(() => {
      window.scrollTo(0, y)
      navigate('/', { replace: true, state: {} })
    })
    return () => cancelAnimationFrame(id)
  }, [isLoading, location.state, navigate])

  const loadMore = () => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage()
    }
  }

  const handleTabClick = (tab: string) => {
    if (tab === activeTab) return
    window.scrollTo(0, 0)
    setActiveTab(tab)
  }

  return (
    <div className="min-h-screen bg-page pb-8">
      {/* 탭 네비게이션 - 이미지처럼 연한 배경으로 본문과 구분 */}
      <div className="sticky top-14 z-[41] bg-page-secondary border-b border-page overflow-visible">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-8 lg:px-12 xl:px-16">
          <div className="flex">
            {tabLabels.map((tab) => (
              <button
                key={tab}
                onClick={() => handleTabClick(tab)}
                className={`flex-1 py-3 text-sm font-medium transition-colors relative flex flex-col items-center justify-center gap-0 ${
                  activeTab === tab
                    ? 'text-page'
                    : 'text-page-secondary hover:text-page'
                }`}
              >
                {tab === specialLabel ? (
                  <span className="flex flex-col items-center justify-center gap-0.5">
                    <span className="rounded-full bg-primary-500 px-1.5 py-0.5 text-[8px] font-medium leading-none text-white whitespace-nowrap">
                      {specialBadgeText}
                    </span>
                    <span>{tab}</span>
                  </span>
                ) : (
                  tab
                )}
                {activeTab === tab && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-[var(--text-primary)]" />
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* 기사 목록 */}
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-8 lg:px-12 xl:px-16 pt-4 md:pt-5 bg-page">
        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <LoadingSpinner size="large" />
          </div>
        ) : news.length === 0 ? (
          <div className="text-center py-20 text-page-secondary">
            기사가 없습니다.
          </div>
        ) : (
          <>
            <div className="bg-page">
              <div className="lg:hidden">
                {news.map((item, i) => (
                  <div key={item.id ?? i}>
                    <ArticleCard article={item} activeTab={activeTab} subCategoryToLabel={subCategoryToLabel} />
                    {i < news.length - 1 && (
                      <div className="h-2 bg-page-secondary" aria-hidden />
                    )}
                  </div>
                ))}
              </div>
              <div className="hidden lg:block">
                {chunkBy2(news).map((row, rowIndex) => (
                  <div key={rowIndex}>
                    <div className="grid grid-cols-2 gap-x-12">
                      {row.map((item, idx) => (
                        <ArticleCard key={item.id ?? rowIndex * 2 + idx} article={item} activeTab={activeTab} subCategoryToLabel={subCategoryToLabel} />
                      ))}
                    </div>
                    {rowIndex < chunkBy2(news).length - 1 && (
                      <div className="h-2 bg-page-secondary" aria-hidden />
                    )}
                  </div>
                ))}
              </div>
            </div>
            {hasNextPage && !isPopularTab && (
              <div className="flex justify-center pt-8 pb-4">
                <button
                  type="button"
                  onClick={loadMore}
                  disabled={isLoadingMore}
                  className="text-page-secondary hover:text-page underline-offset-2 hover:underline font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoadingMore ? '불러오는 중...' : '더 보기'}
                </button>
              </div>
            )}
          </>
        )}
      </div>


    </div>
  )
}

// 기사 카드 - 왼쪽 텍스트 + 오른쪽 이미지. 기사는 흰 배경, 기사 사이 간격에 회색 배경이 비침.
function ArticleCard({ article, activeTab, subCategoryToLabel }: { article: NewsItem; activeTab: string; subCategoryToLabel: Record<string, string> }) {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [isBookmarking, setIsBookmarking] = useState(false)

  const imageUrl = article.image_url || getPlaceholderImageUrl(
    { id: article.id, title: article.title, description: article.description, published_at: article.published_at, category: article.category },
    200,
    200
  )

  // 날짜 포맷팅 (표시용: display_date 우선, docs/DATE_POLICY.md)
  const formatDate = () => {
    if (article.time_ago) return article.time_ago
    const dateStr = (article as { display_date?: string }).display_date ?? article.published_at ?? (article as { created_at?: string }).created_at
    if (dateStr) {
      const date = new Date(dateStr)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return ''
  }

  // 카테고리 라벨: 하위만 표시 (subCategoryToLabel 또는 직접입력값 그대로)
  const getCategoryLabel = () => {
    if (article.category) return subCategoryToLabel[article.category] ?? article.category
    if (article.source === 'Admin') return 'the gist.'
    return formatSourceDisplayName(article.source) || 'the gist.'
  }

  // 오디오 재생: 기사 상세를 먼저 가져와서 내레이션 + The Gist's Critique 전부 읽기
  const handlePlayAudio = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const newsId = article.id ?? article.news_id
    if (!newsId) return

    addAudioItem({
      id: Number(newsId),
      title: article.title,
      description: article.description ?? null,
      source: article.source ?? null,
      category: article.category ?? null,
      published_at: article.published_at ?? null,
    })

    try {
      const res = await newsApi.getDetail(Number(newsId))
      const detail = res.data?.data
      if (detail) {
        const originalTitle = (detail.original_title && String(detail.original_title).trim()) || extractTitleFromUrl(detail.url) || '원문'
        const displayDate = (detail as { display_date?: string }).display_date ?? detail.published_at ?? detail.created_at
        const dateStr = displayDate
          ? `${new Date(displayDate).getFullYear()}년 ${new Date(displayDate).getMonth() + 1}월 ${new Date(displayDate).getDate()}일`
          : ''
        const rawSource = (detail.original_source && String(detail.original_source).trim()) || (detail.source === 'Admin' ? 'The Gist' : detail.source || 'The Gist')
        const sourceDisplay = formatSourceDisplayName(rawSource) || 'The Gist'
        const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
        const mainContent = stripHtml(detail.narration || detail.content || detail.description || article.description || '')
        const critiquePart = detail.why_important ? stripHtml(detail.why_important) : ''
        const img = detail.image_url || article.image_url || ''
        openAndPlay(detail.title, editorialLine, mainContent, critiquePart, 1.0, img, Number(newsId))
        return
      }
    } catch { /* fallback */ }

    // fallback: 상세 못 가져오면 URL 기반으로 매체 설명 구성 (원칙 준수)
    const text = `${article.title}. ${article.description || ''}`.trim()
    if (text) {
      const url = (article as { url?: string; source_url?: string }).url || (article as { source_url?: string }).source_url
      const originalTitle = extractTitleFromUrl(url) || '원문'
      const sourceDisplay = formatSourceDisplayName(article.source) || 'the gist.'
      const displayDate = (article as { display_date?: string }).display_date ?? article.published_at
      const dateStr = displayDate
        ? `${new Date(displayDate).getFullYear()}년 ${new Date(displayDate).getMonth() + 1}월 ${new Date(displayDate).getDate()}일`
        : ''
      const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
      openAndPlay(article.title, editorialLine, text, '', 1.0, undefined, Number(newsId))
    }
  }

  const shareWebUrl = `${window.location.origin}/news/${article.id ?? article.news_id}`

  // 즐겨찾기 핸들러
  const handleBookmark = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    
    const newsId = article.id ?? article.news_id
    if (!newsId) {
      alert('이 기사는 즐겨찾기에 추가할 수 없습니다.')
      return
    }

    if (!isAuthenticated) {
      if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
        navigate('/login', { state: { returnTo: window.location.pathname } })
      }
      return
    }

    setIsBookmarking(true)
    try {
      if (isBookmarked) {
        await newsApi.removeBookmark(Number(newsId))
        setIsBookmarked(false)
      } else {
        await newsApi.bookmark(Number(newsId))
        setIsBookmarked(true)
      }
    } catch (err: unknown) {
      alert(apiErrorMessage(err, '즐겨찾기 처리에 실패했습니다.'))
    } finally {
      setIsBookmarking(false)
    }
  }

  const newsId = article.id ?? article.news_id
  const detailUrl = `/news/${newsId || ''}`

  return (
    <article className="bg-page py-5">
      {/* 상단: 썸네일 정사각형(1:1) 정책 통일 */}
      <div className="grid grid-cols-[1fr_auto] items-start gap-4">
        <div className="min-w-0 flex flex-col">
          <Link to={detailUrl} state={{ fromTab: activeTab }} className="flex flex-col justify-center">
            <h2 className="text-lg font-bold text-page leading-snug mb-1.5 line-clamp-2 break-keep-ko-mobile">
              {article.title}
            </h2>
            {(article.narration || article.description) && (
              <p className="text-xs text-page-secondary leading-relaxed line-clamp-3 break-keep-ko-mobile">
                {stripHtml(article.narration?.trim() || article.description)}
              </p>
            )}
          </Link>
        </div>
        <Link to={detailUrl} state={{ fromTab: activeTab }} className="w-28 h-28 flex-shrink-0 rounded-none overflow-hidden bg-page-secondary block aspect-square">
          <img
            src={imageUrl}
            alt={article.title}
            width={112}
            height={112}
            className="w-full h-full object-cover"
            loading="lazy"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                { id: article.id, title: article.title, description: article.description, published_at: article.published_at, category: article.category, url: article.url, source: article.source },
                200,
                200
              )
            }}
          />
        </Link>
      </div>
      {/* 구분선 아래: 매체 | 날짜(왼쪽) / 아이콘 3개만(오른쪽, 아이콘 옆 구분자 없음) */}
      <div className="flex items-center justify-between pt-2 mt-2 border-t border-page">
        <Link to={detailUrl} state={{ fromTab: activeTab }} className="flex items-center gap-1.5 text-xs shrink-0">
          <span className="font-medium text-primary-500">{getCategoryLabel()}</span>
          <span className="text-page-muted">|</span>
          <span className="text-page-secondary">{formatDate()}</span>
        </Link>
        {/* 메인 페이지: Play · Share · Bookmark 아이콘 크기 완전 동일 (w-5 h-5) */}
        <div className="flex items-center gap-2 shrink-0" role="group" aria-label="기사 액션">
          <button
            type="button"
            onClick={handlePlayAudio}
            className="p-1 transition-colors text-page-secondary hover:text-page"
            title="음성으로 듣기"
            aria-label="재생"
          >
            <MaterialIcon name="headphones" className="w-5 h-5 shrink-0" size={20} />
          </button>
          <ShareMenu
            title={article.title}
            description={article.description || ''}
            imageUrl={imageUrl}
            webUrl={shareWebUrl}
            className="text-page-secondary hover:text-page"
            titleAttr="공유하기"
            iconClassName="w-5 h-5"
          />
          <button
            type="button"
            onClick={handleBookmark}
            disabled={isBookmarking}
            className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-page-secondary hover:text-page'} ${isBookmarking ? 'opacity-60 cursor-wait' : ''}`}
            title="즐겨찾기"
            aria-label={isBookmarked ? '즐겨찾기 해제' : '즐겨찾기 추가'}
          >
            {isBookmarking ? (
              <span className="inline-block w-5 h-5 shrink-0 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            ) : isBookmarked ? (
              <MaterialIcon name="bookmark" filled className="w-5 h-5 shrink-0 text-primary-500" size={20} />
            ) : (
              <MaterialIcon name="bookmark_border" className="w-5 h-5 shrink-0" size={20} />
            )}
          </button>
        </div>
      </div>
    </article>
  )
}

