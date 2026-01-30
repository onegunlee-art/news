import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../../store/authStore'

export default function Header() {
  const { user, isAuthenticated, login, logout } = useAuthStore()
  const [isMenuOpen, setIsMenuOpen] = useState(false)
  const [isProfileOpen, setIsProfileOpen] = useState(false)
  const [isLoginOpen, setIsLoginOpen] = useState(false)
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    setIsProfileOpen(false)
    navigate('/')
  }

  return (
    <header className="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* 로고 */}
          <Link to="/" className="flex items-center gap-2">
            <span className="font-display font-bold text-4xl text-gray-900 tracking-tight">
              INFER
            </span>
          </Link>

          {/* 데스크톱 네비게이션 */}
          <nav className="hidden md:flex items-center gap-10">
            <Link 
              to="/diplomacy" 
              className="text-gray-700 hover:text-primary-600 transition-colors font-medium"
            >
              Foreign Affair
            </Link>
            <Link 
              to="/economy" 
              className="text-gray-700 hover:text-primary-600 transition-colors font-medium"
            >
              Economy
            </Link>
            <Link 
              to="/technology" 
              className="text-gray-700 hover:text-primary-600 transition-colors font-medium"
            >
              Technology
            </Link>
            <Link 
              to="/entertainment" 
              className="text-gray-700 hover:text-primary-600 transition-colors font-medium"
            >
              Entertainment
            </Link>
            <Link 
              to="/register" 
              className="text-gray-700 hover:text-primary-600 transition-colors font-medium"
            >
              구독하기
            </Link>
          </nav>

          {/* 우측 메뉴 */}
          <div className="flex items-center gap-4">
            {isAuthenticated && user ? (
              <div className="relative">
                <button
                  onClick={() => setIsProfileOpen(!isProfileOpen)}
                  className="flex items-center gap-2 p-1 rounded-full hover:bg-white/5 transition-colors"
                >
                  {user.profile_image ? (
                    <img
                      src={user.profile_image}
                      alt={user.nickname}
                      className="w-8 h-8 rounded-full object-cover"
                    />
                  ) : (
                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-accent-purple flex items-center justify-center">
                      <span className="text-sm font-bold text-white">
                        {user.nickname.charAt(0)}
                      </span>
                    </div>
                  )}
                  <span className="hidden sm:block text-sm font-medium text-gray-200">
                    {user.nickname}
                  </span>
                </button>

                <AnimatePresence>
                  {isProfileOpen && (
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -10 }}
                      className="absolute right-0 mt-2 w-48 bg-dark-600 rounded-xl border border-white/10 shadow-xl overflow-hidden"
                    >
                      <div className="p-3 border-b border-white/5">
                        <p className="font-medium text-white">{user.nickname}</p>
                        <p className="text-sm text-gray-400">{user.email || '이메일 없음'}</p>
                      </div>
                      <div className="py-1">
                        <Link
                          to="/profile"
                          onClick={() => setIsProfileOpen(false)}
                          className="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                        >
                          내 프로필
                        </Link>
                        <Link
                          to="/analysis"
                          onClick={() => setIsProfileOpen(false)}
                          className="block px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                        >
                          분석 내역
                        </Link>
                        <button
                          onClick={handleLogout}
                          className="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-white/5 transition-colors"
                        >
                          로그아웃
                        </button>
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            ) : (
              <div className="relative">
                <button
                  onClick={() => setIsLoginOpen(!isLoginOpen)}
                  className="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white font-semibold rounded-lg transition-all"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  <span>로그인</span>
                </button>

                <AnimatePresence>
                  {isLoginOpen && (
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -10 }}
                      className="absolute right-0 mt-2 w-56 bg-dark-600 rounded-xl border border-white/10 shadow-xl overflow-hidden"
                    >
                      <div className="p-3 border-b border-white/5">
                        <p className="font-medium text-white text-center">로그인 방법 선택</p>
                      </div>
                      <div className="p-3 space-y-2">
                        {/* 카카오 로그인 */}
                        <button
                          onClick={() => {
                            login()
                            setIsLoginOpen(false)
                          }}
                          className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-all"
                        >
                          <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z"/>
                          </svg>
                          카카오로 시작하기
                        </button>

                        {/* 일반 로그인 */}
                        <button
                          onClick={() => {
                            navigate('/login')
                            setIsLoginOpen(false)
                          }}
                          className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-lg transition-all"
                        >
                          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                          </svg>
                          이메일로 로그인
                        </button>
                      </div>

                      {/* 회원가입 */}
                      <div className="p-3 border-t border-white/5 bg-dark-700/50">
                        <p className="text-sm text-gray-400 text-center mb-2">아직 계정이 없으신가요?</p>
                        <button
                          onClick={() => {
                            navigate('/register')
                            setIsLoginOpen(false)
                          }}
                          className="w-full flex items-center justify-center gap-2 px-4 py-2 border border-primary-500 text-primary-400 hover:bg-primary-500/10 font-semibold rounded-lg transition-all"
                        >
                          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                          </svg>
                          회원가입
                        </button>
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            )}

            {/* 모바일 메뉴 버튼 */}
            <button
              onClick={() => setIsMenuOpen(!isMenuOpen)}
              className="md:hidden p-2 text-gray-600 hover:text-gray-900 transition-colors"
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
              className="md:hidden pb-4 space-y-1 bg-white"
            >
              <Link
                to="/diplomacy"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-gray-700 hover:text-primary-600 transition-colors font-medium"
              >
                Foreign Affair
              </Link>
              <Link
                to="/economy"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-gray-700 hover:text-primary-600 transition-colors font-medium"
              >
                Economy
              </Link>
              <Link
                to="/technology"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-gray-700 hover:text-primary-600 transition-colors font-medium"
              >
                Technology
              </Link>
              <Link
                to="/entertainment"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-gray-700 hover:text-primary-600 transition-colors font-medium"
              >
                Entertainment
              </Link>
              <Link
                to="/register"
                onClick={() => setIsMenuOpen(false)}
                className="block py-2 text-gray-700 hover:text-primary-600 transition-colors font-medium"
              >
                구독하기
              </Link>
            </motion.nav>
          )}
        </AnimatePresence>
      </div>
    </header>
  )
}
