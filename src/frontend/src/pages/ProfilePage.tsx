import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore, type AudioListItem } from '../store/audioListStore'
import { newsApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { formatSourceDisplayName } from '../utils/formatSource'

export default function ProfilePage() {
  const { user, isAuthenticated, logout } = useAuthStore()
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<'bookmarks' | 'audio'>('bookmarks')
  const audioItems = useAudioListStore((s) => s.items)
  const [bookmarks, setBookmarks] = useState<any[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const activeTabRef = useRef(activeTab)
  activeTabRef.current = activeTab

  useEffect(() => {
    if (!isAuthenticated) return
    if (activeTab === 'bookmarks') fetchBookmarks()
  }, [activeTab, isAuthenticated])

  const fetchBookmarks = async () => {
    setIsLoading(true)
    try {
      const response = await newsApi.getBookmarks(1, 20)
      if (activeTabRef.current === 'bookmarks' && response.data.success) {
        setBookmarks(response.data.data.items || [])
      }
    } catch (error) {
      console.error('Failed to fetch bookmarks:', error)
    } finally {
      if (activeTabRef.current === 'bookmarks') setIsLoading(false)
    }
  }

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  return (
    <div className="min-h-screen bg-white pb-20 md:pb-8">
      {/* 페이지 헤더 */}
      <div className="bg-primary-500 pt-8 pb-16">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4">
          <h1 
            className="text-3xl text-black text-center"
            style={{ fontFamily: "'Lobster', cursive", fontWeight: 400 }}
          >
            My Page
          </h1>
        </div>
      </div>

      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 -mt-10">
        {/* 프로필 카드 — 로그인 시에만 프로필 표시 */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-white rounded-2xl shadow-lg p-6 mb-6"
        >
          <div className="flex flex-col sm:flex-row items-center gap-6">
            {user ? (
              <>
                <div className="relative">
                  {user.profile_image ? (
                    <img
                      src={user.profile_image}
                      alt={user.nickname}
                      className="w-20 h-20 rounded-full object-cover ring-4 ring-primary-500/30"
                    />
                  ) : (
                    <div className="w-20 h-20 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center ring-4 ring-primary-500/30">
                      <span className="text-2xl font-bold text-white">
                        {user.nickname.charAt(0)}
                      </span>
                    </div>
                  )}
                </div>
                <div className="flex-1 text-center sm:text-left">
                  <h2 className="text-xl font-bold text-gray-900 mb-1">{user.nickname}</h2>
                  {user.email && (
                    <p className="text-gray-500 text-sm mb-2">{user.email}</p>
                  )}
                  <div className="flex flex-wrap justify-center sm:justify-start gap-3 text-xs">
                    <span className="px-3 py-1 bg-primary-50 text-primary-600 rounded-full font-medium">
                      {user.role === 'admin' ? '관리자' : '회원'}
                    </span>
                    <span className="text-gray-400">
                      가입일: {new Date(user.created_at).toLocaleDateString('ko-KR')}
                    </span>
                  </div>
                </div>
                <button
                  onClick={handleLogout}
                  className="px-4 py-2 text-sm text-gray-500 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                >
                  로그아웃
                </button>
              </>
            ) : (
              <div className="flex-1 text-center py-2">
                <p className="text-gray-600 mb-3">로그인하면 즐겨찾기를 볼 수 있어요.</p>
                <Link
                  to="/login"
                  className="inline-block px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors text-sm"
                >
                  로그인
                </Link>
              </div>
            )}
          </div>
        </motion.div>

        {/* 탭 네비게이션 */}
        <div className="flex border-b border-gray-200 mb-6">
          <button
            onClick={() => setActiveTab('bookmarks')}
            className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
              activeTab === 'bookmarks'
                ? 'text-primary-500'
                : 'text-gray-500 hover:text-gray-900'
            }`}
          >
            즐겨찾기
            {activeTab === 'bookmarks' && (
              <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500" />
            )}
          </button>
          <button
            onClick={() => setActiveTab('audio')}
            className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
              activeTab === 'audio'
                ? 'text-primary-500'
                : 'text-gray-500 hover:text-gray-900'
            }`}
          >
            들었던 오디오
            {activeTab === 'audio' && (
              <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500" />
            )}
          </button>
        </div>

        {/* 콘텐츠 */}
        {activeTab === 'audio' ? (
          <AudioList items={Array.isArray(audioItems) ? audioItems : []} />
        ) : !isAuthenticated && activeTab === 'bookmarks' ? (
          <div className="text-center py-12">
            <p className="text-gray-500 mb-4">로그인하면 볼 수 있어요.</p>
            <Link to="/login" className="inline-block px-6 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors">
              로그인
            </Link>
          </div>
        ) : isLoading ? (
          <div className="flex justify-center py-12">
            <LoadingSpinner size="large" />
          </div>
        ) : (
          <BookmarkList bookmarks={bookmarks} />
        )}
      </div>

      {/* 하단 네비게이션 - 모바일 */}
      <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-40">
        <div className="max-w-lg mx-auto px-4">
          <div className="flex items-center justify-around h-16">
            <Link to="/" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span className="text-xs">최신</span>
            </Link>
            <Link to="/profile" className="flex flex-col items-center gap-1 text-primary-500">
              <svg className="w-6 h-6" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
              <span className="text-xs font-medium">My Page</span>
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
    </div>
  )
}

function AudioList({ items }: { items: AudioListItem[] }) {
  const safeItems = Array.isArray(items) ? items.filter((i) => i != null && Number.isFinite(i.id)) : []
  if (safeItems.length === 0) {
    return (
      <div className="text-center py-12">
        <div className="text-gray-300 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
          </svg>
        </div>
        <p className="text-gray-500 mb-4">들었던 오디오가 없습니다.</p>
        <p className="text-gray-400 text-sm">기사에서 음성 재생 버튼을 누르면 여기에 기록됩니다.</p>
      </div>
    )
  }

  return (
    <div className="space-y-0 divide-y divide-gray-100">
      {safeItems.map((item, index) => (
        <motion.div
          key={item.listenedAt ? `${item.id}-${item.listenedAt}` : `audio-${item.id}-${index}`}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-gray-50 transition-colors -mx-4 px-4">
            <h3 className="font-bold text-gray-900 mb-2 line-clamp-2 hover:text-primary-500 transition-colors">
              {item.title}
            </h3>
            {item.description && (
              <p className="text-sm text-gray-500 line-clamp-2 mb-2">
                {item.description}
              </p>
            )}
            <div className="flex items-center gap-2 text-xs">
              <span className="text-primary-500 font-medium">{formatSourceDisplayName(item.source) || 'The Gist'}</span>
              <span className="text-gray-300">/</span>
              <span className="text-gray-400">들은 날짜: {new Date(item.listenedAt).toLocaleDateString('ko-KR')}</span>
            </div>
          </Link>
        </motion.div>
      ))}
    </div>
  )
}

function BookmarkList({ bookmarks }: { bookmarks: any[] }) {
  if (bookmarks.length === 0) {
    return (
      <div className="text-center py-12">
        <div className="text-gray-300 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
        </div>
        <p className="text-gray-500 mb-4">즐겨찾기한 기사가 없습니다.</p>
        <Link
          to="/"
          className="inline-block px-6 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors"
        >
          뉴스 둘러보기
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-0 divide-y divide-gray-100">
      {bookmarks.map((item, index) => (
        <motion.div
          key={item.id}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-gray-50 transition-colors -mx-4 px-4">
            <h3 className="font-bold text-gray-900 mb-2 line-clamp-2 hover:text-primary-500 transition-colors">
              {item.title}
            </h3>
            {item.description && (
              <p className="text-sm text-gray-500 line-clamp-2 mb-2">
                {item.description}
              </p>
            )}
            <div className="flex items-center gap-2 text-xs">
              <span className="text-primary-500 font-medium">{formatSourceDisplayName(item.source) || 'The Gist'}</span>
              <span className="text-gray-300">/</span>
              {item.bookmarked_at && (
                <span className="text-gray-400">저장일: {new Date(item.bookmarked_at).toLocaleDateString('ko-KR')}</span>
              )}
            </div>
          </Link>
        </motion.div>
      ))}
    </div>
  )
}
