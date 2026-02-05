import { Link } from 'react-router-dom'

export default function Footer() {
  const currentYear = new Date().getFullYear()

  return (
    <footer className="bg-gray-50 border-t border-gray-100 pb-20 md:pb-0">
      {/* 메인 푸터 - 데스크톱만, 콘텐츠와 동일 max-width */}
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-12">
        <div className="flex flex-col md:flex-row justify-between items-center gap-8">
          {/* 브랜드 */}
          <div className="text-center md:text-left">
            <Link to="/" className="inline-block group">
              <h2 
                className="text-2xl text-gray-900 group-hover:text-primary-500 transition-colors duration-200" 
                style={{ fontFamily: "'Lobster', cursive", fontWeight: 400 }}
              >
                The Gist
              </h2>
            </Link>
            <p className="text-gray-500 text-sm mt-2">
              가볍게 접하는 글로벌 저널
            </p>
          </div>

          {/* 링크들 */}
          <div className="flex items-center gap-8">
            <Link 
              to="/diplomacy" 
              className="text-sm text-gray-500 hover:text-gray-900 transition-colors"
            >
              Foreign Affairs
            </Link>
            <Link 
              to="/economy" 
              className="text-sm text-gray-500 hover:text-gray-900 transition-colors"
            >
              Economy
            </Link>
            <Link 
              to="/technology" 
              className="text-sm text-gray-500 hover:text-gray-900 transition-colors"
            >
              Technology
            </Link>
          </div>
        </div>
      </div>

      {/* 하단 바 */}
      <div className="border-t border-gray-100">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-4">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <p className="text-xs text-gray-400">
              &copy; {currentYear} The Gist. All rights reserved.
            </p>
            <div className="flex items-center gap-6">
              <Link to="/privacy" className="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                개인정보처리방침
              </Link>
              <Link to="/terms" className="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                이용약관
              </Link>
            </div>
          </div>
        </div>
      </div>
    </footer>
  )
}
