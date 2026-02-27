import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import {
  SparklesIcon,
  ArrowTopRightOnSquareIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PlayIcon,
  BookmarkIcon,
  ExclamationTriangleIcon,
} from '@heroicons/react/24/outline'
import { BookmarkIcon as BookmarkIconSolid } from '@heroicons/react/24/solid'
import { motion } from 'framer-motion'
import { newsApi } from '../services/api'
import ShareMenu from '../components/Common/ShareMenu'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { formatSourceDisplayName, buildEditorialLine } from '../utils/formatSource'
import { extractTitleFromUrl } from '../utils/extractTitleFromUrl'
import { formatContentHtml, stripHtml } from '../utils/sanitizeHtml'

interface NewsDetail {
  id: number
  title: string
  subtitle?: string | null
  description: string | null
  content: string | null
  why_important: string | null
  narration: string | null
  future_prediction?: string | null
  source: string | null
  original_source?: string | null  // 추출된 원본 출처 (예: Foreign Affairs)
  original_title?: string | null   // 원문 영어 제목 (매체글 TTS용)
  url: string
  published_at: string | null
  created_at?: string | null
  updated_at?: string | null
  time_ago: string | null
  is_bookmarked?: boolean
  image_url?: string | null
  author?: string | null
  category?: string | null
  category_parent?: string | null
  next_article?: { id: number; title: string } | null
}

/** 상위 카테고리 (기사 소속) → 표시 라벨 - back 버튼에 사용 */
const parentCategoryToLabel: Record<string, string> = {
  diplomacy: '외교',
  economy: '경제',
  special: '특집',
}

export default function NewsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { isAuthenticated } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [news, setNews] = useState<NewsDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // 전역 팝업 플레이어에서 재생 (제목 → 매체설명 → 내레이션 → The Gist)
  // Listen 클릭 시 최신 기사 데이터를 강제 조회하여 stale state로 인한 이전 보이스 재생 방지
  const playArticle = async () => {
    if (!news) return
    let data = news
    try {
      const res = await newsApi.getDetail(news.id, { _t: Date.now() })
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
    const critiqueText = data.why_important ? `The Gist. ${stripHtml(data.why_important)}` : ''
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
      const response = await newsApi.getDetail(newsId)
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
    const hasAuth = isAuthenticated || !!localStorage.getItem('access_token')
    if (!hasAuth || !id) return

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

  // admin에서 업데이트한 날짜 → 사진 밑 매체 옆 표시용
  const formatHeaderDate = () => {
    const dateStr = news?.updated_at || news?.created_at
    if (dateStr) {
      const date = new Date(dateStr)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return news?.time_ago || ''
  }

  // 매체 설명용 날짜 (YYYY년 M월 D일, 원문 게재일 우선)
  const formatMediaDate = () => {
    const dateStr = news?.published_at || news?.updated_at || news?.created_at
    if (!dateStr) return ''
    const date = new Date(dateStr)
    return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
  }

  // 소스 이름 매핑 (표시 시 " Magazine" 제거, e.g. Foreign Affairs Magazine → Foreign Affairs)
  const getSourceName = () => {
    let raw: string
    if (news?.original_source && news.original_source.trim()) raw = news.original_source
    else if (news?.source === 'Admin') return 'The Gist'
    else raw = news?.source || 'The Gist'
    return formatSourceDisplayName(raw) || 'The Gist'
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
          <ExclamationTriangleIcon className="w-16 h-16 mx-auto" strokeWidth={1} />
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
      <div className="apply-grayscale sticky top-14 z-30 bg-page border-b border-page">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-between h-12">
            {/* 뒤로가기 */}
            <button
              onClick={() => navigate(-1)}
              className="flex items-center gap-1 text-page-secondary hover:text-page transition-colors"
            >
              <ChevronLeftIcon className="w-5 h-5" strokeWidth={2} />
              <span className="text-sm">{getListLabel()}</span>
            </button>

            {/* 오른쪽 액션 버튼들 */}
            <div className="flex items-center gap-4">
              {/* 오디오 재생 (팝업 플레이어) */}
              <button 
                onClick={playArticle}
                className="p-1 transition-colors text-page-secondary hover:text-page"
                title="음성으로 듣기"
                aria-label="재생"
              >
                <PlayIcon className="w-5 h-5" strokeWidth={2} />
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
                  const hasAuth = isAuthenticated || !!localStorage.getItem('access_token')
                  if (!hasAuth) {
                    if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
                      navigate('/login')
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
                  <BookmarkIconSolid className="w-5 h-5 text-primary-500" />
                ) : (
                  <BookmarkIcon className="w-5 h-5" strokeWidth={2} />
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
        {/* 대표 이미지 (흑백 제외) */}
        <div className="aspect-video bg-page-secondary overflow-hidden">
          <img
            src={getImageUrl()}
            alt={news.title}
            className="w-full h-full object-cover"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                { id: news.id, title: news.title, description: news.description, published_at: news.published_at, url: news.url, source: news.source },
                800,
                400
              )
            }}
          />
        </div>

        <div className="apply-grayscale px-4 pt-5 pb-8">
          {/* 소스 및 날짜 (매체 옆 = admin에서 업데이트한 날짜) */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <span className="text-primary-500 font-medium">{getSourceName()}</span>
            <span className="text-page-muted"> | </span>
            <span className="text-page-secondary">{formatHeaderDate()}</span>
          </div>

          {/* 제목 */}
          <h1 className="text-2xl font-bold text-page leading-snug mb-2">
            {news.title}
          </h1>

          {/* 부제목 (Foreign Affairs 등 매체의 서브타이틀) */}
          {news.subtitle && (
            <p className="text-lg text-page-secondary italic mb-3 leading-relaxed">
              {news.subtitle}
            </p>
          )}

          {/* 매체 설명 */}
          <p className="text-sm text-page-secondary mb-6">
            이 글은 {formatMediaDate()}{formatMediaDate() ? ' ' : ''}{getSourceName()}에 게재된 &quot;{(news.original_title && news.original_title.trim()) || extractTitleFromUrl(news.url) || '원문'}&quot; 글의 시각을 참고하여 작성되었습니다.
          </p>

          {/* 저자 정보 (있을 경우) */}
          {news.author && (
            <div className="text-sm text-page-secondary mb-4">
              <span className="font-medium text-page">{news.author}</span> 씀
            </div>
          )}

          {/* 오디오 재생 버튼 - 제목 아래 배치 */}
          <button
            onClick={playArticle}
            className="inline-flex items-center gap-2 text-base text-primary-500 hover:text-primary-600 transition-colors mb-6 pb-6 border-b border-page w-full"
          >
            <PlayIcon className="w-5 h-5 shrink-0" strokeWidth={2} />
            <span className="font-medium">AI 보이스로 듣기</span>
          </button>

          {/* The Gist — 단일 배경 박스 (아래 내레이션과 간격 2.5배) */}
          {news.why_important && (
            <div className="mb-20 bg-amber-50 rounded-xl shadow-sm overflow-hidden">
              <div className="px-5 py-5 sm:px-6 sm:py-6">
                <h2
                  className="text-xl font-semibold mb-3 tracking-wide"
                  style={{ fontFamily: "'Lobster', cursive", color: '#FF6F00' }}
                >
                  The Gist
                </h2>
                <div
                  className="text-page leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100"
                  dangerouslySetInnerHTML={{ __html: formatContentHtml(news.why_important) }}
                />
              </div>
            </div>
          )}

          {/* 내레이션 — 메인 콘텐츠 (아래 원문 AI 분석과 간격 5배) */}
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

          {/* 참고 글 AI 구조 분석 — 내레이션과 간격 5배 */}
          {(news.content || (news.url && news.url !== '#')) && (
            <div className="mb-8 border-t border-page pt-6 mt-20">
              <div className="flex justify-between items-center gap-4 mb-3">
                <h3 className="flex items-center gap-2 text-sm font-semibold text-primary-500 uppercase tracking-wider shrink-0">
                  <SparklesIcon className="w-4 h-4" strokeWidth={2} />
                  원문 AI 분석
                </h3>
                {news.url && news.url !== '#' && (
                  <a
                    href={news.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1.5 text-sm font-semibold text-primary-500 hover:text-primary-600 transition-colors shrink-0"
                  >
                    <ArrowTopRightOnSquareIcon className="w-4 h-4" strokeWidth={2} />
                    원문 보러가기
                  </a>
                )}
              </div>
              {news.content && (
                <div className="p-4 bg-page-secondary rounded-lg border border-page text-sm text-page-secondary leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100"
                  dangerouslySetInnerHTML={{ __html: formatContentHtml(news.content) }}
                />
              )}
            </div>
          )}
        </div>
      </motion.article>

      {/* 다음 기사 보기 - 우측 사이드바 (데스크톱) / 본문 하단 (모바일) */}
      {news.next_article && (
        <aside className="lg:w-64 flex-shrink-0 order-last lg:order-none">
          <div className="apply-grayscale lg:sticky lg:top-20 pt-6 lg:pt-8 border-t lg:border-t-0 lg:border-l border-page lg:pl-6 mt-6 lg:mt-0">
            <Link
              to={`/news/${news.next_article.id}`}
              className="block p-4 rounded-xl bg-page-secondary hover:opacity-90 transition-colors group"
            >
              <span className="text-xs font-medium text-page-muted uppercase tracking-wider">다음 기사</span>
              <p className="mt-1 text-sm font-medium text-page line-clamp-2 group-hover:text-primary-600 transition-colors">
                {news.next_article.title}
              </p>
              <span className="inline-flex items-center gap-1 mt-2 text-sm text-primary-500 font-medium">
                보기
                <ChevronRightIcon className="w-4 h-4" strokeWidth={2} />
              </span>
            </Link>
          </div>
        </aside>
      )}
      </div>

      {error && (
        <div className="fixed bottom-4 left-4 right-4 max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto bg-red-50 text-red-600 px-4 py-3 rounded-lg text-center text-sm">
          {error}
        </div>
      )}
    </div>
  )
}
