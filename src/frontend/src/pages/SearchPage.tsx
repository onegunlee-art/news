import { useState, useEffect } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { newsApi } from '../services/api'
import ShareMenu from '../components/Common/ShareMenu'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { formatSourceDisplayName } from '../utils/formatSource'

interface NewsItem {
  id?: number
  title: string
  description: string
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
  category?: string
  image_url?: string | null
  original_source?: string | null
}

export default function SearchPage() {
  const [searchParams] = useSearchParams()
  const q = searchParams.get('q')?.trim() ?? ''
  const [news, setNews] = useState<NewsItem[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [searched, setSearched] = useState(false)

  useEffect(() => {
    if (!q) {
      setNews([])
      setSearched(false)
      return
    }
    setSearched(true)
    setIsLoading(true)
    newsApi
      .search(q, 1, 30)
      .then((res) => {
        if (res.data.success && res.data.data?.items) {
          setNews(res.data.data.items)
        } else {
          setNews([])
        }
      })
      .catch(() => setNews([]))
      .finally(() => setIsLoading(false))
  }, [q])

  return (
    <div className="min-h-screen bg-white pb-20 md:pb-8">
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-6 md:pt-8">
        {/* 검색 결과 헤더 */}
        <div className="mb-6">
          <h1 className="text-xl md:text-2xl font-semibold text-gray-900">
            {q ? `"${q}" 검색 결과` : '키워드 검색'}
          </h1>
          {q && (
            <p className="text-sm text-gray-500 mt-1">
              {!isLoading && searched && `${news.length}건의 기사`}
            </p>
          )}
        </div>

        {!q ? (
          <div className="text-center py-16 text-gray-500">
            <p className="mb-2">상단 검색 아이콘을 눌러 검색어를 입력하세요.</p>
            <p className="text-sm">제목·내용·요약에서 키워드로 검색됩니다.</p>
          </div>
        ) : isLoading ? (
          <div className="flex justify-center items-center py-20">
            <LoadingSpinner size="large" />
          </div>
        ) : news.length === 0 ? (
          <div className="text-center py-20 text-gray-500">
            검색 결과가 없습니다. 다른 키워드로 시도해 보세요.
          </div>
        ) : (
          <div className="space-y-0 lg:grid lg:grid-cols-2 lg:gap-x-12 lg:gap-y-0 lg:border-t lg:border-gray-100">
            {news.map((item, index) => (
              <SearchArticleCard key={item.id ?? index} article={item} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

function SearchArticleCard({ article }: { article: NewsItem }) {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [isBookmarking, setIsBookmarking] = useState(false)

  const imageUrl =
    article.image_url ||
    getPlaceholderImageUrl(
      {
        id: article.id,
        title: article.title,
        description: article.description,
        published_at: article.published_at,
        category: article.category,
      },
      200,
      200
    )

  const formatDate = () => {
    if (article.time_ago) return article.time_ago
    if (article.published_at) {
      const date = new Date(article.published_at)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return ''
  }

  const getSourceName = () => {
    let raw: string
    if (article.original_source && String(article.original_source).trim()) raw = article.original_source
    else if (article.source === 'Admin') return 'The Gist'
    else raw = article.source || 'The Gist'
    return formatSourceDisplayName(raw) || 'The Gist'
  }

  const handlePlayAudio = (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (!('speechSynthesis' in window)) {
      alert('이 브라우저는 음성 재생을 지원하지 않습니다.')
      return
    }
    const text = `${article.title}. ${article.description || ''}`.trim()
    if (!text) return
    const idForList = article.id
    if (idForList) {
      addAudioItem({
        id: Number(idForList),
        title: article.title,
        description: article.description ?? null,
        source: article.source ?? null,
      })
    }
    openAndPlay(article.title, text, 1.0)
  }

  const shareWebUrl = `${window.location.origin}/news/${article.id}`

  const handleBookmark = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const newsId = article.id
    if (!newsId) {
      alert('이 기사는 즐겨찾기에 추가할 수 없습니다.')
      return
    }
    if (!isAuthenticated) {
      if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) navigate('/login')
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
      alert(err.response?.data?.message ?? err.message ?? '즐겨찾기 처리에 실패했습니다.')
    } finally {
      setIsBookmarking(false)
    }
  }

  const detailUrl = `/news/${article.id ?? ''}`

  return (
    <article className="flex gap-4 py-5 border-b border-gray-100 last:border-0 lg:border-b lg:border-gray-100">
      <Link to={detailUrl} className="flex-1 min-w-0 flex gap-4">
        <div className="flex-1 min-w-0">
          <h2 className="text-lg font-bold text-gray-900 leading-snug mb-1.5 line-clamp-2">{article.title}</h2>
          {article.description && (
            <p className="text-sm text-gray-600 leading-relaxed mb-2 line-clamp-2">{article.description}</p>
          )}
          <div className="flex items-center gap-2 text-xs text-gray-500">
            <span className="font-medium text-primary-500">{getSourceName()}</span>
            <span className="text-gray-300">/</span>
            <span>{formatDate()}</span>
          </div>
        </div>
        <div className="w-24 h-24 flex-shrink-0">
          <div className="w-full h-full bg-gray-100 rounded-lg overflow-hidden">
            <img
              src={imageUrl}
              alt={article.title}
              className="w-full h-full object-cover"
              onError={(e) => {
                ;(e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                  {
                    id: article.id,
                    title: article.title,
                    description: article.description,
                    published_at: article.published_at,
                    category: article.category,
                    url: article.url,
                    source: article.source,
                  },
                  200,
                  200
                )
              }}
            />
          </div>
        </div>
      </Link>
      <div className="flex flex-col justify-between py-1" role="group" aria-label="기사 액션">
        <button
          type="button"
          onClick={handlePlayAudio}
          className="p-1 text-gray-300 hover:text-gray-500 transition-colors"
          title="음성으로 듣기"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 18v-6a9 9 0 0118 0v6M3 18h2a2 2 0 002-2v-4a2 2 0 00-2-2H3v8zm14 0h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2v8z" />
          </svg>
        </button>
        <ShareMenu
          title={article.title}
          description={article.description || ''}
          imageUrl={imageUrl}
          webUrl={shareWebUrl}
          className="text-gray-300 hover:text-gray-500"
          titleAttr="공유하기"
        />
        <button
          type="button"
          onClick={handleBookmark}
          disabled={isBookmarking}
          className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-gray-300 hover:text-gray-500'} ${isBookmarking ? 'opacity-60 cursor-wait' : ''}`}
          title="즐겨찾기"
        >
          {isBookmarking ? (
            <span className="inline-block w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
          ) : (
            <svg className="w-5 h-5" fill={isBookmarked ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
          )}
        </button>
      </div>
    </article>
  )
}

