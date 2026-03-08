import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../../services/api'
import MaterialIcon from './MaterialIcon'

const DEFAULT_TERMS = `이용약관

주식회사 더지스트(이하 "회사")의 thegist.co.kr(이하 "서비스") 이용약관입니다.

제1조 (목적)
본 약관은 회사가 제공하는 서비스의 이용 조건 및 절차를 규정합니다.

제2조 (약관의 효력)
본 약관은 서비스 화면에 게시하거나 기타의 방법으로 공지함으로써 효력이 발생합니다.

제3조 (서비스의 제공)
회사는 뉴스 분석, AI 기반 콘텐츠 등 서비스를 제공합니다.

제4조 (이용자의 의무)
이용자는 관련 법령 및 본 약관을 준수하여야 합니다.

제5조 (문의)
문의: info@thegist.co.kr`

interface TermsModalProps {
  isOpen: boolean
  onClose: () => void
}

const TermsModal: React.FC<TermsModalProps> = ({ isOpen, onClose }) => {
  const [content, setContent] = useState<string>(DEFAULT_TERMS)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!isOpen) return
    setLoading(true)
    api.get<{ success: boolean; data: { content: string | null } }>('/settings/terms')
      .then((r) => {
        const c = r.data?.data?.content
        setContent((c && c.trim()) ? c : DEFAULT_TERMS)
      })
      .catch(() => setContent(DEFAULT_TERMS))
      .finally(() => setLoading(false))
  }, [isOpen])

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/50 z-40 backdrop-blur-sm"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 20 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-4 md:inset-8 lg:inset-16 z-50 flex items-center justify-center p-4"
          >
            <div
              className="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 className="text-lg font-semibold text-gray-900">이용약관</h3>
                <button
                  onClick={onClose}
                  className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded-lg transition-colors"
                  aria-label="닫기"
                >
                  <MaterialIcon name="close" className="w-5 h-5" size={20} />
                </button>
              </div>
              <div className="flex-1 overflow-y-auto px-6 py-5 text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                {loading ? '로딩 중...' : content}
              </div>
              <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <button
                  onClick={onClose}
                  className="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-medium rounded-lg transition-colors"
                >
                  확인
                </button>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}

export default TermsModal
