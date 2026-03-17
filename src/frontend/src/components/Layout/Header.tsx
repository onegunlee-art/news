import { useState, useEffect } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../../store/authStore'
import MaterialIcon from '../Common/MaterialIcon'
import GistLogo from '../Common/GistLogo'

export default function Header() {
  const { isAuthenticated, login } = useAuthStore()
  const [isLoginOpen, setIsLoginOpen] = useState(false)
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [scrollFill, setScrollFill] = useState(0)
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    const onScroll = () => {
      const maxScroll = document.documentElement.scrollHeight - window.innerHeight
      const ratio = maxScroll <= 0 ? 0 : Math.min(window.scrollY / maxScroll, 1)
      setScrollFill(ratio)
    }
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    return () => {
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
    }
  }, [])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`)
      setIsSearchOpen(false)
      setSearchQuery('')
    }
  }

  return (
    <header className="relative sticky top-0 z-40 border-b border-page overflow-hidden bg-page min-h-14 md:min-h-[5rem]">
      {/* 스크롤 비율에 따라 왼쪽→오른쪽으로 연한 회색이 진행 */}
      <div
        className="absolute inset-y-0 left-0 bg-page-secondary transition-[width] duration-200 ease-out"
        style={{ width: `${scrollFill * 100}%` }}
        aria-hidden
      />
      {/* 메인 헤더 - PC에서 넓은 max-width */}
      <div className="relative z-10 max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6">
        <div className="flex items-center justify-between min-h-14 md:min-h-[5rem] py-2 md:py-3">
          {/* 왼쪽 - 모바일: 세로 3선(햄버거) → My Page/구독 이동, PC: 텍스트 링크 */}
          <div className="w-16 md:w-auto flex items-center">
            {/* 모바일: 햄버거 아이콘 - My Page에 있으면 홈으로, 그 외에는 My Page/로그인으로 */}
            <button
              type="button"
              onClick={() => {
                if (location.pathname === '/profile') {
                  navigate('/')
                } else {
                  navigate(isAuthenticated ? '/profile' : '/login')
                }
              }}
              className="md:hidden p-2 -ml-2 text-page-secondary hover:text-page transition-colors"
              aria-label={location.pathname === '/profile' ? '홈으로' : isAuthenticated ? 'My Page' : '로그인/회원가입'}
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            {/* PC: 텍스트 링크 */}
            {isAuthenticated ? (
              <div className="hidden md:flex items-center gap-3">
                <Link to="/profile" className="text-xs text-page-secondary hover:text-page transition-colors">
                  My Page
                </Link>
                <Link to="/subscribe" className="text-xs text-page-secondary hover:text-page transition-colors">
                  구독
                </Link>
              </div>
            ) : (
              <Link to="/login" className="hidden md:inline text-xs text-page-secondary hover:text-page transition-colors">
                로그인/회원가입
              </Link>
            )}
          </div>

          {/* 중앙 - 로고 (GistLogo, 헤더용 20% 축소) */}
          <div className="flex-1 flex items-center justify-center min-w-0 min-h-[2.5rem] md:min-h-[3.75rem] overflow-visible">
            <GistLogo as="h1" size="header" link />
          </div>

          {/* 오른쪽 - 검색 아이콘만 */}
          <div className="w-16 md:w-auto flex justify-end items-center">
            <button
              onClick={() => setIsSearchOpen(true)}
              className="p-2 text-page-secondary hover:text-page transition-colors"
            >
              <MaterialIcon name="search" className="w-5 h-5" size={20} />
            </button>
          </div>
        </div>
      </div>

      {/* 검색 모달 */}
      <AnimatePresence>
        {isSearchOpen && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-page z-50"
          >
            <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-4">
              <div className="flex items-center gap-3">
                <button
                  onClick={() => setIsSearchOpen(false)}
                  className="p-2 text-page-secondary"
                >
                  <MaterialIcon name="arrow_back" className="w-5 h-5" size={20} />
                </button>
                <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="키워드 검색 (제목·내용·요약)"
                    autoFocus
                    className="flex-1 px-4 py-3 bg-page-secondary rounded-lg text-page placeholder-[var(--text-muted)] focus:outline-none focus:ring-2 focus:ring-primary-500"
                  />
                  <button
                    type="submit"
                    disabled={!searchQuery.trim()}
                    className="px-4 py-3 rounded-lg bg-[var(--text-primary)] text-[var(--bg-light)] text-sm font-medium hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                  >
                    검색하기
                  </button>
                </form>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* 로그인 모달 */}
      <AnimatePresence>
        {isLoginOpen && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            onClick={() => setIsLoginOpen(false)}
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="bg-white rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl"
              onClick={(e) => e.stopPropagation()}
            >
              <h2 className="text-xl font-semibold text-gray-900 text-center mb-5">로그인</h2>
              
              {/* 카카오 로그인 */}
              <button
                onClick={() => {
                  login()
                  setIsLoginOpen(false)
                }}
                className="w-full flex items-center justify-center gap-2 px-4 py-3 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-xl transition-all mb-3"
              >
                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z"/>
                </svg>
                카카오로 시작하기
              </button>

              {/* 이메일 로그인 */}
              <button
                onClick={() => {
                  navigate('/login')
                  setIsLoginOpen(false)
                }}
                className="w-full flex items-center justify-center gap-2 px-4 py-3 border border-gray-200 hover:border-gray-300 text-gray-700 font-medium rounded-xl transition-all mb-5"
              >
                이메일로 로그인
              </button>

              <div className="text-center text-sm text-gray-500">
                아직 계정이 없으신가요?{' '}
                <button
                  onClick={() => {
                    navigate('/register')
                    setIsLoginOpen(false)
                  }}
                  className="text-primary-500 hover:underline font-medium"
                >
                  회원가입
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  )
}
