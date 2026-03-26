import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import MaterialIcon from '../Common/MaterialIcon'
import { newsApi } from '../../services/api'
import ShareMenu from '../Common/ShareMenu'
import { useAuthStore } from '../../store/authStore'
import { useAudioListStore } from '../../store/audioListStore'
import { useAudioPlayerStore } from '../../store/audioPlayerStore'
import { getPlaceholderImageUrl } from '../../utils/imagePolicy'
import { formatSourceDisplayName, buildEditorialLine } from '../../utils/formatSource'
import { extractTitleFromUrl } from '../../utils/extractTitleFromUrl'
import { stripHtml } from '../../utils/sanitizeHtml'
import { apiErrorMessage } from '../../utils/apiErrorMessage'

export interface HomeNewsItem {
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

export default function HomeArticleCard({
  article,
  activeTab,
  subCategoryToLabel,
}: {
  article: HomeNewsItem
  activeTab: string
  subCategoryToLabel: Record<string, string>
}) {
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

  const formatDate = () => {
    if (article.time_ago) return article.time_ago
    const dateStr = (article as { display_date?: string }).display_date ?? article.published_at ?? (article as { created_at?: string }).created_at
    if (dateStr) {
      const date = new Date(dateStr)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return ''
  }

  const getCategoryLabel = () => {
    if (article.category) return subCategoryToLabel[article.category] ?? article.category
    if (article.source === 'Admin') return 'the gist.'
    return formatSourceDisplayName(article.source) || 'the gist.'
  }

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
      <div className="flex items-center justify-between pt-2 mt-2 border-t border-page">
        <Link to={detailUrl} state={{ fromTab: activeTab }} className="flex items-center gap-1.5 text-xs shrink-0">
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
