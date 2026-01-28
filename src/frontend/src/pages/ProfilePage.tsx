import { useState, useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { newsApi, analysisApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'

export default function ProfilePage() {
  const { user, isAuthenticated, logout } = useAuthStore()
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<'bookmarks' | 'analyses'>('bookmarks')
  const [bookmarks, setBookmarks] = useState<any[]>([])
  const [analyses, setAnalyses] = useState<any[]>([])
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/')
    }
  }, [isAuthenticated, navigate])

  useEffect(() => {
    if (activeTab === 'bookmarks') {
      fetchBookmarks()
    } else {
      fetchAnalyses()
    }
  }, [activeTab])

  const fetchBookmarks = async () => {
    setIsLoading(true)
    try {
      const response = await newsApi.getBookmarks(1, 20)
      if (response.data.success) {
        setBookmarks(response.data.data.items || [])
      }
    } catch (error) {
      console.error('Failed to fetch bookmarks:', error)
    } finally {
      setIsLoading(false)
    }
  }

  const fetchAnalyses = async () => {
    setIsLoading(true)
    try {
      const response = await analysisApi.getHistory(1, 20)
      if (response.data.success) {
        setAnalyses(response.data.data.items || [])
      }
    } catch (error) {
      console.error('Failed to fetch analyses:', error)
    } finally {
      setIsLoading(false)
    }
  }

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  if (!user) return null

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {/* 프로필 헤더 */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="card mb-8"
      >
        <div className="flex flex-col sm:flex-row items-center gap-6">
          {/* 프로필 이미지 */}
          <div className="relative">
            {user.profile_image ? (
              <img
                src={user.profile_image}
                alt={user.nickname}
                className="w-24 h-24 rounded-full object-cover ring-4 ring-primary-500/30"
              />
            ) : (
              <div className="w-24 h-24 rounded-full bg-gradient-to-br from-primary-400 to-accent-purple flex items-center justify-center ring-4 ring-primary-500/30">
                <span className="text-3xl font-bold text-white">
                  {user.nickname.charAt(0)}
                </span>
              </div>
            )}
          </div>

          {/* 사용자 정보 */}
          <div className="flex-1 text-center sm:text-left">
            <h1 className="text-2xl font-bold text-white mb-1">{user.nickname}</h1>
            {user.email && (
              <p className="text-gray-400 mb-3">{user.email}</p>
            )}
            <div className="flex flex-wrap justify-center sm:justify-start gap-4 text-sm">
              <span className="px-3 py-1 bg-primary-500/10 text-primary-400 rounded-full">
                {user.role === 'admin' ? '관리자' : '일반 회원'}
              </span>
              <span className="text-gray-500">
                가입일: {new Date(user.created_at).toLocaleDateString('ko-KR')}
              </span>
            </div>
          </div>

          {/* 로그아웃 버튼 */}
          <button
            onClick={handleLogout}
            className="px-4 py-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors"
          >
            로그아웃
          </button>
        </div>
      </motion.div>

      {/* 탭 네비게이션 */}
      <div className="flex gap-2 mb-6">
        <button
          onClick={() => setActiveTab('bookmarks')}
          className={`flex-1 sm:flex-none px-6 py-3 rounded-xl font-medium transition-all ${
            activeTab === 'bookmarks'
              ? 'bg-primary-500 text-white'
              : 'bg-dark-600 text-gray-400 hover:text-white hover:bg-dark-500'
          }`}
        >
          북마크
        </button>
        <button
          onClick={() => setActiveTab('analyses')}
          className={`flex-1 sm:flex-none px-6 py-3 rounded-xl font-medium transition-all ${
            activeTab === 'analyses'
              ? 'bg-primary-500 text-white'
              : 'bg-dark-600 text-gray-400 hover:text-white hover:bg-dark-500'
          }`}
        >
          분석 내역
        </button>
      </div>

      {/* 콘텐츠 */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <LoadingSpinner size="large" />
        </div>
      ) : activeTab === 'bookmarks' ? (
        <BookmarkList bookmarks={bookmarks} />
      ) : (
        <AnalysisList analyses={analyses} />
      )}
    </div>
  )
}

function BookmarkList({ bookmarks }: { bookmarks: any[] }) {
  if (bookmarks.length === 0) {
    return (
      <div className="text-center py-12">
        <div className="text-gray-500 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
        </div>
        <p className="text-gray-400">북마크한 뉴스가 없습니다.</p>
        <Link
          to="/"
          className="inline-block mt-4 px-4 py-2 bg-primary-500/10 text-primary-400 rounded-lg hover:bg-primary-500/20 transition-colors"
        >
          뉴스 둘러보기
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {bookmarks.map((item, index) => (
        <motion.div
          key={item.id}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
          className="card card-hover"
        >
          <Link to={`/news/${item.id}`}>
            <h3 className="font-semibold text-white mb-2 hover:text-primary-400 transition-colors">
              {item.title}
            </h3>
            {item.description && (
              <p className="text-sm text-gray-400 line-clamp-2 mb-3">
                {item.description}
              </p>
            )}
            <div className="flex items-center gap-4 text-xs text-gray-500">
              {item.source && <span>{item.source}</span>}
              {item.bookmarked_at && (
                <span>북마크: {new Date(item.bookmarked_at).toLocaleDateString('ko-KR')}</span>
              )}
            </div>
          </Link>
        </motion.div>
      ))}
    </div>
  )
}

function AnalysisList({ analyses }: { analyses: any[] }) {
  if (analyses.length === 0) {
    return (
      <div className="text-center py-12">
        <div className="text-gray-500 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <p className="text-gray-400">분석 내역이 없습니다.</p>
        <Link
          to="/analysis"
          className="inline-block mt-4 px-4 py-2 bg-primary-500/10 text-primary-400 rounded-lg hover:bg-primary-500/20 transition-colors"
        >
          텍스트 분석하기
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {analyses.map((item, index) => (
        <motion.div
          key={item.id}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
          className="card card-hover"
        >
          <div className="flex items-start justify-between gap-4">
            <div className="flex-1 min-w-0">
              {item.news_title ? (
                <h3 className="font-semibold text-white mb-2">{item.news_title}</h3>
              ) : (
                <p className="text-gray-400 text-sm line-clamp-2 mb-2">
                  {item.summary || '텍스트 분석'}
                </p>
              )}
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span
                  className="px-2 py-1 rounded"
                  style={{ 
                    backgroundColor: `${item.sentiment?.color}20`,
                    color: item.sentiment?.color 
                  }}
                >
                  {item.sentiment?.label}
                </span>
                <span className="text-gray-500">
                  {new Date(item.created_at).toLocaleDateString('ko-KR')}
                </span>
              </div>
            </div>
            <div className="flex gap-1">
              {item.keywords?.slice(0, 3).map((kw: any, i: number) => (
                <span
                  key={i}
                  className="px-2 py-1 bg-white/5 text-gray-400 text-xs rounded"
                >
                  {kw.keyword}
                </span>
              ))}
            </div>
          </div>
        </motion.div>
      ))}
    </div>
  )
}
