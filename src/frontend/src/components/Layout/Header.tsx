import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../../store/authStore'

export default function Header() {
  const { isAuthenticated, login, logout } = useAuthStore()
  const [isMenuOpen, setIsMenuOpen] = useState(false)
  const [isLoginOpen, setIsLoginOpen] = useState(false)
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  return (
    <header className="bg-white border-b border-gray-200">
      {/* 상단 바 */}
      <div className="bg-gray-900 text-white text-xs py-2">
        <div className="max-w-7xl mx-auto px-4 flex justify-end items-center">
          <div className="flex items-center gap-4">
            {isAuthenticated ? (
              <button onClick={handleLogout} className="hover:text-gray-300">로그아웃</button>
            ) : (
              <button onClick={() => setIsLoginOpen(true)} className="hover:text-gray-300">로그인</button>
            )}
            <Link to="/subscribe" className="text-primary-500 hover:text-primary-400 font-semibold">구독하기</Link>
          </div>
        </div>
      </div>

      {/* 메인 헤더 */}
      <div className="max-w-7xl mx-auto px-4">
        {/* 로고 */}
        <div className="flex justify-center py-6 border-b border-gray-100">
          <Link to="/" className="text-center">
            <h1 className="text-4xl md:text-5xl text-gray-900" style={{ fontFamily: "'Petrov Sans', sans-serif", fontWeight: 400, fontStyle: 'normal', fontSynthesis: 'none' }}>
              The Gist
            </h1>
            <p className="text-xs text-gray-500 mt-1 tracking-widest uppercase">News Analysis</p>
          </Link>
        </div>

        {/* 네비게이션 */}
        <nav className="hidden md:flex justify-center py-4">
          <div className="flex items-center gap-8">
            <Link 
              to="/diplomacy" 
              className="text-sm font-medium text-gray-700 hover:text-primary-500 transition-colors uppercase tracking-wide"
            >
              Foreign Affair
            </Link>
            <Link 
              to="/economy" 
              className="text-sm font-medium text-gray-700 hover:text-primary-500 transition-colors uppercase tracking-wide"
            >
              Economy
            </Link>
            <Link 
              to="/technology" 
              className="text-sm font-medium text-gray-700 hover:text-primary-500 transition-colors uppercase tracking-wide"
            >
              Technology
            </Link>
            <Link 
              to="/entertainment" 
              className="text-sm font-medium text-gray-700 hover:text-primary-500 transition-colors uppercase tracking-wide"
            >
              Entertainment
            </Link>
          </div>
        </nav>

        {/* 모바일 메뉴 버튼 */}
        <div className="md:hidden flex justify-end py-4">
          <button
            onClick={() => setIsMenuOpen(!isMenuOpen)}
            className="p-2 text-gray-700"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              {isMenuOpen ? (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              ) : (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              )}
            </svg>
          </button>
        </div>
      </div>

      {/* 모바일 메뉴 */}
      <AnimatePresence>
        {isMenuOpen && (
          <motion.nav
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="md:hidden border-t border-gray-200 bg-white"
          >
            <div className="max-w-7xl mx-auto px-4 py-4 space-y-3">
              <Link
                to="/diplomacy"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-sm font-medium text-gray-700 uppercase tracking-wide"
              >
                Foreign Affair
              </Link>
              <Link
                to="/economy"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-sm font-medium text-gray-700 uppercase tracking-wide"
              >
                Economy
              </Link>
              <Link
                to="/technology"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-sm font-medium text-gray-700 uppercase tracking-wide"
              >
                Technology
              </Link>
              <Link
                to="/entertainment"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-sm font-medium text-gray-700 uppercase tracking-wide"
              >
                Entertainment
              </Link>
            </div>
          </motion.nav>
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
              className="bg-white rounded-lg p-8 max-w-md w-full mx-4 shadow-2xl"
              onClick={(e) => e.stopPropagation()}
            >
              <h2 className="text-2xl font-semibold text-gray-900 text-center mb-6">로그인</h2>
              
              {/* 카카오 로그인 */}
              <button
                onClick={() => {
                  login()
                  setIsLoginOpen(false)
                }}
                className="w-full flex items-center justify-center gap-2 px-4 py-3 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-all mb-3"
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
                className="w-full flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 hover:border-gray-400 text-gray-700 font-semibold rounded-lg transition-all mb-6"
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
                  구독하기
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  )
}
