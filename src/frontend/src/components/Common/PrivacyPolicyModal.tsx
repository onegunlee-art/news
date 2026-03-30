import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../../services/api'
import { PRIVACY_POLICY_CONTENT } from './PrivacyPolicyContent'
import MaterialIcon from './MaterialIcon'
import { formatContentHtml } from '../../utils/sanitizeHtml'

interface PrivacyPolicyModalProps {
  isOpen: boolean
  onClose: () => void
}

const PrivacyPolicyModal: React.FC<PrivacyPolicyModalProps> = ({ isOpen, onClose }) => {
  const [content, setContent] = useState<string>(PRIVACY_POLICY_CONTENT)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!isOpen) return
    setLoading(true)
    api.get<{ success: boolean; data: { content: string | null } }>('/settings/privacy')
      .then((r) => {
        const c = r.data?.data?.content
        setContent((c && c.trim()) ? c : PRIVACY_POLICY_CONTENT)
      })
      .catch(() => setContent(PRIVACY_POLICY_CONTENT))
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
                <h3 className="text-lg font-semibold text-gray-900">개인정보처리방침</h3>
                <button
                  onClick={onClose}
                  className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded-lg transition-colors"
                  aria-label="닫기"
                >
                  <MaterialIcon name="close" className="w-5 h-5" size={20} />
                </button>
              </div>
              <div className="flex-1 overflow-y-auto px-6 py-5 text-sm text-gray-700 leading-relaxed [&_b]:font-bold [&_strong]:font-bold">
                {loading ? (
                  '로딩 중...'
                ) : (
                  <div dangerouslySetInnerHTML={{ __html: formatContentHtml(content) }} />
                )}
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

export default PrivacyPolicyModal
