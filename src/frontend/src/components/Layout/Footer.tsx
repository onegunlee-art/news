import { useState } from 'react'
import { Link } from 'react-router-dom'
import PrivacyPolicyModal from '../Common/PrivacyPolicyModal'
import TermsModal from '../Common/TermsModal'

export default function Footer() {
  const currentYear = new Date().getFullYear()
  const [showPrivacyModal, setShowPrivacyModal] = useState(false)
  const [showTermsModal, setShowTermsModal] = useState(false)

  return (
    <footer className="bg-gray-50 border-t border-gray-100 pb-6 md:pb-0">
      {/* 메인 푸터 - 이미지 [하단] 구조 */}
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-12">
        <div className="flex flex-col items-center text-center">
          {/* 브랜드 */}
          <Link to="/" className="inline-block group">
            <h2
              className="text-2xl text-primary-500 group-hover:text-primary-600 transition-colors duration-200"
              style={{ fontFamily: "'Lobster', cursive", fontWeight: 400 }}
            >
              The Gist
            </h2>
          </Link>
          <p className="text-gray-500 text-sm mt-2">
            Gisters, Becoming Leaders
          </p>
          {/* 이용 약관 | 개인정보처리방침 (둘 다 팝업) */}
          <div className="flex items-center gap-4 mt-6">
            <button
              type="button"
              onClick={() => setShowTermsModal(true)}
              className="text-xs text-gray-400 hover:text-gray-600 transition-colors"
            >
              이용 약관
            </button>
            <button
              type="button"
              onClick={() => setShowPrivacyModal(true)}
              className="text-xs text-gray-400 hover:text-gray-600 transition-colors"
            >
              개인정보처리방침
            </button>
          </div>
        </div>
      </div>

      {/* 하단 바 - © 2026 The Gist */}
      <div className="border-t border-gray-100">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-4">
          <p className="text-xs text-gray-400 text-center">
            © {currentYear} The Gist
          </p>
        </div>
      </div>

      <TermsModal isOpen={showTermsModal} onClose={() => setShowTermsModal(false)} />
      <PrivacyPolicyModal isOpen={showPrivacyModal} onClose={() => setShowPrivacyModal(false)} />
    </footer>
  )
}
