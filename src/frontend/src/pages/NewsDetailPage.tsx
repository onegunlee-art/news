import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { newsApi } from '../services/api'
import { shareToKakao } from '../services/kakaoAuth'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'

interface NewsDetail {
  id: number
  title: string
  description: string | null
  content: string | null
  why_important: string | null
  source: string | null
  url: string
  published_at: string | null
  time_ago: string | null
  is_bookmarked?: boolean
  image_url?: string | null
  author?: string | null
}

export default function NewsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { isAuthenticated } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const [news, setNews] = useState<NewsDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [error, setError] = useState<string | null>(null)
  
  // TTS 상태
  const [isSpeaking, setIsSpeaking] = useState(false)
  const [speechRate, setSpeechRate] = useState(1.2)

  // TTS 음성 읽기 함수
  const speakText = (text: string) => {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel()
      
      const utterance = new SpeechSynthesisUtterance(text)
      utterance.lang = 'ko-KR'
      utterance.rate = speechRate
      utterance.pitch = 1.0
      
      const voices = window.speechSynthesis.getVoices()
      const koreanVoice = voices.find(voice => voice.lang.includes('ko'))
      if (koreanVoice) {
        utterance.voice = koreanVoice
      }
      
      utterance.onstart = () => setIsSpeaking(true)
      utterance.onend = () => setIsSpeaking(false)
      utterance.onerror = () => setIsSpeaking(false)
      
      window.speechSynthesis.speak(utterance)
    } else {
      alert('이 브라우저는 음성 합성을 지원하지 않습니다.')
    }
  }

  const speakArticle = () => {
    if (!news) return
    addAudioItem({
      id: news.id,
      title: news.title,
      description: news.description ?? news.content ?? null,
      source: news.source ?? null,
    })
    let text = `${news.title}. ${news.content || news.description || ''}`
    if (news.why_important) {
      text += ` The Gist's Critics. ${news.why_important}`
    }
    speakText(text)
  }

  const stopSpeaking = () => {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel()
      setIsSpeaking(false)
    }
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
        setNews(response.data.data)
        setIsBookmarked(response.data.data.is_bookmarked || false)
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
      console.error('Bookmark error:', error)
    }
  }

  // 날짜 포맷팅
  const formatDate = () => {
    if (news?.published_at) {
      const date = new Date(news.published_at)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return news?.time_ago || ''
  }

  // 소스 이름 매핑
  const getSourceName = () => {
    if (news?.source === 'Admin') return 'The Gist'
    return news?.source || 'Foreign Affairs'
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
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto px-4 py-16 text-center">
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
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto px-4">
          <div className="flex items-center justify-between h-12">
            {/* 뒤로가기 */}
            <button
              onClick={() => navigate(-1)}
              className="flex items-center gap-1 text-gray-600 hover:text-gray-900 transition-colors"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
              <span className="text-sm">최신</span>
            </button>

            {/* 오른쪽 액션 버튼들 */}
            <div className="flex items-center gap-4">
              {/* 오디오 재생 */}
              <button 
                onClick={isSpeaking ? stopSpeaking : speakArticle}
                className={`p-1 transition-colors ${isSpeaking ? 'text-primary-500' : 'text-gray-400 hover:text-gray-600'}`}
                title="음성으로 듣기"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  {isSpeaking ? (
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z M10 9v6 M14 9v6" />
                  ) : (
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M12 18.75a.75.75 0 01-.75-.75V6a.75.75 0 011.5 0v12a.75.75 0 01-.75.75zM8.25 15V9a.75.75 0 011.5 0v6a.75.75 0 01-1.5 0zM5.25 12.75v-1.5a.75.75 0 011.5 0v1.5a.75.75 0 01-1.5 0z" />
                  )}
                </svg>
              </button>
              {/* 카카오톡 공유 */}
              <button 
                onClick={() => news && shareToKakao({
                  title: news.title,
                  description: news.description || '',
                  imageUrl: getImageUrl(),
                  webUrl: window.location.href,
                })}
                className="p-1 text-gray-400 hover:text-yellow-500 transition-colors"
                title="카카오톡으로 공유"
              >
                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 3C6.5 3 2 6.58 2 11c0 2.83 1.82 5.32 4.56 6.74-.2.74-.73 2.68-.84 3.1-.13.53.19.52.41.38.17-.11 2.74-1.87 3.85-2.63.65.09 1.32.14 2.02.14 5.5 0 10-3.58 10-8s-4.5-8-10-8z"/>
                </svg>
              </button>
              {/* 즐겨찾기 */}
              <button
                onClick={() => {
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
              >
                <svg className="w-5 h-5" fill={isBookmarked ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <motion.article
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto"
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
          {/* 소스 및 날짜 */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <span className="text-primary-500 font-medium">{getSourceName()}</span>
            <span className="text-gray-300">/</span>
            <span className="text-gray-400">{formatDate()}</span>
          </div>

          {/* 제목 */}
          <h1 className="text-2xl font-bold text-gray-900 leading-snug mb-6">
            {news.title}
          </h1>

          {/* 저자 정보 (있을 경우) */}
          {news.author && (
            <div className="text-sm text-gray-500 mb-6">
              <span className="font-medium text-gray-700">{news.author}</span> 씀
            </div>
          )}

          {/* 본문 내용 */}
          <div className="prose prose-lg max-w-none mb-8">
            {news.content ? (
              <p className="text-gray-700 leading-relaxed whitespace-pre-wrap">
                {news.content}
              </p>
            ) : news.description ? (
              <p className="text-gray-700 leading-relaxed">{news.description}</p>
            ) : (
              <p className="text-gray-400 italic">본문 내용이 없습니다.</p>
            )}
          </div>

          {/* The Gist's Critics 섹션 */}
          {news.why_important && (
            <div className="border-t border-gray-100 pt-6 mt-6">
              <h2 
                className="text-xl font-medium mb-4"
                style={{ fontFamily: "'Lobster', cursive", color: '#FF6F00' }}
              >
                The Gist's Critics
              </h2>
              <div className="text-gray-700 leading-relaxed whitespace-pre-wrap">
                {/* 중요 문구 강조 (bold 처리) */}
                {news.why_important.split(/(\*\*[^*]+\*\*)/).map((part, index) => {
                  if (part.startsWith('**') && part.endsWith('**')) {
                    return (
                      <span key={index} className="font-bold text-gray-900">
                        {part.slice(2, -2)}
                      </span>
                    )
                  }
                  // 오렌지 하이라이트 (밑줄 텍스트)
                  const underlineRegex = /__([^_]+)__/g
                  const parts = part.split(underlineRegex)
                  return parts.map((subPart, subIndex) => {
                    if (subIndex % 2 === 1) {
                      return (
                        <span key={`${index}-${subIndex}`} className="text-primary-500 font-medium underline decoration-primary-500">
                          {subPart}
                        </span>
                      )
                    }
                    return subPart
                  })
                })}
              </div>
            </div>
          )}

          {/* AI 음성 읽기 */}
          <div className="border-t border-gray-100 pt-6 mt-6">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <svg className="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                </svg>
                <span className="text-gray-900 font-medium">음성으로 듣기</span>
              </div>
              <select
                value={speechRate}
                onChange={(e) => setSpeechRate(parseFloat(e.target.value))}
                className="text-sm bg-gray-100 text-gray-700 rounded-lg px-3 py-1.5 border-0 focus:ring-2 focus:ring-primary-500"
              >
                <option value="0.7">느리게</option>
                <option value="0.85">조금 느리게</option>
                <option value="1.0">보통</option>
                <option value="1.2">약간 빠름</option>
                <option value="1.4">빠르게</option>
                <option value="2.0">최고속도</option>
              </select>
            </div>
            
            <button
              onClick={isSpeaking ? stopSpeaking : speakArticle}
              className={`w-full py-3 rounded-xl font-medium transition flex items-center justify-center gap-2 ${
                isSpeaking
                  ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  : 'bg-primary-500 text-white hover:bg-primary-600'
              }`}
            >
              {isSpeaking ? (
                <>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  읽기 중지
                </>
              ) : (
                <>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  기사 읽어주기
                </>
              )}
            </button>
          </div>

          {/* 원문 링크 */}
          {news.url && news.url !== '#' && (
            <div className="border-t border-gray-100 pt-6 mt-6">
              <a
                href={news.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-primary-500 transition-colors"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                원문 보기
              </a>
            </div>
          )}
        </div>
      </motion.article>

      {/* 하단 네비게이션 - 모바일만, PC는 헤더에 링크 */}
      <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-40">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto px-4">
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
        <div className="fixed bottom-20 md:bottom-4 left-4 right-4 max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto bg-red-50 text-red-600 px-4 py-3 rounded-lg text-center text-sm">
          {error}
        </div>
      )}
    </div>
  )
}
