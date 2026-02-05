import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../../store/authStore'

export default function Header() {
  const { isAuthenticated, login, logout } = useAuthStore()
  const [isLoginOpen, setIsLoginOpen] = useState(false)
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`)
      setIsSearchOpen(false)
      setSearchQuery('')
    }
  }

  return (
    <header className="bg-white sticky top-0 z-40 border-b border-gray-100">
      {/* 메인 헤더 - PC에서 넓은 max-width */}
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6">
        <div className="flex items-center justify-between h-14">
          {/* 왼쪽 - 로그인/로그아웃 */}
          <div className="w-16 md:w-auto">
            {isAuthenticated ? (
              <button 
                onClick={handleLogout} 
                className="text-xs text-gray-500 hover:text-gray-900 transition-colors"
              >
                로그아웃
              </button>
            ) : (
              <button 
                onClick={() => setIsLoginOpen(true)} 
                className="text-xs text-gray-500 hover:text-gray-900 transition-colors"
              >
                로그인
              </button>
            )}
          </div>

          {/* 중앙 - 로고 (Lobster 폰트, 크게) */}
          <Link to="/" className="flex-1 text-center">
            <h1 className="text-2xl md:text-4xl font-normal text-gray-900 tracking-tight" style={{ fontFamily: "'Lobster', cursive" }}>
              The Gist
            </h1>
          </Link>

          {/* 오른쪽 - PC: 최신/즐겨찾기/설정 + 검색 */}
          <div className="w-16 md:w-auto flex justify-end items-center gap-2 md:gap-6">
            <nav className="hidden md:flex items-center gap-6">
              <Link to="/" className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                최신
              </Link>
              <Link to="/profile" className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                My Page
              </Link>
              <Link to="/settings" className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                설정
              </Link>
            </nav>
            <button
              onClick={() => setIsSearchOpen(true)}
              className="p-2 text-gray-600 hover:text-gray-900 transition-colors"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
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
            className="fixed inset-0 bg-white z-50"
          >
            <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-4">
              <div className="flex items-center gap-3">
                <button
                  onClick={() => setIsSearchOpen(false)}
                  className="p-2 text-gray-600"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                  </svg>
                </button>
                <form onSubmit={handleSearch} className="flex-1 flex gap-2">
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="키워드 검색 (제목·내용·요약)"
                    autoFocus
                    className="flex-1 px-4 py-3 bg-gray-100 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                  />
                  <button
                    type="submit"
                    disabled={!searchQuery.trim()}
                    className="px-4 py-3 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
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
