import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import PrivacyPolicyModal from '../Common/PrivacyPolicyModal'
import TermsModal from '../Common/TermsModal'
import { siteSettingsApi } from '../../services/api'

const defaultVision = 'Gisters, Becoming Leaders'
const defaultCopyright = () => `© ${new Date().getFullYear()} The Gist`

export default function Footer() {
  const [showPrivacyModal, setShowPrivacyModal] = useState(false)
  const [showTermsModal, setShowTermsModal] = useState(false)
  const [vision, setVision] = useState(defaultVision)
  const [copyright, setCopyright] = useState(defaultCopyright())

  useEffect(() => {
    siteSettingsApi.getSite().then((res) => {
      if (res.data?.data) {
        if (res.data.data.the_gist_vision?.trim()) setVision(res.data.data.the_gist_vision.trim())
        if (res.data.data.copyright_text?.trim()) setCopyright(res.data.data.copyright_text.trim())
        else setCopyright(defaultCopyright())
      }
    }).catch(() => {})
  }, [])

  return (
    <footer className="bg-page-secondary border-t border-page pb-6 md:pb-0">
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-12">
        <div className="flex flex-col items-center text-center">
          <Link to="/" className="inline-block group">
            <h2
              className="text-2xl text-primary-500 group-hover:text-primary-600 transition-colors duration-200"
              style={{ fontFamily: "'Lobster', cursive", fontWeight: 400 }}
            >
              The Gist
            </h2>
          </Link>
          <p className="text-page-secondary text-sm mt-2">{vision}</p>
          <div className="flex items-center gap-4 mt-6">
            <button
              type="button"
              onClick={() => setShowTermsModal(true)}
              className="text-xs text-page-muted hover:text-page-secondary transition-colors"
            >
              이용 약관
            </button>
            <button
              type="button"
              onClick={() => setShowPrivacyModal(true)}
              className="text-xs text-page-muted hover:text-page-secondary transition-colors"
            >
              개인정보처리방침
            </button>
          </div>
        </div>
      </div>

      <div className="border-t border-page">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-4 text-center">
          <p className="text-xs text-page-muted">사업자등록번호: 178-86-03814 | 대표: 이원근</p>
          <p className="text-xs text-page-muted mt-1">전화: 1551-6210 | 주소: 서울특별시 영등포구 국제금융로8길 27-8, 4116호</p>
          <p className="text-xs text-page-muted mt-1">{copyright}</p>
        </div>
      </div>

      <TermsModal isOpen={showTermsModal} onClose={() => setShowTermsModal(false)} />
      <PrivacyPolicyModal isOpen={showPrivacyModal} onClose={() => setShowPrivacyModal(false)} />
    </footer>
  )
}
