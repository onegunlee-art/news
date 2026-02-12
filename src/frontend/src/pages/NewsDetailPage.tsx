import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { newsApi } from '../services/api'
import ShareMenu from '../components/Common/ShareMenu'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { formatSourceDisplayName } from '../utils/formatSource'
import { formatContentHtml } from '../utils/sanitizeHtml'

interface NewsDetail {
  id: number
  title: string
  description: string | null
  content: string | null
  why_important: string | null
  narration: string | null
  future_prediction?: string | null
  source: string | null
  original_source?: string | null  // 추출된 원본 출처 (예: Financial Times)
  url: string
  published_at: string | null
  created_at?: string | null
  updated_at?: string | null
  time_ago: string | null
  is_bookmarked?: boolean
  image_url?: string | null
  author?: string | null
  category?: string | null
}

/** API category 값 → 목록 라벨 (외교, 금융 등) */
const categoryToLabel: Record<string, string> = {
  diplomacy: '외교',
  economy: '금융',
  entertainment: '엔터테인먼트',
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
  const [showGptSummary, setShowGptSummary] = useState(false)

  // 전역 팝업 플레이어에서 재생 (제목 → 편집 문구 → 내레이션 → The Gist's Critique)
  const playArticle = () => {
    if (!news) return
    const dateStr = news.published_at
      ? `${new Date(news.published_at).getFullYear()}년 ${new Date(news.published_at).getMonth() + 1}월 ${new Date(news.published_at).getDate()}일`
      : (news.updated_at || news.created_at)
        ? `${new Date(news.updated_at || news.created_at!).getFullYear()}년 ${new Date(news.updated_at || news.created_at!).getMonth() + 1}월 ${new Date(news.updated_at || news.created_at!).getDate()}일`
        : ''
    const rawSource = (news.original_source && news.original_source.trim()) || (news.source === 'Admin' ? 'The Gist' : news.source || 'The Gist')
    const sourceDisplay = formatSourceDisplayName(rawSource) || 'The Gist'
    const editorialLine = dateStr
      ? `${dateStr}자 ${sourceDisplay} 저널의 "${news.title}"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.`
      : `${sourceDisplay} 저널의 "${news.title}"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.`
    const parts: string[] = [news.title, editorialLine]
    const mainContent = news.narration || news.content || news.description || ''
    if (mainContent) parts.push(mainContent)
    if (news.why_important) parts.push("The Gist's Critique.", news.why_important)
    const text = parts.join(' ').trim()
    if (!text) {
      alert('재생할 본문 내용이 없습니다.')
      return
    }
    addAudioItem({
      id: news.id,
      title: news.title,
      description: news.description ?? news.content ?? null,
      source: news.source ?? null,
    })
    const imageUrl = news.image_url || getPlaceholderImageUrl(
      { id: news.id, title: news.title, description: news.description, published_at: news.published_at, url: news.url, source: news.source },
      800,
      400
    )
    openAndPlay(news.title, text, 1.0, imageUrl, news.id)
  }

  useEffect(() => {
    if (id) {
      fetchNewsDetail(parseInt(id))
    }
  }, [id])

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

  // 원문 게재일 (URL에서 추출) → 제목 밑 출처 문구용
  const formatDate = () => {
    if (news?.published_at) {
      const date = new Date(news.published_at)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return news?.time_ago || ''
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

  // 소스 이름 매핑 (표시 시 " Magazine" 제거, e.g. Foreign Affairs Magazine → Foreign Affairs)
  const getSourceName = () => {
    let raw: string
    if (news?.original_source && news.original_source.trim()) raw = news.original_source
    else if (news?.source === 'Admin') return 'The Gist'
    else raw = news?.source || 'The Gist'
    return formatSourceDisplayName(raw) || 'The Gist'
  }

  // 글 목록 라벨 (카테고리 → 외교, 금융 등; 없으면 최신)
  const getListLabel = () => {
    if (!news?.category) return '최신'
    return categoryToLabel[news.category] ?? news.category
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
        <div className="text-gray-400 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-gray-900 mb-2">오류 발생</h2>
        <p className="text-gray-500 mb-6">{error}</p>
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
    <div className="min-h-screen bg-white pb-20 md:pb-8">
      {/* 상단 헤더 */}
      <div className="sticky top-0 bg-white z-40 border-b border-gray-100">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-between h-12">
            {/* 뒤로가기 */}
            <button
              onClick={() => navigate(-1)}
              className="flex items-center gap-1 text-gray-600 hover:text-gray-900 transition-colors"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
              <span className="text-sm">{getListLabel()}</span>
            </button>

            {/* 오른쪽 액션 버튼들 */}
            <div className="flex items-center gap-4">
              {/* 오디오 재생 (팝업 플레이어) */}
              <button 
                onClick={playArticle}
                className="p-1 transition-colors text-gray-400 hover:text-gray-600"
                title="음성으로 듣기"
                aria-label="재생"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 18v-6a9 9 0 0118 0v6M3 18h2a2 2 0 002-2v-4a2 2 0 00-2-2H3v8zm14 0h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2v8z" />
                </svg>
              </button>
              {/* 공유하기 (메뉴: 카카오톡 / 링크 복사 / 시스템 공유) */}
              {news && (
                <ShareMenu
                  title={news.title}
                  description={news.description || ''}
                  imageUrl={getImageUrl()}
                  webUrl={window.location.href}
                  className="text-gray-400 hover:text-gray-600"
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
                      navigate('/login')
                    }
                    return
                  }
                  handleBookmark()
                }}
                className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-gray-400 hover:text-gray-600'}`}
                title="즐겨찾기"
                aria-label={isBookmarked ? '즐겨찾기 해제' : '즐겨찾기 추가'}
              >
                <svg className="w-5 h-5" fill={isBookmarked ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <motion.article
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto"
      >
        {/* 대표 이미지 */}
        <div className="aspect-video bg-gray-100 overflow-hidden">
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

        <div className="px-4 pt-5 pb-8">
          {/* 소스 및 날짜 (매체 옆 = admin에서 업데이트한 날짜) */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <span className="text-primary-500 font-medium">{getSourceName()}</span>
            <span className="text-gray-300">/</span>
            <span className="text-gray-400">{formatHeaderDate()}</span>
          </div>

          {/* 제목 */}
          <h1 className="text-2xl font-bold text-gray-900 leading-snug mb-2">
            {news.title}
          </h1>

          {/* 디폴트 출처 문구: * 0000년 00월 00일자 OOO 저널의 "원제목"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다. */}
          <p className="text-sm text-gray-500 mb-6">
            * {formatDate()}자 {getSourceName()} 저널의 &quot;{news.title}&quot;을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.
          </p>

          {/* 저자 정보 (있을 경우) */}
          {news.author && (
            <div className="text-sm text-gray-500 mb-4">
              <span className="font-medium text-gray-700">{news.author}</span> 씀
            </div>
          )}

          {/* 오디오 재생 버튼 - 제목 아래 배치 */}
          <button
            onClick={playArticle}
            className="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors mb-6 pb-6 border-b border-gray-100 w-full"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 18v-6a9 9 0 0118 0v6M3 18h2a2 2 0 002-2v-4a2 2 0 00-2-2H3v8zm14 0h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2v8z" />
            </svg>
            <span className="font-medium">Listen to audio</span>
          </button>

          {/* 내레이션 (The Gist's Take) - 메인 콘텐츠 */}
          {news.narration && (
            <div className="prose prose-lg max-w-none mb-8">
              <p
                className="text-gray-700 leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5"
                dangerouslySetInnerHTML={{ __html: formatContentHtml(news.narration) }}
              />
            </div>
          )}

          {/* GPT 요약 (접이식) */}
          {news.content && (
            <div className="mb-8">
              <button
                type="button"
                onClick={() => setShowGptSummary(!showGptSummary)}
                className="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors"
              >
                <svg 
                  className={`w-4 h-4 transition-transform ${showGptSummary ? 'rotate-90' : ''}`} 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                  strokeWidth={2}
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                </svg>
                원문 AI 요약/구조 분석 보기
              </button>
              {showGptSummary && (
                <div className="mt-3 p-4 bg-gray-50 rounded-lg border border-gray-100">
                  <p
                    className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5"
                    dangerouslySetInnerHTML={{ __html: formatContentHtml(news.content) }}
                  />
                </div>
              )}
            </div>
          )}

          {/* 내레이션이 없고 GPT 요약도 없는 경우: description 표시 */}
          {!news.narration && !news.content && news.description && (
            <div className="prose prose-lg max-w-none mb-8">
              <p
                className="text-gray-700 leading-relaxed [&_mark]:rounded-sm [&_mark]:px-0.5"
                dangerouslySetInnerHTML={{ __html: formatContentHtml(news.description) }}
              />
            </div>
          )}

          {/* The Gist's Critique 섹션 */}
          {news.why_important && (
            <div className="border-t border-gray-100 pt-6 mt-6">
              <h2 
                className="text-xl font-medium mb-4"
                style={{ fontFamily: "'Lobster', cursive", color: '#FF6F00' }}
              >
                The Gist's Critique
              </h2>
              <div
                className="text-gray-700 leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5"
                dangerouslySetInnerHTML={{ __html: formatContentHtml(news.why_important) }}
              />
            </div>
          )}

          {/* 원문 링크 */}
          {news.url && news.url !== '#' && (
            <div className="border-t border-gray-100 pt-6 mt-6">
              <a
                href={news.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-primary-500 transition-colors"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                원문 보기
              </a>
            </div>
          )}
        </div>
      </motion.article>

      {/* 하단 네비게이션 - 모바일만, PC는 헤더에 링크 */}
      <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-40">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-around h-16">
            <Link to="/" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span className="text-xs">최신</span>
            </Link>
            <Link to="/profile" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
              <span className="text-xs">My Page</span>
            </Link>
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

      {error && (
        <div className="fixed bottom-20 md:bottom-4 left-4 right-4 max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto bg-red-50 text-red-600 px-4 py-3 rounded-lg text-center text-sm">
          {error}
        </div>
      )}
    </div>
  )
}
