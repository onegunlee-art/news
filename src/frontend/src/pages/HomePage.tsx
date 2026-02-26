import { useState, useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { PlayIcon, BookmarkIcon } from '@heroicons/react/24/outline'
import { BookmarkIcon as BookmarkIconSolid } from '@heroicons/react/24/solid'
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

interface NewsItem {
  id?: number
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

type TabType = '최신' | '외교' | '경제' | '특집' | '인기'

const tabToCategory: Record<TabType, string | null> = {
  '최신': null,
  '외교': 'diplomacy',
  '경제': 'economy',
  '특집': 'special',
  '인기': null,
}

/** 기사 카드/본문에 표시할 하위 카테고리 라벨 (8개 + 직접입력은 그대로) */
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

const PER_PAGE = 20

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
  const [news, setNews] = useState<NewsItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isLoadingMore, setIsLoadingMore] = useState(false)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [activeTab, setActiveTab] = useState<TabType>('최신')

  useEffect(() => {
    setPage(1)
    fetchNews(1, false)
  }, [activeTab])

  const fetchNews = async (pageNum: number, append: boolean) => {
    if (append) {
      setIsLoadingMore(true)
    } else {
      setIsLoading(true)
    }
    try {
      const category = tabToCategory[activeTab]
      const response = await newsApi.getList(pageNum, PER_PAGE, category || undefined)
      if (response.data.success) {
        const items = response.data.data.items || []
        const filtered = filterPlaceholder(items)
        const pagination = response.data.data.pagination || {}
        const total = pagination.total_pages ?? 1
        setTotalPages(total)
        setNews((prev) => (append ? [...prev, ...filtered] : filtered))
      }
    } catch (error) {
      console.error('Failed to fetch news:', error)
    } finally {
      setIsLoading(false)
      setIsLoadingMore(false)
    }
  }

  const loadMore = () => {
    const nextPage = page + 1
    setPage(nextPage)
    fetchNews(nextPage, true)
  }

  const tabs: TabType[] = ['최신', '외교', '경제', '특집', '인기']

  return (
    <div className="min-h-screen bg-page pb-8">
      {/* 탭 네비게이션 - 이미지처럼 연한 배경으로 본문과 구분 */}
      <div className="apply-grayscale sticky top-14 bg-page-secondary z-30 border-b border-page">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-8 lg:px-12 xl:px-16">
          <div className="flex">
            {tabs.map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
                  activeTab === tab
                    ? 'text-page'
                    : 'text-page-secondary hover:text-page'
                }`}
              >
                {tab}
                {activeTab === tab && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-[var(--text-primary)]" />
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* 기사 목록 - 메뉴~첫기사는 흰 배경, 기사와 기사 사이에만 회색 띠(이미지와 동일) */}
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
              {/* 모바일: 기사 1개씩, 기사와 기사 사이에만 회색 */}
              <div className="lg:hidden">
                {news.map((item, i) => (
                  <div key={item.id ?? i}>
                    <ArticleCard article={item} />
                    {i < news.length - 1 && (
                      <div className="h-2 bg-page-secondary" aria-hidden />
                    )}
                  </div>
                ))}
              </div>
              {/* PC(lg~): 기사 2개씩 한 행, 행과 행 사이에만 회색 */}
              <div className="hidden lg:block">
                {chunkBy2(news).map((row, rowIndex) => (
                  <div key={rowIndex}>
                    <div className="grid grid-cols-2 gap-x-12">
                      {row.map((item, idx) => (
                        <ArticleCard key={item.id ?? rowIndex * 2 + idx} article={item} />
                      ))}
                    </div>
                    {rowIndex < chunkBy2(news).length - 1 && (
                      <div className="h-2 bg-page-secondary" aria-hidden />
                    )}
                  </div>
                ))}
              </div>
            </div>
            {page < totalPages && (
              <div className="apply-grayscale flex justify-center pt-8 pb-4">
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
function ArticleCard({ article }: { article: NewsItem }) {
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

  // 날짜 포맷팅
  const formatDate = () => {
    if (article.time_ago) return article.time_ago
    if (article.published_at) {
      const date = new Date(article.published_at)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return ''
  }

  // 카테고리 라벨: 하위만 표시 (subCategoryToLabel 또는 직접입력값 그대로)
  const getCategoryLabel = () => {
    if (article.category) return subCategoryToLabel[article.category] ?? article.category
    if (article.source === 'Admin') return 'The Gist'
    return formatSourceDisplayName(article.source) || 'The Gist'
  }

  // 오디오 재생: 기사 상세를 먼저 가져와서 내레이션 + The Gist's Critique 전부 읽기
  const handlePlayAudio = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const newsId = article.id ?? (article as any).news_id
    if (!newsId) return

    addAudioItem({
      id: Number(newsId),
      title: article.title,
      description: article.description ?? null,
      source: article.source ?? null,
    })

    try {
      const res = await newsApi.getDetail(Number(newsId))
      const detail = res.data?.data
      if (detail) {
        const originalTitle = (detail.original_title && String(detail.original_title).trim()) || extractTitleFromUrl(detail.url) || '원문'
        const dateStr = detail.published_at
          ? `${new Date(detail.published_at).getFullYear()}년 ${new Date(detail.published_at).getMonth() + 1}월 ${new Date(detail.published_at).getDate()}일`
          : (detail.updated_at || detail.created_at)
            ? `${new Date(detail.updated_at || detail.created_at).getFullYear()}년 ${new Date(detail.updated_at || detail.created_at).getMonth() + 1}월 ${new Date(detail.updated_at || detail.created_at).getDate()}일`
            : ''
        const rawSource = (detail.original_source && String(detail.original_source).trim()) || (detail.source === 'Admin' ? 'The Gist' : detail.source || 'The Gist')
        const sourceDisplay = formatSourceDisplayName(rawSource) || 'The Gist'
        const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
        const mainContent = stripHtml(detail.narration || detail.content || detail.description || article.description || '')
        const critiquePart = detail.why_important ? `The Gist's Critique. ${stripHtml(detail.why_important)}` : ''
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
      const sourceDisplay = formatSourceDisplayName(article.source) || 'The Gist'
      const dateStr = article.published_at
        ? `${new Date(article.published_at).getFullYear()}년 ${new Date(article.published_at).getMonth() + 1}월 ${new Date(article.published_at).getDate()}일`
        : ''
      const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
      openAndPlay(article.title, editorialLine, text, '', 1.0, undefined, Number(newsId))
    }
  }

  const shareWebUrl = `${window.location.origin}/news/${article.id ?? (article as any).news_id}`

  // 즐겨찾기 핸들러
  const handleBookmark = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    
    const newsId = article.id ?? (article as any).news_id
    if (!newsId) {
      alert('이 기사는 즐겨찾기에 추가할 수 없습니다.')
      return
    }

    if (!isAuthenticated) {
      if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
        navigate('/login')
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
    } catch (err: any) {
      const msg = err.response?.data?.message ?? err.message ?? '즐겨찾기 처리에 실패했습니다.'
      alert(msg)
    } finally {
      setIsBookmarking(false)
    }
  }

  const newsId = article.id ?? (article as any).news_id
  const detailUrl = `/news/${newsId || ''}`

  return (
    <article className="bg-page py-5">
      {/* 상단: 글 블록과 썸네일 높이 동일하게 맞춤 */}
      <div className="grid grid-cols-[1fr_auto] items-stretch gap-4">
        <div className="apply-grayscale min-w-0 flex flex-col">
          <Link to={detailUrl} className="flex flex-col min-h-[7rem] justify-center">
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
        <Link to={detailUrl} className="w-28 min-h-[7rem] flex-shrink-0 rounded-none overflow-hidden bg-page-secondary block self-stretch">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full min-h-[7rem] object-cover"
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
      <div className="apply-grayscale flex items-center justify-between pt-2 mt-2 border-t border-page">
        <Link to={detailUrl} className="flex items-center gap-1.5 text-xs shrink-0">
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
            <PlayIcon className="w-5 h-5 shrink-0" strokeWidth={1.5} />
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
              <BookmarkIconSolid className="w-5 h-5 shrink-0 text-primary-500" />
            ) : (
              <BookmarkIcon className="w-5 h-5 shrink-0" strokeWidth={1.5} />
            )}
          </button>
        </div>
      </div>
    </article>
  )
}

