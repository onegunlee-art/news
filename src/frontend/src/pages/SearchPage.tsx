import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
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
import { queryKeys } from '../lib/queryClient'

/** 기사 카드/본문에 표시할 하위 카테고리 라벨 (홈과 동일) */
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

interface NewsItem {
  id?: number
  title: string
  description: string
  url: string
  source: string | null
  display_date?: string | null
  published_at: string | null
  time_ago?: string
  category?: string
  image_url?: string | null
  original_source?: string | null
  narration?: string | null
}

export default function SearchPage() {
  const [searchParams] = useSearchParams()
  const q = searchParams.get('q')?.trim() ?? ''

  const { data, isLoading, isFetched } = useQuery({
    queryKey: queryKeys.news.search(q),
    queryFn: async () => {
      const res = await newsApi.search(q, 1, 30)
      if (res.data.success && res.data.data?.items) {
        return res.data.data.items as NewsItem[]
      }
      return []
    },
    enabled: q.length >= 1,
    staleTime: 1000 * 60 * 2, // 2분 캐시
  })

  const news = data ?? []
  const searched = isFetched && q.length >= 1

  return (
    <div className="min-h-screen bg-page pb-8">
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-6 md:pt-8">
        {/* 검색 결과 헤더 */}
        <div className="mb-6">
          <h1 className="text-xl md:text-2xl font-semibold text-page">
            {q ? `"${q}" 검색 결과` : '키워드 검색'}
          </h1>
          {q && (
            <p className="text-sm text-page-secondary mt-1">
              {!isLoading && searched && `${news.length}건의 기사`}
            </p>
          )}
        </div>

        {!q ? (
          <div className="text-center py-16 text-page-secondary">
            <p className="mb-2">상단 검색 아이콘을 눌러 검색어를 입력하세요.</p>
            <p className="text-sm">제목·내용·요약에서 키워드로 검색됩니다.</p>
          </div>
        ) : isLoading ? (
          <div className="flex justify-center items-center py-20">
            <LoadingSpinner size="large" />
          </div>
        ) : news.length === 0 ? (
          <div className="text-center py-20 text-page-secondary">
            검색 결과가 없습니다. 다른 키워드로 시도해 보세요.
          </div>
        ) : (
          <div className="space-y-0 lg:grid lg:grid-cols-2 lg:gap-x-12 lg:gap-y-0 lg:border-t lg:border-page">
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
    const dateStr = article.display_date ?? article.published_at
    if (dateStr) {
      const date = new Date(dateStr)
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

  // 카테고리 라벨: 하위만 표시 (홈과 동일)
  const getCategoryLabel = () => {
    if (article.category) return subCategoryToLabel[article.category] ?? article.category
    if (article.source === 'Admin') return 'The Gist'
    return formatSourceDisplayName(article.source) || 'The Gist'
  }

  // 오디오 재생: 기사 상세를 먼저 가져와서 내레이션 + The Gist's Critique 전부 읽기
  const handlePlayAudio = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const newsId = article.id
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
      const sourceDisplay = getSourceName()
      const displayDate = article.display_date ?? article.published_at
      const dateStr = displayDate
        ? `${new Date(displayDate).getFullYear()}년 ${new Date(displayDate).getMonth() + 1}월 ${new Date(displayDate).getDate()}일`
        : ''
      const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
      openAndPlay(article.title, editorialLine, text, '', 1.0, undefined, Number(newsId))
    }
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
      if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) navigate('/login', { state: { returnTo: window.location.pathname } })
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
    <article className="bg-page py-5">
      <div className="grid grid-cols-[1fr_auto] items-start gap-4">
        <div className="min-w-0 flex flex-col">
          <Link to={detailUrl} state={{ fromTab: '최신' }} className="flex flex-col justify-center">
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
        <Link to={detailUrl} state={{ fromTab: '최신' }} className="w-28 h-28 flex-shrink-0 rounded-none overflow-hidden bg-page-secondary block aspect-square">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover"
            loading="lazy"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
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
        </Link>
      </div>
      <div className="flex items-center justify-between pt-2 mt-2 border-t border-page">
        <Link to={detailUrl} state={{ fromTab: '최신' }} className="flex items-center gap-1.5 text-xs shrink-0">
          <span className="font-medium text-primary-500">{getCategoryLabel()}</span>
          <span className="text-page-muted">|</span>
          <span className="text-page-secondary">{formatDate()}</span>
        </Link>
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

