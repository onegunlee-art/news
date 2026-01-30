import { Link } from 'react-router-dom'

export default function Footer() {
  const currentYear = new Date().getFullYear()

  return (
    <footer className="bg-dark-600/50 border-t border-white/5 mt-auto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* 브랜드 */}
          <div className="col-span-1 md:col-span-2">
            <Link to="/" className="inline-block mb-4">
              <span className="font-display font-bold text-xl bg-gradient-to-r from-primary-400 to-primary-600 bg-clip-text text-transparent">
                Infer
              </span>
            </Link>
            <p className="text-gray-400 text-sm leading-relaxed max-w-md">
              전문가가 직접 짚어주는 뉴스의 이면과 우리에게 전달될 파급력을 전해 드립니다.
            </p>
          </div>

          {/* 링크 */}
          <div>
            <h3 className="text-white font-semibold mb-4">서비스</h3>
            <ul className="space-y-2">
              <li>
                <Link to="/" className="text-gray-400 hover:text-primary-400 text-sm transition-colors">
                  뉴스 피드
                </Link>
              </li>
              <li>
                <Link to="/analysis" className="text-gray-400 hover:text-primary-400 text-sm transition-colors">
                  텍스트 분석
                </Link>
              </li>
            </ul>
          </div>

          {/* 기술 스택 */}
          <div>
            <h3 className="text-white font-semibold mb-4">기술 스택</h3>
            <ul className="space-y-2 text-sm text-gray-400">
              <li>React + TypeScript</li>
              <li>PHP 8.4 Backend</li>
              <li>MySQL 8.0</li>
              <li>Naver News API</li>
            </ul>
          </div>
        </div>

        <div className="mt-8 pt-8 border-t border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4">
          <p className="text-gray-500 text-sm">
            &copy; {currentYear} Infer. All rights reserved.
          </p>
          <div className="flex items-center gap-4">
            <a 
              href="https://developers.kakao.com" 
              target="_blank" 
              rel="noopener noreferrer"
              className="text-gray-500 hover:text-gray-400 text-sm transition-colors"
            >
              Kakao API
            </a>
            <a 
              href="https://developers.naver.com" 
              target="_blank" 
              rel="noopener noreferrer"
              className="text-gray-500 hover:text-gray-400 text-sm transition-colors"
            >
              Naver API
            </a>
          </div>
        </div>
      </div>
    </footer>
  )
}
