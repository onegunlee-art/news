import { Link } from 'react-router-dom'

export default function Footer() {
  const currentYear = new Date().getFullYear()

  return (
    <footer className="bg-gray-900 text-white">
      {/* 메인 푸터 */}
      <div className="max-w-7xl mx-auto px-4 py-16">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-12 md:gap-8">
          {/* 브랜드 */}
          <div className="md:col-span-1">
            <Link to="/" className="inline-block mb-5 group">
              <h2 className="text-2xl text-white group-hover:text-gray-300 transition-colors duration-200" style={{ fontFamily: "'Petrov Sans', sans-serif", fontWeight: 400, letterSpacing: '-0.02em' }}>The Gist</h2>
            </Link>
            <p className="text-gray-400 text-sm leading-relaxed">
              전문가가 직접 짚어주는 뉴스의 이면과 우리에게 전달될 파급력을 전해 드립니다.
            </p>
          </div>

          {/* 섹션 */}
          <div>
            <h3 className="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 mb-5">
              섹션
            </h3>
            <ul className="space-y-3">
              <li>
                <Link to="/diplomacy" className="text-sm text-gray-400 hover:text-white transition-colors duration-200 relative inline-block after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-[1px] after:bg-white after:transition-all after:duration-300 hover:after:w-full">
                  Foreign Affairs
                </Link>
              </li>
              <li>
                <Link to="/economy" className="text-sm text-gray-400 hover:text-white transition-colors duration-200 relative inline-block after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-[1px] after:bg-white after:transition-all after:duration-300 hover:after:w-full">
                  Economy
                </Link>
              </li>
              <li>
                <Link to="/technology" className="text-sm text-gray-400 hover:text-white transition-colors duration-200 relative inline-block after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-[1px] after:bg-white after:transition-all after:duration-300 hover:after:w-full">
                  Technology
                </Link>
              </li>
              <li>
                <Link to="/entertainment" className="text-sm text-gray-400 hover:text-white transition-colors duration-200 relative inline-block after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-[1px] after:bg-white after:transition-all after:duration-300 hover:after:w-full">
                  Entertainment
                </Link>
              </li>
            </ul>
          </div>

          {/* 계정 */}
          <div>
            <h3 className="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 mb-5">
              계정
            </h3>
            <ul className="space-y-3">
              <li>
                <Link to="/login" className="text-sm text-gray-400 hover:text-white transition-colors duration-200 relative inline-block after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-[1px] after:bg-white after:transition-all after:duration-300 hover:after:w-full">
                  로그인
                </Link>
              </li>
              <li>
                <Link to="/register" className="text-sm text-gray-400 hover:text-white transition-colors duration-200 relative inline-block after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-[1px] after:bg-white after:transition-all after:duration-300 hover:after:w-full">
                  구독하기
                </Link>
              </li>
            </ul>
          </div>

          {/* 구독 */}
          <div>
            <h3 className="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 mb-5">
              구독
            </h3>
            <p className="text-sm text-gray-400 mb-5 leading-relaxed">
              전문가 분석과 심층 리포트를 받아보세요.
            </p>
            <Link
              to="/subscribe"
              className="inline-block px-6 py-2.5 bg-primary-500 hover:bg-primary-400 text-white text-sm font-semibold rounded-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary-500/20"
            >
              구독하기
            </Link>
          </div>
        </div>
      </div>

      {/* 하단 바 */}
      <div className="border-t border-gray-800/50">
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <p className="text-xs text-gray-500">
              &copy; {currentYear} The Gist. All rights reserved.
            </p>
            <div className="flex items-center gap-8">
              <Link to="/privacy" className="text-xs text-gray-500 hover:text-gray-300 transition-colors duration-200">
                개인정보처리방침
              </Link>
              <Link to="/terms" className="text-xs text-gray-500 hover:text-gray-300 transition-colors duration-200">
                이용약관
              </Link>
            </div>
          </div>
        </div>
      </div>
    </footer>
  )
}
