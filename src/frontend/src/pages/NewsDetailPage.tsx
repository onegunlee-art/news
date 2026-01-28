import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { newsApi, analysisApi } from '../services/api'
import { useAuthStore } from '../store/authStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import AnalysisResult from '../components/Analysis/AnalysisResult'

interface NewsDetail {
  id: number
  title: string
  description: string | null
  content: string | null
  source: string | null
  url: string
  published_at: string | null
  time_ago: string | null
  is_bookmarked?: boolean
}

interface AnalysisData {
  id: number
  keywords: Array<{ keyword: string; score: number; count: number }>
  sentiment: {
    type: string
    label: string
    score: number
    color: string
    details: any
  }
  summary: string
  status: string
  processing_time_ms: number
}

export default function NewsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { isAuthenticated, isSubscribed, checkSubscription } = useAuthStore()
  const [news, setNews] = useState<NewsDetail | null>(null)
  const [analysis, setAnalysis] = useState<AnalysisData | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isAnalyzing, setIsAnalyzing] = useState(false)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [showSubscribeModal, setShowSubscribeModal] = useState(false)

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

  const handleAnalyze = async () => {
    if (!id || isAnalyzing) return

    // 구독 상태 확인
    const hasSubscription = checkSubscription() || isSubscribed
    
    if (!hasSubscription) {
      // 구독하지 않은 사용자에게 구독 안내 모달 표시
      setShowSubscribeModal(true)
      return
    }

    setIsAnalyzing(true)
    setError(null)

    try {
      const response = await analysisApi.analyzeNews(parseInt(id))
      if (response.data.success) {
        setAnalysis(response.data.data)
      }
    } catch (error: any) {
      setError(error.response?.data?.message || '분석에 실패했습니다.')
    } finally {
      setIsAnalyzing(false)
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

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-[60vh]">
        <LoadingSpinner size="large" />
      </div>
    )
  }

  if (error && !news) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-16 text-center">
        <div className="text-red-400 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-white mb-2">오류 발생</h2>
        <p className="text-gray-400 mb-6">{error}</p>
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
    <div className="max-w-4xl mx-auto px-4 py-8">
      {/* 뒤로가기 */}
      <Link
        to="/"
        className="inline-flex items-center gap-2 text-gray-400 hover:text-white mb-6 transition-colors"
      >
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
        </svg>
        목록으로
      </Link>

      <motion.article
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="card mb-8"
      >
        {/* 메타 정보 */}
        <div className="flex items-center gap-4 text-sm text-gray-400 mb-4">
          {news.source && (
            <span className="px-2 py-1 bg-primary-500/10 text-primary-400 rounded">
              {news.source}
            </span>
          )}
          {news.time_ago && <span>{news.time_ago}</span>}
        </div>

        {/* 제목 */}
        <h1 className="text-2xl lg:text-3xl font-bold text-white mb-6 leading-tight">
          {news.title}
        </h1>

        {/* 본문 */}
        {news.content ? (
          <div className="prose prose-invert max-w-none mb-6">
            <p className="text-gray-300 leading-relaxed whitespace-pre-wrap">
              {news.content}
            </p>
          </div>
        ) : news.description ? (
          <p className="text-gray-300 leading-relaxed mb-6">{news.description}</p>
        ) : null}

        {/* 액션 버튼들 */}
        <div className="flex flex-wrap items-center gap-4 pt-6 border-t border-white/10">
          {isAuthenticated && (
            <button
              onClick={handleBookmark}
              className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                isBookmarked
                  ? 'bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30'
                  : 'bg-white/5 text-gray-300 hover:bg-white/10'
              }`}
            >
              <svg
                className="w-5 h-5"
                fill={isBookmarked ? 'currentColor' : 'none'}
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
              {isBookmarked ? '북마크됨' : '북마크'}
            </button>
          )}

          <button
            onClick={handleAnalyze}
            disabled={isAnalyzing}
            className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isAnalyzing ? (
              <>
                <LoadingSpinner size="small" />
                분석 중...
              </>
            ) : (
              <>
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                이게 왜 중요한대!
              </>
            )}
          </button>
        </div>
      </motion.article>

      {/* 분석 결과 */}
      {analysis && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <AnalysisResult analysis={analysis} />
        </motion.div>
      )}

      {error && analysis === null && (
        <div className="text-center py-8 text-red-400">
          <p>{error}</p>
        </div>
      )}

      {/* 구독 안내 모달 */}
      {showSubscribeModal && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm"
          onClick={() => setShowSubscribeModal(false)}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            className="bg-dark-800 rounded-2xl p-8 max-w-md mx-4 border border-white/10"
            onClick={(e) => e.stopPropagation()}
          >
            {/* 아이콘 */}
            <div className="flex justify-center mb-6">
              <div className="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-600 rounded-full flex items-center justify-center">
                <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
            </div>

            {/* 제목 */}
            <h3 className="text-2xl font-bold text-white text-center mb-3">
              구독이 필요합니다
            </h3>

            {/* 설명 */}
            <p className="text-gray-400 text-center mb-6 leading-relaxed">
              전문가의 심층 분석을 확인하시려면<br />
              구독 서비스에 가입해주세요.
            </p>

            {/* 혜택 */}
            <div className="bg-gradient-to-r from-primary-500/10 to-primary-600/10 border border-primary-500/20 rounded-xl p-4 mb-6">
              <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 bg-primary-500/20 rounded-full flex items-center justify-center">
                  <svg className="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                  </svg>
                </div>
                <div>
                  <p className="text-primary-400 font-semibold">🎉 1달 무료 체험!</p>
                  <p className="text-gray-400 text-sm">지금 가입하시면 첫 달은 무료입니다</p>
                </div>
              </div>
              <ul className="space-y-2 text-sm text-gray-300">
                <li className="flex items-center gap-2">
                  <svg className="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  "이게 왜 중요한대!" 심층 분석
                </li>
                <li className="flex items-center gap-2">
                  <svg className="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  빅픽쳐 - 글로벌 트렌드 분석
                </li>
                <li className="flex items-center gap-2">
                  <svg className="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  "그래서 우리에겐?" 영향 분석
                </li>
              </ul>
            </div>

            {/* 버튼들 */}
            <div className="flex gap-3">
              <button
                onClick={() => setShowSubscribeModal(false)}
                className="flex-1 px-4 py-3 bg-white/5 hover:bg-white/10 text-gray-300 rounded-xl transition-colors"
              >
                닫기
              </button>
              <button
                onClick={() => navigate('/register')}
                className="flex-1 px-4 py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl transition-all"
              >
                무료로 시작하기
              </button>
            </div>
          </motion.div>
        </motion.div>
      )}
    </div>
  )
}
