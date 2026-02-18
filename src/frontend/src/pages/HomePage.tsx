import { useState, useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { newsApi } from '../services/api'
import ShareMenu from '../components/Common/ShareMenu'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { formatSourceDisplayName } from '../utils/formatSource'
import { extractTitleFromUrl } from '../utils/extractTitleFromUrl'

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

type TabType = '최신' | '외교' | '금융' | '인기'

const tabToCategory: Record<TabType, string | null> = {
  '최신': null,
  '외교': 'diplomacy',
  '금융': 'economy',
  '인기': null,
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

  const tabs: TabType[] = ['최신', '외교', '금융', '인기']

  return (
    <div className="min-h-screen bg-white pb-20 md:pb-8">
      {/* 탭 네비게이션 - PC: Foreign Affairs 스타일 넓은 레이아웃 */}
      <div className="sticky top-14 bg-white z-30 border-b border-gray-200">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6">
          <div className="flex">
            {tabs.map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
                  activeTab === tab
                    ? 'text-gray-900'
                    : 'text-gray-500 hover:text-gray-900'
                }`}
              >
                {tab}
                {activeTab === tab && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-gray-900" />
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* 기사 목록 - 모바일: 1열 / PC: Foreign Affairs 스타일 넓은 2열 */}
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-6 md:pt-8">
        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <LoadingSpinner size="large" />
          </div>
        ) : news.length === 0 ? (
          <div className="text-center py-20 text-gray-500">
            기사가 없습니다.
          </div>
        ) : (
          <>
            <div className="space-y-0 lg:grid lg:grid-cols-2 lg:gap-x-12 lg:gap-y-0 lg:border-t lg:border-gray-100">
              {news.map((item, index) => (
                <ArticleCard key={item.id || index} article={item} />
              ))}
            </div>
            {page < totalPages && (
              <div className="flex justify-center pt-8 pb-4">
                <button
                  type="button"
                  onClick={loadMore}
                  disabled={isLoadingMore}
                  className="px-8 py-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoadingMore ? '불러오는 중...' : '더 보기'}
                </button>
              </div>
            )}
          </>
        )}
      </div>


      {/* 하단 네비게이션 - 모바일만 표시, PC는 헤더에 링크 */}
      <BottomNav />
    </div>
  )
}

// 기사 카드 - 왼쪽 텍스트 + 오른쪽 이미지 (기사별 고유 이미지, 중복 없음)
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

  // 소스 이름 매핑
  const getSourceName = () => {
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
        const titleForMeta = (detail.original_title && String(detail.original_title).trim()) || extractTitleFromUrl(detail.url) || 'Article'
        const dateStr = detail.published_at
          ? `${new Date(detail.published_at).getFullYear()}년 ${new Date(detail.published_at).getMonth() + 1}월 ${new Date(detail.published_at).getDate()}일`
          : (detail.updated_at || detail.created_at)
            ? `${new Date(detail.updated_at || detail.created_at).getFullYear()}년 ${new Date(detail.updated_at || detail.created_at).getMonth() + 1}월 ${new Date(detail.updated_at || detail.created_at).getDate()}일`
            : ''
        const rawSource = (detail.original_source && String(detail.original_source).trim()) || (detail.source === 'Admin' ? 'The Gist' : detail.source || 'The Gist')
        const sourceDisplay = formatSourceDisplayName(rawSource) || 'The Gist'
        const editorialLine = dateStr
          ? `${dateStr}자 ${sourceDisplay} 저널의 "${titleForMeta}"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.`
          : `${sourceDisplay} 저널의 "${titleForMeta}"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.`
        const mainContent = detail.narration || detail.content || detail.description || article.description || ''
        const critiquePart = detail.why_important ? `The Gist's Critique. ${detail.why_important}` : ''
        const img = detail.image_url || article.image_url || ''
        openAndPlay(titleForMeta, editorialLine, mainContent, critiquePart, 1.0, img, Number(newsId))
        return
      }
    } catch { /* fallback */ }

    // fallback: 상세 못 가져오면 기존 방식 (매체 설명 없음)
    const text = `${article.title}. ${article.description || ''}`.trim()
    if (text) openAndPlay(article.title, '', text, '', 1.0, undefined, Number(newsId))
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
    <article className="flex gap-4 py-5 border-b border-gray-100 last:border-0 lg:border-b lg:border-gray-100">
      {/* 클릭 시 상세로 이동하는 영역만 Link */}
      <Link to={detailUrl} className="flex-1 min-w-0 flex gap-4">
        {/* 왼쪽 - 텍스트 콘텐츠 */}
        <div className="flex-1 min-w-0">
          {/* 제목 - Foreign Affairs 스타일: 굵은 헤드라인 */}
          <h2 className="text-lg font-bold text-gray-900 leading-snug mb-1.5 line-clamp-2">
            {article.title}
          </h2>
          
          {/* 기사 내용 - 내레이션 우선, 없으면 요약 */}
          {(article.narration || article.description) && (
            <p className="text-xs text-gray-600 leading-relaxed mb-2 line-clamp-3">
              {article.narration?.trim() || article.description}
            </p>
          )}
          
          {/* 소스 및 날짜 - Foreign Affairs 스타일: 저자/출처 라인 */}
          <div className="flex items-center gap-2 text-xs">
            <span className="font-medium text-primary-500">{getSourceName()}</span>
            <span className="text-gray-300">/</span>
            <span className="text-gray-400">{formatDate()}</span>
          </div>
        </div>

        {/* 오른쪽 - 이미지 */}
        <div className="w-24 h-24 flex-shrink-0">
          <div className="w-full h-full bg-gray-100 rounded-lg overflow-hidden">
            <img
              src={imageUrl}
              alt={article.title}
              className="w-full h-full object-cover"
              onError={(e) => {
                (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                  { id: article.id, title: article.title, description: article.description, published_at: article.published_at, category: article.category, url: article.url, source: article.source },
                  200,
                  200
                )
              }}
            />
          </div>
        </div>
      </Link>

      {/* 액션 버튼들 - Link 밖에 두어 클릭 시 상세 이동 안 함 */}
      <div className="flex flex-col justify-between py-1" role="group" aria-label="기사 액션">
          {/* 오디오 재생 버튼 */}
          <button
            type="button"
            onClick={handlePlayAudio}
            className="p-1 transition-colors text-gray-400 hover:text-gray-600"
            title="음성으로 듣기"
            aria-label="재생"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 18v-6a9 9 0 0118 0v6M3 18h2a2 2 0 002-2v-4a2 2 0 00-2-2H3v8zm14 0h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2v8z" />
            </svg>
          </button>
          
          {/* 공유하기 메뉴 */}
          <ShareMenu
            title={article.title}
            description={article.description || ''}
            imageUrl={imageUrl}
            webUrl={shareWebUrl}
            className="text-gray-400 hover:text-gray-600"
            titleAttr="공유하기"
          />
          
          {/* 즐겨찾기 버튼 */}
          <button
            type="button"
            onClick={handleBookmark}
            disabled={isBookmarking}
            className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-gray-400 hover:text-gray-600'} ${isBookmarking ? 'opacity-60 cursor-wait' : ''}`}
            title="즐겨찾기"
            aria-label={isBookmarked ? '즐겨찾기 해제' : '즐겨찾기 추가'}
          >
            {isBookmarking ? (
              <span className="inline-block w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            ) : (
              <svg className="w-5 h-5" fill={isBookmarked ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
            )}
          </button>
        </div>
    </article>
  )
}

// 하단 네비게이션 - 모바일만 표시, PC는 헤더에 최신/즐겨찾기/설정 링크
function BottomNav() {
  return (
    <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-40">
      <div className="max-w-lg mx-auto px-4">
        <div className="flex items-center justify-around h-16">
          {/* 최신 */}
          <Link to="/" className="flex flex-col items-center gap-1 text-gray-900">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span className="text-xs font-medium">최신</span>
          </Link>

          {/* My Page */}
          <Link to="/profile" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
            <span className="text-xs">My Page</span>
          </Link>

          {/* 설정 */}
          <Link to="/settings" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span className="text-xs">설정</span>
          </Link>
        </div>
      </div>
    </nav>
  )
}
